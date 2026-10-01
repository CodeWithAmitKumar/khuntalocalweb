<?php
/**
 * KhuntaLocal — All categories.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeNav  = 'categories';
$categories = active_categories();

// Published counts per category (single grouped query).
$counts = [];
foreach (fetch_all(
    "SELECT category_id, COUNT(*) AS c FROM news
      WHERE status = 'published' AND published_at <= NOW() GROUP BY category_id"
) as $row) {
    $counts[(int) $row['category_id']] = (int) $row['c'];
}

$meta = ['title' => 'Categories | ' . site_name(), 'description' => 'Browse local news by category on ' . site_name() . '.'];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="kl-section__head"><h1 class="kl-section__title">Browse categories</h1></div>

<?php if ($categories): ?>
  <div class="row g-3">
    <?php foreach ($categories as $c): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="kl-card h-100 p-3 text-reset d-flex flex-column gap-2" href="<?= e_attr(category_url((string) $c['slug'])) ?>">
          <span style="font-size:1.8rem"><?= e($c['icon'] ?: '📰') ?></span>
          <span class="fw-bold" style="color:var(--kl-charcoal)"><?= e($c['name']) ?></span>
          <span class="small text-muted-2"><?= e(format_count($counts[(int) $c['id']] ?? 0)) ?> stories</span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php render_empty_state('No categories yet', '', '🗂️'); ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
