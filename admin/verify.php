<?php
/**
 * KhuntaLocal — Verification review (split layout + actions).
 * LEFT: the original submission.  RIGHT: the verification assistant.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user   = require_permission('news.verify');
$id     = (int) input('id', '0');
$news   = $id > 0 ? news_get($id) : null;

if (!$news) {
    http_response_code(404);
    $pageTitle = 'Not found'; $activeAdmin = 'verification';
    require KL_INCLUDES . '/partials/admin-head.php';
    render_empty_state('Submission not found', 'It may have been removed.', '🔍');
    echo '<a class="btn btn-emerald mt-3" href="' . e_attr(base_url('admin/verification.php')) . '">Back to queue</a>';
    require KL_INCLUDES . '/partials/admin-foot.php';
    exit;
}

/* ---- Handle actions (POST) -------------------------------------------- */
if (is_post()) {
    csrf_check();
    $action = input('action');
    $adminId = (int) $user['id'];
    $done = false; $msg = ''; $type = 'success';

    switch ($action) {
        case 'approve':
            require_permission('news.approve');
            $done = news_approve($id, $adminId);
            $msg  = $done ? 'Story approved and published.' : 'No change — it may already be published.';
            $type = $done ? 'success' : 'info';
            break;

        case 'reject':
            require_permission('news.reject');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                flash_set('error', 'A rejection reason is required.');
                redirect('admin/verify.php?id=' . $id);
            }
            $done = news_reject($id, $adminId, $reason);
            $msg  = $done ? 'Story rejected and the reporter was notified.' : 'No change.';
            $type = $done ? 'success' : 'info';
            break;

        case 'request_info':
            require_permission('news.request_info');
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($note === '') {
                flash_set('error', 'Please describe what information is needed.');
                redirect('admin/verify.php?id=' . $id);
            }
            $done = news_request_info($id, $adminId, $note);
            $msg  = $done ? 'Information request sent to the reporter.' : 'No change.';
            $type = $done ? 'success' : 'info';
            break;

        case 'schedule':
            require_permission('news.approve');
            $when = trim((string) ($_POST['scheduled_at'] ?? ''));
            $done = news_schedule($id, $adminId, $when);
            $msg  = $done ? 'Story scheduled.' : 'Could not schedule — check the date/time.';
            $type = $done ? 'success' : 'error';
            break;

        case 'note':
            // Save an internal verification note (admin-only).
            $note = trim((string) ($_POST['internal_note'] ?? ''));
            db_run(
                'INSERT INTO news_verification (news_id, internal_notes)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE internal_notes = VALUES(internal_notes)',
                [$id, $note !== '' ? $note : null]
            );
            verification_log($id, 'INTERNAL_NOTE', ['actor_type' => 'admin', 'admin_id' => $adminId, 'note' => 'Internal note updated.']);
            $msg = 'Internal note saved.';
            break;

        default:
            flash_set('error', 'Unknown action.');
            redirect('admin/verify.php?id=' . $id);
    }

    flash_set($type, $msg);
    // After a terminal decision, return to the queue; otherwise stay.
    if (in_array($action, ['approve', 'reject', 'schedule'], true) && $done) {
        redirect('admin/verification.php');
    }
    redirect('admin/verify.php?id=' . $id);
}

/* ---- Claim pending items for review ----------------------------------- */
if ($news['status'] === 'pending') {
    if (news_start_review($id, (int) $user['id'])) {
        $news = news_get($id); // reload with new status
    }
}

/* ---- Gather review data ----------------------------------------------- */
$sources   = news_sources_for($id);
$similar   = verification_find_similar($news, 6);
$analysis  = verification_basic_checks($news, $sources, $similar);
$history   = reporter_history((int) $news['user_id']);
$timeline  = verification_timeline($id);
$verRow    = fetch('SELECT * FROM news_verification WHERE news_id = ? LIMIT 1', [$id]);
$internal  = $verRow['internal_notes'] ?? '';
$timer     = review_timer($news['submitted_at'] ?? null);
[$statusLabel, $statusVariant] = status_label((string) $news['status']);

$pageTitle   = 'Review #' . $id;
$activeAdmin = 'verification';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<div class="mb-3 d-flex flex-wrap align-items-center gap-2">
  <a class="btn btn-sm btn-outline-emerald" href="<?= e_attr(base_url('admin/verification.php')) ?>">← Queue</a>
  <span class="badge text-bg-<?= e_attr($statusVariant) ?>"><?= e($statusLabel) ?></span>
  <span class="kl-timer kl-timer--<?= e_attr($timer['state']) ?>" title="min <?= $timer['min_min'] ?>m / max <?= $timer['max_min'] ?>m review window">
    ⏱ <?= e($timer['label']) ?> elapsed
  </span>
  <?php if ($timer['state'] === 'overdue'): ?><span class="small text-danger">Past maximum review window</span><?php endif; ?>
