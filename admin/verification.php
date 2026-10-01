<?php
/**
 * KhuntaLocal — Verification queue.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_permission('news.verify');

$statuses = ['pending', 'under_review', 'needs_information'];
$filter   = input('status');
if (!in_array($filter, $statuses, true)) {
    $filter = '';
}

$perPage = 20;
$page    = max(1, (int) input('page', '1'));

if ($filter !== '') {
    $where  = 'n.status = ?';
    $params = [$filter];
} else {
    $where  = "n.status IN ('pending','under_review','needs_information')";
    $params = [];
}

$total = (int) fetch_column("SELECT COUNT(*) FROM news n WHERE $where", $params, 0);
$pages = (int) ceil($total / $perPage);
$rows  = fetch_all(
    news_select_base() . " WHERE $where ORDER BY n.submitted_at ASC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);

$pageTitle   = 'Verification queue';
$activeAdmin = 'verification';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="btn-group btn-group-sm" role="group">
    <a class="btn <?= $filter === '' ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/verification.php')) ?>">All open (<?= $total && $filter==='' ? $total : '' ?><?= $filter!=='' ? '' : '' ?>)</a>
    <?php foreach ($statuses as $s): [$lbl]=status_label($s); ?>
      <a class="btn <?= $filter === $s ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/verification.php?status=' . $s)) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="ms-auto small text-muted-2"><?= e(number_format($total)) ?> item<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php if ($rows): ?>
  <div class="table-responsive kl-card" style="box-shadow:none">
    <table class="table align-middle mb-0">
      <thead><tr class="small text-muted-2">
        <th>#</th><th>Headline</th><th>Reporter</th><th class="d-none d-lg-table-cell">Category</th>
        <th class="d-none d-md-table-cell">Submitted</th><th>Timer</th><th>Risk</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $n):
            [$label, $variant] = status_label((string) $n['status']);
            $t = review_timer($n['submitted_at'] ?? null);
            $risk = (string) ($n['risk_level'] ?? 'unknown');
        ?>
          <tr>
            <td class="text-muted-2">#<?= (int) $n['id'] ?></td>
            <td class="fw-semibold" style="max-width:260px"><?= e(str_excerpt((string) $n['title'], 58)) ?></td>
            <td class="small"><?= e((string) ($n['author_name'] ?? '—')) ?></td>
            <td class="d-none d-lg-table-cell small"><?= e((string) ($n['category_name'] ?? '—')) ?></td>
            <td class="d-none d-md-table-cell small text-muted-2"><?= e($n['submitted_at'] ? time_ago($n['submitted_at']) : '—') ?></td>
            <td><span class="kl-timer kl-timer--<?= e_attr($t['state']) ?>" title="min <?= $t['min_min'] ?>m / max <?= $t['max_min'] ?>m"><?= e($t['label']) ?></span></td>
            <td><span class="kl-risk kl-risk--<?= e_attr($risk) ?>"><?= e($risk) ?></span></td>
            <td><span class="badge text-bg-<?= e_attr($variant) ?>"><?= e($label) ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-emerald" href="<?= e_attr(base_url('admin/verify.php?id=' . (int) $n['id'])) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php render_pagination($page, $pages, 'admin/verification.php', $filter !== '' ? ['status' => $filter] : []); ?>
<?php else: ?>
  <?php render_empty_state('Nothing to review', 'The verification queue is empty for this filter.', '🎉'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
