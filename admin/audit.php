<?php
/**
 * KhuntaLocal — Audit log viewer.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_permission('audit.view');

$action = input('action_filter');
$perPage = 40;
$page    = max(1, (int) input('page', '1'));

$where = '';
$params = [];
if ($action !== '') {
    $where = 'WHERE a.action = ?';
    $params[] = $action;
}

$total = (int) fetch_column("SELECT COUNT(*) FROM admin_logs a $where", $params, 0);
$pages = (int) ceil($total / $perPage);
$rows  = fetch_all(
    "SELECT a.*, u.name AS admin_name
       FROM admin_logs a
       LEFT JOIN users u ON u.id = a.admin_id
       $where
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);

// Distinct actions for the filter dropdown.
$actions = array_column(fetch_all('SELECT DISTINCT action FROM admin_logs ORDER BY action'), 'action');

$pageTitle   = 'Audit log';
$activeAdmin = 'audit';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<form method="get" class="d-flex align-items-end gap-2 mb-3">
  <div>
    <label class="form-label small" for="action_filter">Action</label>
    <select class="form-select form-select-sm" id="action_filter" name="action_filter" onchange="this.form.submit()" style="min-width:220px">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e_attr($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <span class="ms-auto small text-muted-2 align-self-center"><?= e(number_format($total)) ?> entries</span>
</form>

<?php if ($rows): ?>
  <div class="table-responsive kl-card" style="box-shadow:none">
    <table class="table align-middle mb-0">
      <thead><tr class="small text-muted-2">
        <th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Change</th><th class="d-none d-lg-table-cell">Reason</th><th class="d-none d-md-table-cell">IP</th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="small text-muted-2" title="<?= e_attr((string) $r['created_at']) ?>"><?= e(time_ago((string) $r['created_at'])) ?></td>
            <td class="small"><?= e((string) ($r['admin_name'] ?? 'System')) ?></td>
            <td><span class="kl-badge kl-badge--category"><?= e((string) $r['action']) ?></span></td>
            <td class="small text-muted-2">
              <?= e((string) ($r['entity_type'] ?? '—')) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?>
              <?php if (!empty($r['news_id'])): ?><a href="<?= e_attr(base_url('admin/verify.php?id=' . (int) $r['news_id'])) ?>">→ news #<?= (int) $r['news_id'] ?></a><?php endif; ?>
            </td>
            <td class="small">
              <?php if ($r['old_status'] || $r['new_status']): ?>
                <span class="text-muted-2"><?= e((string) ($r['old_status'] ?? '—')) ?></span> → <strong><?= e((string) ($r['new_status'] ?? '—')) ?></strong>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="d-none d-lg-table-cell small text-muted-2" style="max-width:260px"><?= e(str_excerpt((string) ($r['reason'] ?? ''), 80)) ?></td>
            <td class="d-none d-md-table-cell small text-muted-2"><?= e((string) ($r['ip_address'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php render_pagination($page, $pages, 'admin/audit.php', $action !== '' ? ['action_filter' => $action] : []); ?>
<?php else: ?>
  <?php render_empty_state('No audit entries', 'Administrative actions will be recorded here.', '📋'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