</div>

<div class="kl-verify-grid">

  <!-- LEFT: original submission -->
  <div class="kl-card p-3 p-md-4" style="box-shadow:none">
    <div class="d-flex flex-wrap gap-2 mb-2">
      <?php if (!empty($news['category_name'])): ?><span class="kl-badge kl-badge--category"><?= e($news['category_name']) ?></span><?php endif; ?>
      <?php if (!empty($news['is_breaking'])): ?><span class="kl-badge kl-badge--breaking">Breaking</span><?php endif; ?>
    </div>
    <h2 class="h4"><?= e((string) $news['title']) ?></h2>
    <div class="small text-muted-2 d-flex flex-wrap gap-3 mb-3">
      <span>👤 <?= e((string) ($news['author_name'] ?? '—')) ?></span>
      <span>📍 <?= e((string) ($news['location_name'] ?? '—')) ?></span>
      <span>🌐 <?= e(strtoupper((string) $news['language_code'])) ?></span>
      <span>🕒 submitted <?= e($news['submitted_at'] ? time_ago($news['submitted_at']) : '—') ?></span>
    </div>

    <?php if (!empty($news['cover_path'])): ?>
      <img src="<?= e_attr(upload_url((string) $news['cover_path'])) ?>" alt="cover" class="img-fluid rounded mb-3" style="max-height:320px;object-fit:cover;width:100%">
    <?php endif; ?>

    <?php if (!empty($news['summary'])): ?><p class="fw-semibold"><?= e((string) $news['summary']) ?></p><?php endif; ?>

    <div class="kl-article-body">
      <?php foreach (preg_split('/\n\s*\n/', trim((string) $news['body'])) ?: [] as $p) {
          $p = trim($p); if ($p !== '') echo '<p>' . nl2br(e($p)) . '</p>';
      } ?>
    </div>

    <h3 class="h6 mt-3">Sources / references</h3>
    <?php if ($sources): ?>
      <ul class="small">
        <?php foreach ($sources as $s): ?>
          <li>
            <?php if (!empty($s['url'])): ?>
              <a href="<?= e_attr((string) $s['url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= e($s['label'] ?: $s['url']) ?> ↗</a>
            <?php else: ?><?= e($s['label'] ?: '—') ?><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="small text-muted-2">None provided.</p><?php endif; ?>

    <a class="btn btn-sm btn-outline-emerald" href="<?= e_attr(news_url((string) $news['slug'])) ?>" target="_blank" rel="noopener">Open public preview ↗</a>
  </div>

  <!-- RIGHT: verification assistant -->
  <div class="d-grid gap-3">

    <!-- Reporter -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-2">Reporter</h3>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="kl-logo-mark" style="width:36px;height:36px;background:var(--kl-primary-light);color:var(--kl-primary-dark)"><?= e(mb_substr((string) ($news['author_name'] ?? '?'), 0, 1)) ?></span>
        <div>
          <a href="<?= e_attr(reporter_url((string) $news['author_username'])) ?>" target="_blank" rel="noopener" class="fw-semibold text-reset"><?= e((string) ($news['author_name'] ?? '—')) ?></a>
          <div class="small text-muted-2">@<?= e((string) ($news['author_username'] ?? '')) ?></div>
        </div>
      </div>
      <div class="small text-muted-2">
        Published <strong><?= (int) $history['published'] ?></strong> ·
        Rejected <strong><?= (int) $history['rejected'] ?></strong> ·
        Total <strong><?= (int) $history['total'] ?></strong> ·
        Reports <strong><?= (int) $history['reports'] ?></strong>
      </div>
      <div class="small text-muted-2 mt-1">Internal stats for moderation only — not a public score.</div>
    </div>

    <!-- Checks -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-2">Verification checks</h3>
      <?php foreach ($analysis['checks'] as $c): ?>
        <div class="kl-check kl-check--<?= e_attr($c['status']) ?>">
          <span class="kl-check__badge"><?= e(strtoupper($c['status'])) ?></span>
          <div><span class="fw-semibold"><?= e($c['label']) ?></span><div class="small text-muted-2"><?= e($c['detail']) ?></div></div>
        </div>
      <?php endforeach; ?>
      <?php if ($analysis['concerns']): ?>
        <div class="alert alert-warning mt-3 mb-0 small">
          <strong>Potential concerns</strong>
          <ul class="mb-0 mt-1"><?php foreach ($analysis['concerns'] as $c) echo '<li>' . e($c) . '</li>'; ?></ul>
        </div>
      <?php endif; ?>
      <div class="small text-muted-2 mt-2">Automated checks assist your decision; they do not prove a story is true. External-evidence checks expand in Phase 4.</div>
    </div>

    <!-- Similar / duplicates -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-2">Similar / possible duplicates</h3>
      <?php if ($similar): ?>
        <ul class="list-unstyled d-grid gap-2 m-0">
          <?php foreach ($similar as $s): ?>
            <li class="d-flex align-items-start gap-2">
              <span class="kl-risk kl-risk--<?= $s['match_level']==='high'?'high':($s['match_level']==='medium'?'medium':'unknown') ?>"><?= e($s['match_level']) ?></span>
              <a href="<?= e_attr(base_url('admin/verify.php?id=' . (int) $s['id'])) ?>" class="small"><?= e(str_excerpt((string) $s['title'], 70)) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="small text-muted-2 mb-0">No similar stories found.</p><?php endif; ?>
    </div>

    <!-- Internal notes -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-2">Internal notes</h3>
      <form method="post" action="<?= e_attr(base_url('admin/verify.php?id=' . $id)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="note">
        <textarea class="form-control mb-2" name="internal_note" rows="3" placeholder="Notes visible to reviewers only"><?= e((string) $internal) ?></textarea>
        <button class="btn btn-sm btn-outline-emerald" type="submit">Save note</button>
      </form>
    </div>

    <!-- Decision -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-3">Decision</h3>

      <?php if (can('news.approve', (int) $user['id'])): ?>
      <form method="post" action="<?= e_attr(base_url('admin/verify.php?id=' . $id)) ?>" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="approve">
        <button class="btn btn-emerald w-100" type="submit" <?= $news['status']==='published'?'disabled':'' ?>>✓ Approve &amp; Publish</button>
      </form>
      <?php endif; ?>

      <?php if (can('news.request_info', (int) $user['id'])): ?>
      <details class="mb-2">
        <summary class="btn btn-outline-emerald w-100">↩ Request more information</summary>
        <form method="post" action="<?= e_attr(base_url('admin/verify.php?id=' . $id)) ?>" class="mt-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="request_info">
          <textarea class="form-control mb-2" name="note" rows="3" required placeholder="What does the reporter need to add or clarify?"></textarea>
          <button class="btn btn-soft w-100" type="submit">Send request</button>
        </form>
      </details>
      <?php endif; ?>

      <?php if (can('news.approve', (int) $user['id'])): ?>
      <details class="mb-2">
        <summary class="btn btn-outline-emerald w-100">🗓 Schedule</summary>
        <form method="post" action="<?= e_attr(base_url('admin/verify.php?id=' . $id)) ?>" class="mt-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="schedule">
          <input class="form-control mb-2" type="datetime-local" name="scheduled_at" required>
          <button class="btn btn-soft w-100" type="submit">Schedule</button>
          <div class="form-text">Scheduled stories are published automatically by the Phase 4 cron.</div>
        </form>
      </details>
      <?php endif; ?>

      <?php if (can('news.reject', (int) $user['id'])): ?>
      <details>
        <summary class="btn btn-outline-emerald w-100 text-danger">✕ Reject</summary>
        <form method="post" action="<?= e_attr(base_url('admin/verify.php?id=' . $id)) ?>" class="mt-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="reject">
          <textarea class="form-control mb-2" name="reason" rows="3" required placeholder="Reason (sent to the reporter)"></textarea>
          <button class="btn btn-danger w-100" type="submit">Reject submission</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <!-- Audit history -->
    <div class="kl-card p-3" style="box-shadow:none">
      <h3 class="h6 mb-2">Audit history</h3>
      <?php if ($timeline): ?>
        <ul class="list-unstyled d-grid gap-2 m-0 small">
          <?php foreach ($timeline as $t): ?>
            <li class="border-start ps-2" style="border-color:var(--kl-border)!important">
              <span class="fw-semibold"><?= e((string) $t['action']) ?></span>
              <?php if (!empty($t['new_status'])): ?><span class="text-muted-2">→ <?= e((string) $t['new_status']) ?></span><?php endif; ?>
              <div class="text-muted-2"><?= e($t['admin_name'] ? (string) $t['admin_name'] : ucfirst((string) $t['actor_type'])) ?> · <?= e(time_ago((string) $t['created_at'])) ?></div>
              <?php if (!empty($t['note'])): ?><div class="text-muted-2 fst-italic"><?= e(str_excerpt((string) $t['note'], 140)) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="small text-muted-2 mb-0">No history yet.</p><?php endif; ?>
    </div>

  </div>
</div>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
