<?php
/**
 * KhuntaLocal — News detail.
 * Basic Phase 1 view. Comments, gallery, video, save & report arrive later.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$slug = input('slug');
$news = $slug !== '' ? news_get_by_slug($slug) : null;

if (!$news) {
    http_response_code(404);
    $meta = ['title' => 'Story not found | ' . site_name(), 'robots' => 'noindex,follow'];
    require __DIR__ . '/includes/partials/head.php';
    render_empty_state('Story not found', 'This story may have been removed or is not yet published.', '🔍');
    echo '<div class="text-center mt-3"><a class="btn btn-emerald" href="' . e_attr(base_url()) . '">Back to home</a></div>';
    require __DIR__ . '/includes/partials/footer.php';
    exit;
}

$isPublished = $news['status'] === 'published';
if ($isPublished) {
    news_record_view((int) $news['id']);
}

$sources = news_sources_for((int) $news['id']);
$related = news_fetch_published(
    ['category_id' => (int) $news['category_id'], 'exclude_id' => (int) $news['id']],
    'latest',
    3
);

$meta = news_meta($news);
if (!$isPublished) {
    $meta['robots'] = 'noindex,nofollow';
}
require __DIR__ . '/includes/partials/head.php';

$breakingActive = !empty($news['is_breaking'])
    && (empty($news['breaking_expires_at']) || strtotime((string) $news['breaking_expires_at']) > time());
?>
<article class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-8">

    <!-- Owner/staff preview banner for non-published items -->
    <?php if (!$isPublished): ?>
      <?php [$label, $variant] = status_label((string) $news['status']); ?>
      <div class="alert alert-<?= e_attr($variant) ?> d-flex align-items-center gap-2">
        <span>👁️</span>
        <div>This is a preview. Current status: <strong><?= e($label) ?></strong>. It is not publicly visible yet.</div>
      </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="small mb-2">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= e_attr(base_url()) ?>">Home</a></li>
        <?php if (!empty($news['category_name'])): ?>
          <li class="breadcrumb-item"><a href="<?= e_attr(category_url((string) $news['category_slug'])) ?>"><?= e($news['category_name']) ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= e(str_excerpt($news['title'], 40)) ?></li>
      </ol>
    </nav>

    <!-- Badges -->
    <div class="d-flex flex-wrap gap-2 mb-2">
      <?php if (!empty($news['category_name'])): ?>
        <a class="kl-badge kl-badge--category" href="<?= e_attr(category_url((string) $news['category_slug'])) ?>">
          <?= e(($news['category_icon'] ? $news['category_icon'] . ' ' : '') . $news['category_name']) ?></a>
      <?php endif; ?>
      <?php if ($breakingActive): ?><span class="kl-badge kl-badge--breaking">● Breaking</span><?php endif; ?>
      <?php if (($news['priority'] ?? '') === 'urgent'): ?><span class="kl-badge kl-badge--urgent">Urgent</span><?php endif; ?>
    </div>

    <h1 class="fw-bold mb-3" style="letter-spacing:-.02em"><?= e($news['title']) ?></h1>

    <!-- Byline -->
    <div class="d-flex flex-wrap align-items-center gap-3 text-muted-2 small mb-3">
      <?php if (!empty($news['author_name'])): ?>
        <a class="d-inline-flex align-items-center gap-2 text-reset" href="<?= e_attr(reporter_url((string) $news['author_username'])) ?>">
          <span class="kl-logo-mark" style="width:28px;height:28px;font-size:.8rem;background:var(--kl-primary-light);color:var(--kl-primary-dark)"><?= e(mb_substr($news['author_name'], 0, 1)) ?></span>
          <span>By <?= e($news['author_name']) ?></span>
        </a>
      <?php endif; ?>
      <?php if (!empty($news['location_name'])): ?><span>📍 <?= e($news['location_name']) ?></span><?php endif; ?>
      <span>🕒 <?= e($isPublished ? time_ago($news['published_at']) : time_ago($news['created_at'])) ?></span>
      <span>👁 <?= e(format_count((int) $news['view_count'])) ?> views</span>
    </div>

    <!-- Hero image -->
    <div class="kl-card mb-4" style="box-shadow:none">
      <div class="kl-card__media" style="aspect-ratio:16/9">
        <?php
        $cover = $news['cover_path'] ?? null;
        if ($cover) {
            echo '<img src="' . e_attr(upload_url($cover)) . '" alt="' . e_attr($news['title']) . '">';
        } else {
            echo '<div class="kl-card__placeholder"><span>' . e($news['category_icon'] ?? '📰') . '</span></div>';
        }
        ?>
      </div>
    </div>

    <!-- Body -->
    <div class="kl-article-body mb-4">
      <?php
      $paras = preg_split('/\n\s*\n/', trim((string) $news['body'])) ?: [];
      foreach ($paras as $p) {
          $p = trim($p);
          if ($p !== '') {
              echo '<p>' . nl2br(e($p)) . '</p>';
          }
      }
      ?>
    </div>

    <!-- Verification label -->
    <?php if ($isPublished): ?>
      <div class="kl-verify-note mb-4">
        <span>✓</span>
        <div><strong>Reviewed by KhuntaLocal.</strong> This community report was checked by our review
          process before publication. We present local reporting transparently and do not claim absolute
          certainty about every detail.</div>
      </div>
    <?php endif; ?>

    <!-- Sources -->
    <section class="mb-4">
      <h2 class="kl-section__title h5 mb-2">Sources &amp; references</h2>
      <?php if ($sources): ?>
        <ul class="list-unstyled d-grid gap-2 m-0">
          <?php foreach ($sources as $s): ?>
            <li class="kl-card p-2 px-3" style="box-shadow:none">
              <?php if (!empty($s['url'])): ?>
                <a href="<?= e_attr($s['url']) ?>" target="_blank" rel="nofollow noopener noreferrer">
                  <?= e($s['label'] ?: $s['url']) ?> ↗
                </a>
              <?php else: ?>
                <span><?= e($s['label'] ?: '—') ?></span>
              <?php endif; ?>
              <?php if (!empty($s['note'])): ?><div class="small text-muted-2"><?= e($s['note']) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted-2 small mb-0">No external sources were provided for this community report.</p>
      <?php endif; ?>
    </section>

    <!-- Actions -->
    <div class="d-flex flex-wrap gap-2 mb-4">
      <button class="btn btn-emerald btn-sm" data-share
              data-share-title="<?= e_attr($news['title']) ?>"
              data-share-url="<?= e_attr(news_url((string) $news['slug'])) ?>">🔗 Share</button>
      <button class="btn btn-outline-emerald btn-sm" disabled title="Coming in a later update">♡ Save <span class="kl-soon ms-1">soon</span></button>
      <button class="btn btn-outline-emerald btn-sm" disabled title="Coming in a later update">⚑ Report <span class="kl-soon ms-1">soon</span></button>
    </div>

    <!-- Comments (Phase 3) -->
    <section class="mb-4">
      <h2 class="kl-section__title h5 mb-2">Comments</h2>
      <?php render_empty_state('Comments are coming soon', 'Readers will be able to discuss stories in a later update (Phase 3).', '💬'); ?>
    </section>

    <!-- Related -->
    <?php if ($related): ?>
      <section class="kl-section">
        <div class="kl-section__head"><h2 class="kl-section__title">Related stories</h2></div>
        <div class="row g-3">
          <?php foreach ($related as $r) { render_news_card($r); } ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</article>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
