<?php
/**
 * KhuntaLocal — Homepage.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeNav = 'home';

// --- Data ------------------------------------------------------------------
$breaking = news_fetch_published(['is_breaking' => true], 'latest', 6);

$featuredList = news_fetch_published(['is_featured' => true], 'latest', 1);
$featured     = $featuredList[0] ?? null;

$excludeId = $featured ? (int) $featured['id'] : 0;

$latest = news_fetch_published($excludeId ? ['exclude_id' => $excludeId] : [], 'latest', 6);
if (!$featured && $latest) {
    // No explicit featured story — promote the newest one.
    $featured = array_shift($latest);
    $excludeId = (int) $featured['id'];
    $latest = news_fetch_published(['exclude_id' => $excludeId], 'latest', 6);
}

$trending   = news_fetch_published(['trending_days' => 14], 'trending', 5);
$mostViewed = news_fetch_published([], 'most_viewed', 5);
$photoItems = array_values(array_filter(
    news_fetch_published([], 'latest', 12),
    static fn($n) => !empty($n['cover_path'])
));

$cats = active_categories();

require __DIR__ . '/includes/partials/head.php';
?>

<!-- Breaking strip -->
<?php if ($breaking): ?>
  <div class="kl-breaking mb-3">
    <span class="kl-breaking__tag">● BREAKING</span>
    <div class="kl-breaking__items">
      <?php foreach ($breaking as $b): ?>
        <a href="<?= e_attr(news_url($b['slug'])) ?>"><?= e($b['title']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Category chips -->
<?php if ($cats): ?>
  <div class="kl-chip-row mb-2" aria-label="Categories">
    <a class="kl-chip" href="<?= e_attr(base_url('latest.php')) ?>">🏠 All</a>
    <?php foreach ($cats as $c): ?>
      <a class="kl-chip" href="<?= e_attr(category_url($c['slug'])) ?>">
        <?= e(($c['icon'] ? $c['icon'] . ' ' : '') . $c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Featured story -->
<?php if ($featured): ?>
  <section class="kl-section mt-3" aria-label="Featured story">
    <div class="row g-3">
      <?php render_news_card($featured, ['feature' => true, 'class' => 'col-12']); ?>
    </div>
  </section>
<?php endif; ?>

<!-- Latest news -->
<section class="kl-section">
  <div class="kl-section__head">
    <h2 class="kl-section__title">Latest news</h2>
    <a href="<?= e_attr(base_url('latest.php')) ?>" class="btn btn-soft btn-sm">View all</a>
  </div>
  <div class="row g-3">
    <?php if ($latest): ?>
      <?php foreach ($latest as $n) { render_news_card($n); } ?>
    <?php else: ?>
      <div class="col-12"><?php render_empty_state('No news yet', 'Be the first to report something happening in your area.', '📰'); ?></div>
    <?php endif; ?>
  </div>
</section>

<div class="row g-4 mt-1">
  <!-- Trending -->
  <div class="col-12 col-lg-6">
    <section class="kl-section mt-0">
      <div class="kl-section__head"><h2 class="kl-section__title">🔥 Trending</h2></div>
      <?php if ($trending): ?>
        <ol class="list-unstyled d-grid gap-2 m-0">
          <?php foreach ($trending as $i => $t): ?>
            <li>
              <a class="d-flex align-items-center gap-3 p-2 rounded kl-card" style="box-shadow:none" href="<?= e_attr(news_url($t['slug'])) ?>">
                <span class="fw-bold text-emerald fs-5" style="min-width:28px"><?= (int) ($i + 1) ?></span>
                <span>
                  <span class="fw-semibold d-block" style="color:var(--kl-charcoal)"><?= e(str_excerpt($t['title'], 80)) ?></span>
                  <span class="small text-muted-2">👁 <?= e(format_count((int) $t['view_count'])) ?> · <?= e(time_ago($t['published_at'])) ?></span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <?php render_empty_state('Nothing trending yet', 'Trending stories appear as readers engage.', '🔥'); ?>
      <?php endif; ?>
    </section>
  </div>

  <!-- Most viewed -->
  <div class="col-12 col-lg-6">
    <section class="kl-section mt-0">
      <div class="kl-section__head"><h2 class="kl-section__title">👁 Most viewed</h2></div>
      <?php if ($mostViewed): ?>
        <ol class="list-unstyled d-grid gap-2 m-0">
          <?php foreach ($mostViewed as $i => $t): ?>
            <li>
              <a class="d-flex align-items-center gap-3 p-2 rounded kl-card" style="box-shadow:none" href="<?= e_attr(news_url($t['slug'])) ?>">
                <span class="fw-bold text-muted-2 fs-5" style="min-width:28px"><?= (int) ($i + 1) ?></span>
                <span>
                  <span class="fw-semibold d-block" style="color:var(--kl-charcoal)"><?= e(str_excerpt($t['title'], 80)) ?></span>
                  <span class="small text-muted-2">📍 <?= e($t['location_name'] ?? '—') ?> · <?= e(time_ago($t['published_at'])) ?></span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <?php render_empty_state('No stories yet', '', '👁'); ?>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- Photo stories -->
<section class="kl-section">
  <div class="kl-section__head"><h2 class="kl-section__title">📸 Photo stories</h2></div>
  <?php if ($photoItems): ?>
    <div class="row g-3">
      <?php foreach (array_slice($photoItems, 0, 3) as $n) { render_news_card($n); } ?>
    </div>
  <?php else: ?>
    <?php render_empty_state('Photo stories coming soon', 'Reporters can attach a cover photo when submitting. Full photo galleries arrive in a later update.', '📸'); ?>
  <?php endif; ?>
</section>

<!-- Video news -->
<?php $videoItems = news_fetch_published(['has_video' => true], 'latest', 6); ?>
<section class="kl-section">
  <div class="kl-section__head">
    <h2 class="kl-section__title">🎬 Video news</h2>
    <?php if ($videoItems): ?><a href="<?= e_attr(base_url('latest.php?media=video')) ?>" class="btn btn-soft btn-sm">View all</a><?php endif; ?>
  </div>
  <?php if ($videoItems): ?>
    <div class="row g-3">
      <?php foreach (array_slice($videoItems, 0, 3) as $n) { render_news_card($n); } ?>
    </div>
  <?php else: ?>
    <?php render_empty_state('No video stories yet', 'Reporters can attach a video when submitting a story.', '🎬'); ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
