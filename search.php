<?php
/**
 * KhuntaLocal — Search (keyword + category/sort filters).
 * Date/location/language filters are extended in Phase 3.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeNav = '';
$q         = input('q');
$catId     = (int) input('category', '0');
$allowedSorts = ['latest', 'oldest', 'most_viewed', 'most_shared'];
$sort = input('sort', 'latest');
if (!in_array($sort, $allowedSorts, true)) { $sort = 'latest'; }

$categories = active_categories();
$catIds     = array_map('intval', array_column($categories, 'id'));
if ($catId > 0 && !in_array($catId, $catIds, true)) { $catId = 0; }

$filters = [];
if ($q !== '')    { $filters['search'] = $q; }
if ($catId > 0)   { $filters['category_id'] = $catId; }

$perPage = 12;
$page    = max(1, (int) input('page', '1'));
$total   = ($q !== '' || $catId > 0) ? news_count_published($filters) : 0;
$pages   = (int) ceil($total / $perPage);
$items   = ($q !== '' || $catId > 0) ? news_fetch_published($filters, $sort, $perPage, ($page - 1) * $perPage) : [];

$meta = ['title' => ($q !== '' ? 'Search: ' . $q : 'Search') . ' | ' . site_name(), 'robots' => 'noindex,follow'];
require __DIR__ . '/includes/partials/head.php';
?>
<h1 class="kl-section__title mb-3">Search news</h1>

<form method="get" action="<?= e_attr(base_url('search.php')) ?>" class="kl-form-card mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5">
      <label class="form-label" for="q">Keyword</label>
      <input class="form-control" id="q" name="q" value="<?= e_attr($q) ?>" placeholder="Search headlines & stories…">
    </div>
    <div class="col-8 col-md-4">
      <label class="form-label" for="category">Category</label>
      <select class="form-select" id="category" name="category">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label" for="sort">Sort</label>
      <select class="form-select" id="sort" name="sort">
        <option value="latest"      <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
        <option value="oldest"      <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
        <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most viewed</option>
        <option value="most_shared" <?= $sort === 'most_shared' ? 'selected' : '' ?>>Most shared</option>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button class="btn btn-emerald" type="submit">Go</button>
    </div>
  </div>
  <div class="form-text mt-2">More filters (date, location, language) arrive in Phase 3.</div>
</form>

<?php if ($q !== '' || $catId > 0): ?>
  <p class="text-muted-2"><?= e(number_format($total)) ?> result<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' for “' . e($q) . '”' : '' ?></p>
  <?php if ($items): ?>
    <div class="row g-3"><?php foreach ($items as $n) { render_news_card($n); } ?></div>
    <?php render_pagination($page, $pages, 'search.php', ['q' => $q, 'category' => $catId, 'sort' => $sort]); ?>
  <?php else: ?>
    <?php render_empty_state('No results', 'Try different keywords or clear the filters.', '🔍'); ?>
  <?php endif; ?>
<?php else: ?>
  <?php render_empty_state('Search local news', 'Enter a keyword above to find stories from Khunta and nearby areas.', '🔍'); ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
