<?php
/**
 * KhuntaLocal — Search (keyword + category/location/language/date/sort filters).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeNav = '';
$q         = input('q');
$catId     = (int) input('category', '0');
$locId     = (int) input('location', '0');
$lang      = input('language');
$dateFrom  = input('from');
$dateTo    = input('to');
$allowedSorts = ['latest', 'oldest', 'most_viewed', 'most_shared'];
$sort = input('sort', 'latest');
if (!in_array($sort, $allowedSorts, true)) { $sort = 'latest'; }

$categories = active_categories();
$locations  = active_locations();
$languages  = active_languages();
$catIds     = array_map('intval', array_column($categories, 'id'));
$locIds     = array_map('intval', array_column($locations, 'id'));
$langCodes  = array_column($languages, 'code');
if ($catId > 0 && !in_array($catId, $catIds, true)) { $catId = 0; }
if ($locId > 0 && !in_array($locId, $locIds, true)) { $locId = 0; }
if ($lang !== '' && !in_array($lang, $langCodes, true)) { $lang = ''; }
$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($dateRe, $dateFrom)) { $dateFrom = ''; }
if (!preg_match($dateRe, $dateTo))   { $dateTo = ''; }

$filters = [];
if ($q !== '')    { $filters['search'] = $q; }
if ($catId > 0)   { $filters['category_id'] = $catId; }
if ($locId > 0)   { $filters['location_id'] = $locId; }
if ($lang !== '') { $filters['language'] = $lang; }
if ($dateFrom !== '') { $filters['date_from'] = $dateFrom; }
if ($dateTo !== '')   { $filters['date_to'] = $dateTo; }

$hasQuery = $filters !== [];
$perPage = 12;
$page    = max(1, (int) input('page', '1'));
$total   = $hasQuery ? news_count_published($filters) : 0;
$pages   = (int) ceil($total / $perPage);
$items   = $hasQuery ? news_fetch_published($filters, $sort, $perPage, ($page - 1) * $perPage) : [];
$pageParams = array_filter([
    'q' => $q, 'category' => $catId ?: '', 'location' => $locId ?: '',
    'language' => $lang, 'from' => $dateFrom, 'to' => $dateTo, 'sort' => $sort,
], static fn($v) => $v !== '' && $v !== 0);

$meta = ['title' => ($q !== '' ? 'Search: ' . $q : 'Search') . ' | ' . site_name(), 'robots' => 'noindex,follow'];
require __DIR__ . '/includes/partials/head.php';
?>
<h1 class="kl-section__title mb-3">Search news</h1>

<form method="get" action="<?= e_attr(base_url('search.php')) ?>" class="kl-form-card mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label" for="q">Keyword</label>
      <input class="form-control" id="q" name="q" value="<?= e_attr($q) ?>" placeholder="Search headlines, stories & reporters…">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="category">Category</label>
      <select class="form-select" id="category" name="category">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="location">Location</label>
      <select class="form-select" id="location" name="location">
        <option value="0">All areas</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= (int) $loc['id'] ?>" <?= $locId === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="language">Language</label>
      <select class="form-select" id="language" name="language">
        <option value="">All languages</option>
        <?php foreach ($languages as $lng): ?>
          <option value="<?= e_attr($lng['code']) ?>" <?= $lang === $lng['code'] ? 'selected' : '' ?>><?= e($lng['native_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="from">From</label>
      <input type="date" class="form-control" id="from" name="from" value="<?= e_attr($dateFrom) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="to">To</label>
      <input type="date" class="form-control" id="to" name="to" value="<?= e_attr($dateTo) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="sort">Sort</label>
      <select class="form-select" id="sort" name="sort">
        <option value="latest"      <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
        <option value="oldest"      <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
        <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most viewed</option>
        <option value="most_shared" <?= $sort === 'most_shared' ? 'selected' : '' ?>>Most shared</option>
      </select>
    </div>
    <div class="col-6 col-md-1 d-grid align-self-end">
      <button class="btn btn-emerald" type="submit">Go</button>
    </div>
  </div>
  <?php if ($hasQuery): ?>
    <div class="mt-2"><a class="small text-muted-2" href="<?= e_attr(base_url('search.php')) ?>">✕ Clear filters</a></div>
  <?php endif; ?>
</form>

<?php if ($hasQuery): ?>
  <p class="text-muted-2"><?= e(number_format($total)) ?> result<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' for “' . e($q) . '”' : '' ?></p>
  <?php if ($items): ?>
    <div class="row g-3"><?php foreach ($items as $n) { render_news_card($n); } ?></div>
    <?php render_pagination($page, $pages, 'search.php', $pageParams); ?>
  <?php else: ?>
    <?php render_empty_state('No results', 'Try different keywords or clear the filters.', '🔍'); ?>
  <?php endif; ?>
<?php else: ?>
  <?php render_empty_state('Search local news', 'Enter a keyword above to find stories from Khunta and nearby areas.', '🔍'); ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
