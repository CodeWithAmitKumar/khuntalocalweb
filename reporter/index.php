<?php
/**
 * KhuntaLocal — Reporter dashboard.
 * Richer than the profile "My submissions" list: stat cards + full table with
 * verification status, review time and per-item actions.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

$stats = reporter_history((int) $user['id']);

$perPage = 15;
$page    = max(1, (int) input('page', '1'));
$total   = (int) fetch_column('SELECT COUNT(*) FROM news WHERE user_id = ?', [(int) $user['id']], 0);
$pages   = (int) ceil($total / $perPage);
$rows    = fetch_all(
    'SELECT n.id, n.slug, n.title, n.status, n.submitted_at, n.reviewed_at, n.rejection_reason,
            c.name AS category_name
       FROM news n LEFT JOIN categories c ON c.id = n.category_id
      WHERE n.user_id = ?
      ORDER BY n.created_at DESC
      LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    [(int) $user['id']]
);

$editable = ['draft', 'pending', 'needs_information'];

$activeNav = 'profile';
$meta = ['title' => 'Reporter dashboard | ' . site_name(), 'robots' => 'noindex,nofollow'];
require KL_INCLUDES . '/partials/head.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h3 fw-bold mb-1">Reporter dashboard</h1>
    <p class="text-muted-2 mb-0">Track your submissions and their verification status.</p>
  </div>
  <a href="<?= e_attr(base_url('submit-news.php')) ?>" class="btn btn-emerald">＋ Submit news</a>
</div>

<div class="row g-3 mb-4">
  <?php
  $tiles = [
      ['Total', $stats['total'], ''],
      ['Published', $stats['published'], 'accent'],
      ['Pending', $stats['pending'], 'warn'],
      ['Under review', $stats['under_review'], ''],
      ['Needs info', $stats['needs_information'], 'warn'],
      ['Rejected', $stats['rejected'], $stats['rejected'] > 0 ? 'danger' : ''],
  ];
  foreach ($tiles as [$label, $num, $variant]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="kl-stat <?= $variant ? 'kl-stat--' . e_attr($variant) : '' ?>">
        <div class="kl-stat__num"><?= e((string) $num) ?></div>
        <div class="kl-stat__label"><?= e($label) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="kl-section__head"><h2 class="kl-section__title">Your submissions</h2></div>
<?php if ($rows): ?>
  <div class="table-responsive kl-card" style="box-shadow:none">
    <table class="table align-middle mb-0">
      <thead><tr class="small text-muted-2">
        <th>Headline</th><th class="d-none d-md-table-cell">Category</th><th>Status</th>
        <th class="d-none d-md-table-cell">Submitted</th><th class="d-none d-lg-table-cell">Reviewed</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): [$label, $variant] = status_label((string) $r['status']); ?>
          <tr>
            <td class="fw-semibold" style="max-width:300px">
              <?= e(str_excerpt((string) $r['title'], 64)) ?>
              <?php if ($r['status'] === 'needs_information' && !empty($r['rejection_reason'])): ?>
                <div class="small text-warning">ℹ <?= e(str_excerpt((string) $r['rejection_reason'], 90)) ?></div>
              <?php elseif ($r['status'] === 'rejected' && !empty($r['rejection_reason'])): ?>
                <div class="small text-danger">✕ <?= e(str_excerpt((string) $r['rejection_reason'], 90)) ?></div>
              <?php endif; ?>
            </td>
            <td class="d-none d-md-table-cell small"><?= e((string) ($r['category_name'] ?? '—')) ?></td>
            <td><span class="badge text-bg-<?= e_attr($variant) ?>"><?= e($label) ?></span></td>
            <td class="d-none d-md-table-cell small text-muted-2"><?= e($r['submitted_at'] ? time_ago($r['submitted_at']) : '—') ?></td>
            <td class="d-none d-lg-table-cell small text-muted-2"><?= e($r['reviewed_at'] ? time_ago($r['reviewed_at']) : '—') ?></td>
            <td class="text-end">
              <?php if ($r['status'] === 'published'): ?>
                <a class="btn btn-sm btn-outline-emerald" href="<?= e_attr(news_url((string) $r['slug'])) ?>">View</a>
              <?php endif; ?>
              <?php if (in_array($r['status'], $editable, true)): ?>
                <a class="btn btn-sm btn-emerald" href="<?= e_attr(base_url('reporter/edit.php?id=' . (int) $r['id'])) ?>">Edit</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php render_pagination($page, $pages, 'reporter/index.php'); ?>
<?php else: ?>
  <?php render_empty_state('No submissions yet', 'Share your first local story — it only takes a minute.', '📝'); ?>
<?php endif; ?>
<?php require KL_INCLUDES . '/partials/footer.php'; ?>
