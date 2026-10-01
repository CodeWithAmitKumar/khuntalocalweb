<?php
/**
 * KhuntaLocal — Admin dashboard.
 * Accessible to any back-office role; cards adapt to the data.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
if (!is_staff((int) $user['id'])) {
    http_response_code(403);
    exit('403 — Admin access only.');
}

// Dashboard metrics (single lightweight queries).
$totalNews       = (int) fetch_column('SELECT COUNT(*) FROM news', [], 0);
$pendingVerify   = (int) fetch_column("SELECT COUNT(*) FROM news WHERE status IN ('pending','under_review','needs_information')", [], 0);
$publishedToday  = (int) fetch_column("SELECT COUNT(*) FROM news WHERE status='published' AND published_at >= CURDATE()", [], 0);
$breakingLive    = (int) fetch_column("SELECT COUNT(*) FROM news WHERE is_breaking=1 AND status='published' AND (breaking_expires_at IS NULL OR breaking_expires_at > NOW())", [], 0);
$openReports     = (int) fetch_column("SELECT COUNT(*) FROM news_reports WHERE status IN ('open','reviewing')", [], 0);
$totalUsers      = (int) fetch_column('SELECT COUNT(*) FROM users', [], 0);

// Latest queue preview.
$queue = fetch_all(
    news_select_base()
    . " WHERE n.status IN ('pending','under_review','needs_information')
        ORDER BY n.submitted_at ASC LIMIT 6"
);

$pageTitle   = 'Dashboard';
$activeAdmin = 'dashboard';
require KL_INCLUDES . '/partials/admin-head.php';

$cards = [
    ['Total news',          $totalNews,      '🗞️', ''],
    ['Pending verification', $pendingVerify, '🛡️', 'warn'],
    ['Published today',     $publishedToday, '✅', 'accent'],
    ['Breaking (live)',     $breakingLive,   '⚡', ''],
    ['Open reports',        $openReports,    '⚑', $openReports > 0 ? 'danger' : ''],
    ['Users',               $totalUsers,     '👥', ''],
];
?>
<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $num, $icon, $variant]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kl-stat <?= $variant ? 'kl-stat--' . e_attr($variant) : '' ?>">
        <div class="kl-stat__icon"><?= $icon ?></div>
        <div class="kl-stat__num"><?= e((string) $num) ?></div>
        <div class="kl-stat__label"><?= e($label) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h2 class="h5 mb-0">Verification queue</h2>
  <?php if (can('news.verify', (int) $user['id'])): ?>
    <a class="btn btn-sm btn-emerald" href="<?= e_attr(base_url('admin/verification.php')) ?>">Open full queue</a>
  <?php endif; ?>
</div>

<?php if (!can('news.verify', (int) $user['id'])): ?>
  <div class="kl-card p-3" style="box-shadow:none">
    <p class="mb-0 text-muted-2">You don't have the verification permission. Contact a super admin if you need it.</p>
  </div>
<?php elseif ($queue): ?>
  <div class="table-responsive kl-card" style="box-shadow:none">
    <table class="table align-middle mb-0">
      <thead><tr class="small text-muted-2">
        <th>#</th><th>Headline</th><th>Reporter</th><th class="d-none d-md-table-cell">Category</th><th>Status</th><th>Timer</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($queue as $n): [$label,$variant]=status_label((string)$n['status']); $t=review_timer($n['submitted_at'] ?? null); ?>
          <tr>
            <td class="text-muted-2">#<?= (int) $n['id'] ?></td>
            <td class="fw-semibold" style="max-width:280px"><?= e(str_excerpt((string) $n['title'], 60)) ?></td>
            <td class="small"><?= e((string) ($n['author_name'] ?? '—')) ?></td>
            <td class="d-none d-md-table-cell small"><?= e((string) ($n['category_name'] ?? '—')) ?></td>
            <td><span class="badge text-bg-<?= e_attr($variant) ?>"><?= e($label) ?></span></td>
            <td><span class="kl-timer kl-timer--<?= e_attr($t['state']) ?>"><?= e($t['label']) ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-emerald" href="<?= e_attr(base_url('admin/verify.php?id=' . (int) $n['id'])) ?>">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <?php render_empty_state('Queue is clear', 'There are no submissions awaiting verification right now.', '🎉'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
