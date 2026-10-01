<?php
/**
 * KhuntaLocal — Notifications centre.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

// Mark all read (POST).
if (is_post()) {
    csrf_check();
    if (input('action') === 'read_all') {
        notifications_mark_all_read((int) $user['id']);
        flash_set('success', 'All notifications marked as read.');
    }
    redirect('notifications.php');
}

// Open one notification: mark read, then go to its story (or stay).
$open = (int) input('open', '0');
if ($open > 0) {
    $n = fetch('SELECT n.id, n.news_id, nw.slug FROM notifications n LEFT JOIN news nw ON nw.id = n.news_id WHERE n.id = ? AND n.user_id = ? LIMIT 1', [$open, (int) $user['id']]);
    if ($n) {
        notification_mark_read($open, (int) $user['id']);
        if (!empty($n['slug'])) {
            redirect('news/' . $n['slug']);
        }
    }
    redirect('notifications.php');
}

$perPage = 30;
$page    = max(1, (int) input('page', '1'));
$total   = notifications_count((int) $user['id']);
$pages   = (int) ceil($total / $perPage);
$rows    = notifications_for_user((int) $user['id'], $perPage, ($page - 1) * $perPage);

$icons = [
    'news.submitted'        => '📤',
    'news.new_submission'   => '🆕',
    'news.resubmitted'      => '🔁',
    'news.published'        => '✅',
    'news.rejected'         => '✕',
    'news.needs_information' => 'ℹ️',
    'news.scheduled'        => '🗓️',
    'news.reported'         => '⚑',
    'comment.new'           => '💬',
    'comment.reported'      => '⚑',
];

$activeNav = '';
$meta = ['title' => 'Notifications | ' . site_name(), 'robots' => 'noindex,nofollow'];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 fw-bold mb-0">Notifications</h1>
      <?php if ($total > 0): ?>
        <form method="post" action="<?= e_attr(base_url('notifications.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="read_all">
          <button class="btn btn-outline-emerald btn-sm" type="submit">Mark all read</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($rows): ?>
      <div class="d-grid gap-2">
        <?php foreach ($rows as $n): $unread = !((int) $n['is_read']); ?>
          <a href="<?= e_attr(base_url('notifications.php?open=' . (int) $n['id'])) ?>"
             class="kl-card p-3 d-flex gap-3 text-reset <?= $unread ? '' : 'opacity-75' ?>"
             style="box-shadow:none;<?= $unread ? 'border-left:4px solid var(--kl-primary)' : '' ?>">
            <span style="font-size:1.4rem"><?= $icons[$n['type']] ?? '🔔' ?></span>
            <span class="flex-grow-1">
              <span class="fw-semibold d-block" style="color:var(--kl-charcoal)"><?= e((string) $n['title']) ?></span>
              <?php if (!empty($n['body'])): ?><span class="small text-muted-2 d-block"><?= e(str_excerpt((string) $n['body'], 140)) ?></span><?php endif; ?>
              <span class="small text-muted-2"><?= e(time_ago((string) $n['created_at'])) ?></span>
            </span>
            <?php if ($unread): ?><span class="badge rounded-pill text-bg-danger align-self-start">new</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php render_pagination($page, $pages, 'notifications.php'); ?>
    <?php else: ?>
      <?php render_empty_state('No notifications yet', 'Updates about your stories will appear here.', '🔔'); ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
