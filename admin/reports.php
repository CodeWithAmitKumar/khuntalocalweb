<?php
/**
 * KhuntaLocal — Report review (news reports).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_permission('report.review');

if (is_post()) {
    csrf_check();
    $reportId = (int) input('report_id', '0');
    $status   = input('status');
    if ($reportId > 0 && report_set_status($reportId, $status, (int) $user['id'])) {
        flash_set('success', 'Report marked as ' . $status . '.');
    } else {
        flash_set('error', 'Could not update the report.');
    }
    redirect('admin/reports.php?scope=' . urlencode(input('scope', 'open')));
}

$scope = input('scope', 'open');
if (!in_array($scope, ['open', 'all'], true)) {
    $scope = 'open';
}
$where = $scope === 'open' ? "WHERE r.status IN ('open','reviewing')" : '';
$rows = fetch_all(
    "SELECT r.*, n.title AS news_title, n.slug AS news_slug, u.name AS reporter_name
       FROM news_reports r
       JOIN news n ON n.id = r.news_id
       LEFT JOIN users u ON u.id = r.user_id
       $where
      ORDER BY r.created_at DESC
      LIMIT 100"
);

$pageTitle   = 'Reports';
$activeAdmin = 'reports';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<div class="btn-group btn-group-sm mb-3" role="group">
  <a class="btn <?= $scope === 'open' ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/reports.php?scope=open')) ?>">Open</a>
  <a class="btn <?= $scope === 'all' ? 'btn-emerald' : 'btn-outline-emerald' ?>" href="<?= e_attr(base_url('admin/reports.php?scope=all')) ?>">All</a>
</div>

<?php if ($rows): ?>
  <div class="table-responsive kl-card" style="box-shadow:none">
    <table class="table align-middle mb-0">
      <thead><tr class="small text-muted-2">
        <th>Story</th><th>Reason</th><th class="d-none d-md-table-cell">By</th><th class="d-none d-lg-table-cell">When</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="max-width:240px">
              <a href="<?= e_attr(news_url((string) $r['news_slug'])) ?>" target="_blank" rel="noopener"><?= e(str_excerpt((string) $r['news_title'], 50)) ?></a>
              <?php if (!empty($r['note'])): ?><div class="small text-muted-2"><?= e(str_excerpt((string) $r['note'], 80)) ?></div><?php endif; ?>
            </td>
            <td><span class="kl-risk kl-risk--medium"><?= e(reason_label((string) $r['reason'])) ?></span></td>
            <td class="d-none d-md-table-cell small"><?= e((string) ($r['reporter_name'] ?? 'Anonymous')) ?></td>
            <td class="d-none d-lg-table-cell small text-muted-2"><?= e(time_ago((string) $r['created_at'])) ?></td>
            <td><span class="badge text-bg-<?= $r['status']==='open'?'warning':($r['status']==='reviewing'?'info':'secondary') ?>"><?= e((string) $r['status']) ?></span></td>
            <td>
              <form method="post" action="<?= e_attr(base_url('admin/reports.php')) ?>" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="scope" value="<?= e_attr($scope) ?>">
                <?php if ($r['status'] === 'open'): ?>
                  <button class="btn btn-sm btn-outline-emerald" name="status" value="reviewing">Review</button>
                <?php endif; ?>
                <button class="btn btn-sm btn-emerald" name="status" value="resolved">Resolve</button>
                <button class="btn btn-sm btn-outline-emerald" name="status" value="dismissed">Dismiss</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <?php render_empty_state('No reports', $scope === 'open' ? 'There are no open reports.' : 'No reports found.', '✅'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
