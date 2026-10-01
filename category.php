<?php
/**
 * KhuntaLocal — Single category listing.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$slug = input('slug');
$category = $slug !== '' ? fetch('SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]) : null;

if (!$category) {
    http_response_code(404);
    $meta = ['title' => 'Category not found | ' . site_name(), 'robots' => 'noindex,follow'];
    require __DIR__ . '/includes/partials/head.php';
    render_empty_state('Category not found', 'This category does not exist.', '🗂️');
    require __DIR__ . '/includes/partials/footer.php';
    exit;
}

$allowedSorts = ['latest', 'oldest', 'most_viewed', 'most_shared'];
$sort = input('sort', 'latest');
if (!in_array($sort, $allowedSorts, true)) { $sort = 'latest'; }

$perPage = 12;
$page    = max(1, (int) input('page', '1'));
$filters = ['category_id' => (int) $category['id']];

$total = news_count_published($filters);
$pages = (int) ceil($total / $perPage);
$items = news_fetch_published($filters, $sort, $perPage, ($page - 1) * $perPage);

$activeNav = 'categories';
$meta = [
    'title'       => $category['name'] . ' news | ' . site_name(),
    'description' => 'Latest ' . $category['name'] . ' news from Khunta and Mayurbhanj on ' . site_name() . '.',
    'canonical'   => category_url((string) $category['slug']),
];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="kl-section__head">
  <h1 class="kl-section__title"><?= e(($category['icon'] ? $category['icon'] . ' ' : '') . $category['name']) ?></h1>
  <form method="get" class="d-flex align-items-center gap-2">
    <input type="hidden" name="slug" value="<?= e_attr($category['slug']) ?>">
    <label class="small text-muted-2 d-none d-sm-inline" for="sort">Sort</label>
    <select class="form-select form-select-sm" id="sort" name="sort" onchange="this.form.submit()" style="width:auto">
      <option value="latest"      <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
      <option value="oldest"      <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
      <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most viewed</option>
      <option value="most_shared" <?= $sort === 'most_shared' ? 'selected' : '' ?>>Most shared</option>
    </select>
  </form>
</div>

<?php if ($items): ?>
  <div class="row g-3">
    <?php foreach ($items as $n) { render_news_card($n); } ?>
  </div>
  <?php render_pagination($page, $pages, 'category.php', ['slug' => $category['slug'], 'sort' => $sort]); ?>
<?php else: ?>
  <?php render_empty_state('No news found', 'There are no published stories in this category yet.', '🗂️'); ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
