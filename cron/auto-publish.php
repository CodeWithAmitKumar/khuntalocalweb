<?php
/**
 * KhuntaLocal — cron: auto-publish policy + scheduled publishing.
 *
 * IMPORTANT: a story is NEVER published just because a timer expired. When the
 * minimum review window has elapsed we run final automated checks and only
 * publish if the configured policy allows it and no critical safety flags are
 * present. Every automated decision is written to the audit log.
 *
 * Safe to run repeatedly — status guards + row locks prevent double-publishing.
 */

declare(strict_types=1);
require __DIR__ . '/_cli.php';

cron_log('auto-publish: starting');

$autoOn      = setting_bool('auto_publish_enabled', false);
$lowRiskOnly = setting_bool('auto_publish_low_risk_only', true);
$minMinutes  = setting_int('verification_min_minutes', (int) config('verification.min_review_minutes', 60));

$toNotify = []; // [newsId => [user_id, title, kind]]

/* ---------------------------------------------------------------------------
 * 1) Scheduled stories whose time has arrived (independent of the auto toggle —
 *    an admin explicitly scheduled these).
 * ------------------------------------------------------------------------- */
$scheduled = fetch_all(
    "SELECT id FROM news WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() LIMIT 200"
);
$pubScheduled = 0;
foreach ($scheduled as $s) {
    $id = (int) $s['id'];
    try {
        $row = db_transaction(function () use ($id) {
            $r = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$id]);
            if (!$r || $r['status'] !== 'scheduled') {
                return null;
            }
            db_update('news', [
                'status'       => 'published',
                'published_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            audit_log('AUTO_PUBLISH_DECISION', [
                'entity_type' => 'news', 'entity_id' => $id, 'news_id' => $id,
                'old_status' => 'scheduled', 'new_status' => 'published',
                'reason' => 'Scheduled time reached.', 'admin_id' => null,
            ]);
            verification_log($id, 'AUTO_PUBLISHED', ['actor_type' => 'system', 'new_status' => 'published', 'note' => 'Scheduled publication.']);
            return $r;
        });
        if ($row) {
            $pubScheduled++;
            $toNotify[$id] = ['user_id' => (int) $row['user_id'], 'title' => (string) $row['title']];
        }
    } catch (Throwable $ex) {
        error_log('auto-publish (scheduled) #' . $id . ': ' . $ex->getMessage());
    }
}
cron_log("auto-publish: published {$pubScheduled} scheduled item(s)");

/* ---------------------------------------------------------------------------
 * 2) Auto-publish policy for reviewed-but-undecided items.
 * ------------------------------------------------------------------------- */
if (!$autoOn) {
    cron_log('auto-publish: automatic publishing is OFF — skipping policy step.');
} else {
    $candidates = fetch_all(
        "SELECT id FROM news
          WHERE status IN ('pending','under_review')
            AND submitted_at IS NOT NULL
            AND submitted_at <= (NOW() - INTERVAL ? MINUTE)
          ORDER BY submitted_at ASC
          LIMIT 200",
        [$minMinutes]
    );

    $published = 0; $held = 0;
    foreach ($candidates as $c) {
        $id = (int) $c['id'];

        // Final automated checks (fresh) — outside the lock is fine; it upserts.
        $result = verification_engine_run($id, ['actor_type' => 'system']);

        $decision = 'publish';
        $reason   = 'Auto-published: min review window elapsed and checks passed.';
        if ($result['critical']) {
            $decision = 'hold';
            $reason   = 'Held: critical safety flag present.';
        } elseif ($lowRiskOnly && $result['risk_level'] !== 'low') {
            $decision = 'hold';
            $reason   = 'Held: risk is ' . $result['risk_level'] . ' and policy allows only low-risk auto-publish.';
        }

        try {
            $row = db_transaction(function () use ($id, $decision, $reason, $result) {
                $r = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$id]);
                if (!$r || !in_array($r['status'], ['pending', 'under_review'], true)) {
                    return null; // already decided elsewhere
                }
                if ($decision === 'publish') {
                    db_update('news', [
                        'status'       => 'published',
                        'published_at' => date('Y-m-d H:i:s'),
                        'reviewed_at'  => date('Y-m-d H:i:s'),
                        'risk_level'   => $result['risk_level'],
                    ], ['id' => $id]);
                    db_run("UPDATE news_verification SET auto_decision = 'eligible' WHERE news_id = ?", [$id]);
                    audit_log('AUTO_PUBLISH_DECISION', [
                        'entity_type' => 'news', 'entity_id' => $id, 'news_id' => $id,
                        'old_status' => (string) $r['status'], 'new_status' => 'published',
                        'reason' => $reason, 'admin_id' => null,
                    ]);
                    verification_log($id, 'AUTO_PUBLISHED', ['actor_type' => 'system', 'old_status' => (string) $r['status'], 'new_status' => 'published', 'note' => $reason]);
                    $r['_published'] = true;
                } else {
                    db_run("UPDATE news_verification SET auto_decision = 'hold' WHERE news_id = ?", [$id]);
                    audit_log('AUTO_PUBLISH_DECISION', [
                        'entity_type' => 'news', 'entity_id' => $id, 'news_id' => $id,
                        'old_status' => (string) $r['status'], 'new_status' => (string) $r['status'],
                        'reason' => $reason, 'admin_id' => null,
                    ]);
                    $r['_published'] = false;
                }
                return $r;
            });

            if ($row && !empty($row['_published'])) {
                $published++;
                $toNotify[$id] = ['user_id' => (int) $row['user_id'], 'title' => (string) $row['title']];
            } elseif ($row) {
                $held++;
            }
        } catch (Throwable $ex) {
            error_log('auto-publish (policy) #' . $id . ': ' . $ex->getMessage());
        }
    }
    cron_log("auto-publish: published {$published}, held {$held} (reviewed cohort)");
}

/* 3) Notify reporters of anything published this run. */
foreach ($toNotify as $id => $info) {
    notify((int) $info['user_id'], 'news.published', 'Your story is now published',
        '“' . str_excerpt((string) $info['title'], 80) . '” has been published.', ['news_id' => (int) $id]);
}
cron_log('auto-publish: done');
