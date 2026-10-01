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

$newsId  = (int) $news['id'];
$sources = news_sources_for($newsId);
$related = news_fetch_published(
    ['category_id' => (int) $news['category_id'], 'exclude_id' => $newsId],
    'latest',
    3
);

// Media (gallery = images other than the cover) + video.
$images    = news_media_for($newsId, 'image');
$videos    = news_media_for($newsId, 'video');
$coverId   = (int) ($news['cover_media_id'] ?? 0);
$gallery   = array_values(array_filter($images, static fn($m) => (int) $m['id'] !== $coverId));

// Comments + viewer engagement state.
$comments   = $isPublished ? comments_for_news($newsId) : [];
$uid        = auth_user_id();
$viewerLiked = $uid ? user_has_liked($newsId, $uid) : false;
$viewerSaved = $uid ? user_has_saved($newsId, $uid) : false;

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

    <!-- Video -->
    <?php if ($videos): ?>
      <section class="mb-4">
        <?php foreach ($videos as $vid): ?>
          <video class="w-100 rounded" style="max-height:460px;background:#000" controls preload="metadata">
            <source src="<?= e_attr(upload_url((string) $vid['path'])) ?>" type="<?= e_attr((string) ($vid['mime'] ?: 'video/mp4')) ?>">
            Your browser does not support the video tag.
          </video>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- Photo gallery -->
    <?php if ($gallery): ?>
      <section class="mb-4">
        <h2 class="kl-section__title h5 mb-2">Photos</h2>
        <div class="row g-2">
          <?php foreach ($gallery as $i => $g): ?>
            <div class="col-6 col-md-4">
              <a href="<?= e_attr(upload_url((string) $g['path'])) ?>" data-gallery="story" class="d-block"
                 style="aspect-ratio:1/1;overflow:hidden;border-radius:10px">
                <img src="<?= e_attr(upload_url((string) ($g['thumb_path'] ?: $g['path']))) ?>"
                     alt="<?= e_attr('Photo ' . ($i + 1)) ?>" loading="lazy"
                     style="width:100%;height:100%;object-fit:cover">
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

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
    <?php if ($isPublished): ?>
    <div class="d-flex flex-wrap gap-2 mb-4" data-engage="<?= (int) $newsId ?>">
      <button class="btn btn-sm <?= $viewerLiked ? 'btn-emerald' : 'btn-outline-emerald' ?>" data-like aria-pressed="<?= $viewerLiked ? 'true' : 'false' ?>">
        <span class="ic">♥</span> <span data-like-label><?= $viewerLiked ? 'Liked' : 'Like' ?></span>
        <span class="badge text-bg-light ms-1" data-like-count><?= e(format_count((int) $news['like_count'])) ?></span>
      </button>
      <button class="btn btn-sm <?= $viewerSaved ? 'btn-emerald' : 'btn-outline-emerald' ?>" data-save aria-pressed="<?= $viewerSaved ? 'true' : 'false' ?>">
        <span class="ic">🔖</span> <span data-save-label><?= $viewerSaved ? 'Saved' : 'Save' ?></span>
      </button>
      <button class="btn btn-outline-emerald btn-sm" data-share
              data-share-title="<?= e_attr($news['title']) ?>"
              data-share-url="<?= e_attr(news_url((string) $news['slug'])) ?>"
              data-share-id="<?= (int) $newsId ?>">🔗 Share</button>
      <?php if (is_logged_in()): ?>
        <button class="btn btn-outline-emerald btn-sm ms-auto text-danger" data-bs-toggle="modal" data-bs-target="#reportModal">⚑ Report</button>
      <?php else: ?>
        <a class="btn btn-outline-emerald btn-sm ms-auto text-danger" href="<?= e_attr(base_url('login.php')) ?>">⚑ Report</a>
      <?php endif; ?>
    </div>

    <!-- Comments -->
    <section class="mb-4" id="comments">
      <h2 class="kl-section__title h5 mb-3">Comments <span class="text-muted-2">(<?= e(format_count((int) $news['comment_count'])) ?>)</span></h2>

      <?php if (is_logged_in()): ?>
        <form method="post" action="<?= e_attr(base_url('comment.php')) ?>" class="kl-card p-3 mb-3" style="box-shadow:none">
          <?= csrf_field() ?>
          <input type="hidden" name="news_id" value="<?= (int) $newsId ?>">
          <input type="hidden" name="action" value="add">
          <textarea class="form-control mb-2" name="body" rows="3" required maxlength="5000" placeholder="Share your thoughts respectfully…"></textarea>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted-2"><?= setting_bool('comment_moderation', true) ? 'Comments are reviewed before appearing.' : 'Be kind and factual.' ?></small>
            <button class="btn btn-emerald btn-sm" type="submit">Post comment</button>
          </div>
        </form>
      <?php else: ?>
        <div class="kl-card p-3 mb-3 text-center" style="box-shadow:none">
          <a class="btn btn-emerald btn-sm" href="<?= e_attr(base_url('login.php')) ?>">Log in to comment</a>
        </div>
      <?php endif; ?>

      <?php if ($comments): ?>
        <div class="d-grid gap-3">
          <?php foreach ($comments as $c) { render_comment($c, $newsId); } ?>
        </div>
      <?php else: ?>
        <?php render_empty_state('No comments yet', 'Be the first to share your thoughts.', '💬'); ?>
      <?php endif; ?>
    </section>
    <?php endif; /* isPublished actions+comments */ ?>

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

<?php if ($isPublished && is_logged_in()): ?>
<!-- Report modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--kl-radius)">
      <form method="post" action="<?= e_attr(base_url('report.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="news_id" value="<?= (int) $newsId ?>">
        <div class="modal-header">
          <h5 class="modal-title">Report this story</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted-2 small">Tell us what's wrong. Our moderators review every report.</p>
          <div class="mb-3">
            <label class="form-label" for="report_reason">Reason</label>
            <select class="form-select" id="report_reason" name="reason" required>
              <option value="false_information">False information</option>
              <option value="duplicate">Duplicate</option>
              <option value="offensive">Offensive content</option>
              <option value="copyright">Copyright issue</option>
              <option value="spam">Spam</option>
              <option value="wrong_information">Wrong information</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label" for="report_note">Details <span class="text-muted-2 fw-normal">(optional)</span></label>
            <textarea class="form-control" id="report_note" name="note" rows="3" maxlength="1000"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-emerald" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Submit report</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
