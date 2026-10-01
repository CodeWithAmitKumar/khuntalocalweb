<?php
/**
 * KhuntaLocal — cron: run automated verification checks on items in the queue.
 *
 * Safe to run repeatedly. Re-checks pending / under_review items that haven't
 * been checked recently, and notifies verifiers once when an item passes its
 * maximum review window without a decision.
 */

declare(strict_types=1);
require __DIR__ . '/_cli.php';

$recheckMinutes = 30;  // don't re-run the engine more often than this per item
$maxMinutes     = setting_int('verification_max_minutes', (int) config('verification.max_review_minutes', 120));

cron_log('verification-check: starting');

/* 1) Re-run the engine on items needing a (re)check. */
$items = fetch_all(
    "SELECT n.id
       FROM news n
       LEFT JOIN news_verification v ON v.news_id = n.id
      WHERE n.status IN ('pending','under_review','needs_information')
        AND (v.last_checked_at IS NULL OR v.last_checked_at < (NOW() - INTERVAL ? MINUTE))
      ORDER BY n.submitted_at ASC
      LIMIT 200",
    [$recheckMinutes]
);
$checked = 0;
foreach ($items as $it) {
    try {
        verification_engine_run((int) $it['id'], ['actor_type' => 'system']);
        $checked++;
    } catch (Throwable $ex) {
        error_log('verification-check failed for #' . $it['id'] . ': ' . $ex->getMessage());
    }
}
cron_log("verification-check: re-checked {$checked} item(s)");

/* 2) Escalate items past the maximum review window (notify verifiers once/day). */
$overdue = fetch_all(
    "SELECT n.id, n.title
       FROM news n
      WHERE n.status IN ('pending','under_review')
        AND n.submitted_at < (NOW() - INTERVAL ? MINUTE)
        AND NOT EXISTS (
            SELECT 1 FROM notifications nt
             WHERE nt.news_id = n.id AND nt.type = 'news.overdue'
               AND nt.created_at > (NOW() - INTERVAL 1 DAY)
        )
      LIMIT 100",
    [$maxMinutes]
);
foreach ($overdue as $o) {
    notify_staff('news.verify', 'news.overdue', 'Submission past review window',
        (string) $o['title'], ['news_id' => (int) $o['id']]);
}
cron_log('verification-check: escalated ' . count($overdue) . ' overdue item(s)');
cron_log('verification-check: done');
