<?php
/**
 * KhuntaLocal — Latest / Trending / Photos / Videos listing.
 * Advanced search & filters (date, location, language) arrive in Phase 3.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$media = input('media'); // '', 'photo', 'video'
$allowedSorts = ['latest', 'oldest', 'most_viewed', 'most_shared', 'trending'];
$sort = input('sort', 'latest');
if (!in_array($sort, $allowedSorts, true)) { $sort = 'latest'; }

$titles = [
    'latest'      => 'Latest news',
    'oldest'      => 'Oldest first',
    'most_viewed' => 'Most viewed',
    'most_shared' => 'Most shared',
    'trending'    => 'Trending',
];
$heading = $titles[$sort] ?? 'Latest news';
$activeNav = $sort === 'trending' ? 'trending' : 'latest';

if ($media === 'video') {
    $heading   = 'Video news';
    $activeNav = 'videos';
} elseif ($media === 'photo') {
    $heading   = 'Photo stories';
    $activeNav = 'photos';
}

$meta = ['title' => $heading . ' | ' . site_name()];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="kl-section__head">
  <h1 class="kl-section__title"><?= e($heading) ?></h1>
  <?php if ($media === ''): ?>
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="small text-muted-2 d-none d-sm-inline" for="sort">Sort</label>
      <select class="form-select form-select-sm" id="sort" name="sort" onchange="this.form.submit()" style="width:auto">
        <option value="latest"      <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
        <option value="trending"    <?= $sort === 'trending' ? 'selected' : '' ?>>Trending</option>
        <option value="oldest"      <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
        <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most viewed</option>
        <option value="most_shared" <?= $sort === 'most_shared' ? 'selected' : '' ?>>Most shared</option>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php
if ($media === 'video') {
    $perPage = 12;
    $page    = max(1, (int) input('page', '1'));
    $total   = news_count_published(['has_video' => true]);
    $pages   = (int) ceil($total / $perPage);
    $items   = news_fetch_published(['has_video' => true], 'latest', $perPage, ($page - 1) * $perPage);
    if ($items) {
        echo '<div class="row g-3">';
        foreach ($items as $n) { render_news_card($n); }
        echo '</div>';
        render_pagination($page, $pages, 'latest.php', ['media' => 'video']);
    } else {
        render_empty_state('No video stories yet', 'Stories with a video will appear here.', '🎬');
    }
} elseif ($media === 'photo') {
    $perPage = 12;
    $page    = max(1, (int) input('page', '1'));
    $total   = news_count_published(['has_gallery' => true]);
    $pages   = (int) ceil($total / $perPage);
    $items   = news_fetch_published(['has_gallery' => true], 'latest', $perPage, ($page - 1) * $perPage);
    if ($items) {
        echo '<div class="row g-3">';
        foreach ($items as $n) { render_news_card($n); }
        echo '</div>';
        render_pagination($page, $pages, 'latest.php', ['media' => 'photo']);
    } else {
        render_empty_state('No photo stories yet', 'Stories with photos will appear here.', '📸');
    }
} else {
    $perPage = 12;
    $page    = max(1, (int) input('page', '1'));
    $filters = $sort === 'trending' ? ['trending_days' => 30] : [];
    $total   = news_count_published($filters);
    $pages   = (int) ceil($total / $perPage);
    $items   = news_fetch_published($filters, $sort, $perPage, ($page - 1) * $perPage);

    if ($items) {
        echo '<div class="row g-3">';
        foreach ($items as $n) { render_news_card($n); }
        echo '</div>';
        render_pagination($page, $pages, 'latest.php', ['sort' => $sort]);
    } else {
        render_empty_state('No news found', 'Try a different filter, or check back soon.', '📰');
    }
}
?>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
