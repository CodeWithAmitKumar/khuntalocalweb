<?php
/**
 * KhuntaLocal — Comment moderation.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_permission('comment.moderate');

if (is_post()) {
    csrf_check();
    $commentId = (int) input('comment_id', '0');
    $action    = input('action');
    $map = ['approve' => 'approved', 'hide' => 'hidden', 'delete' => 'deleted'];
    if (isset($map[$action]) && $commentId > 0) {
        comment_set_status($commentId, $map[$action], (int) $user['id']);
        flash_set('success', 'Comment ' . $action . 'd.');
    } else {
        flash_set('error', 'Unknown action.');
    }
    redirect('admin/comments.php?scope=' . urlencode(input('scope', 'pending')));
}

$scope = input('scope', 'pending');
if (!in_array($scope, ['pending', 'reported'], true)) {
    $scope = 'pending';
}
$rows = comment_moderation_list($scope, 100);

$pageTitle   = 'Comment moderation';
$activeAdmin = 'comments';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<div class="btn-group btn-group-sm mb-3" role="group">
  <a class="btn <?= $scope === 'pending' ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/comments.php?scope=pending')) ?>">Pending</a>
  <a class="btn <?= $scope === 'reported' ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/comments.php?scope=reported')) ?>">Reported</a>
</div>

<?php if ($rows): ?>
  <div class="d-grid gap-2">
    <?php foreach ($rows as $c): ?>
      <div class="kl-card p-3" style="box-shadow:none">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div class="small text-muted-2">
            <strong><?= e((string) $c['author_name']) ?></strong> on
            <a href="<?= e_attr(news_url((string) $c['news_slug'])) ?>" target="_blank" rel="noopener"><?= e(str_excerpt((string) $c['news_title'], 50)) ?></a>
            · <?= e(time_ago((string) $c['created_at'])) ?>
            <?php if ((int) ($c['open_reports'] ?? 0) > 0): ?><span class="kl-risk kl-risk--high ms-1"><?= (int) $c['open_reports'] ?> report(s)</span><?php endif; ?>
            <span class="badge text-bg-<?= $c['status']==='approved'?'success':($c['status']==='pending'?'warning':'secondary') ?>"><?= e((string) $c['status']) ?></span>
          </div>
        </div>
        <p class="my-2" style="white-space:pre-wrap"><?= e((string) $c['body']) ?></p>
        <form method="post" action="<?= e_attr(base_url('admin/comments.php')) ?>" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="comment_id" value="<?= (int) $c['id'] ?>">
          <input type="hidden" name="scope" value="<?= e_attr($scope) ?>">
          <?php if ($c['status'] !== 'approved'): ?>
            <button class="btn btn-sm btn-emerald" name="action" value="approve">Approve</button>
          <?php endif; ?>
          <?php if ($c['status'] !== 'hidden'): ?>
            <button class="btn btn-sm btn-outline-emerald" name="action" value="hide">Hide</button>
          <?php endif; ?>
          <button class="btn btn-sm btn-outline-emerald text-danger" name="action" value="delete"
                  onclick="return confirm('Delete this comment?')">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php render_empty_state('Nothing to moderate', $scope === 'reported' ? 'No reported comments.' : 'No comments awaiting moderation.', '💬'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
