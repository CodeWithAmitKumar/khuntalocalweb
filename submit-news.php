<?php
/**
 * KhuntaLocal — Submit news (multi-step, login required).
 * Phase 1: text fields + sources + optional cover image. Video & photo
 * galleries arrive in Phase 3.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
old_load();
$errors = [];

$categories = active_categories();
$locations  = active_locations();
$languages  = active_languages();
$catIds     = array_map('intval', array_column($categories, 'id'));
$locIds     = array_map('intval', array_column($locations, 'id'));
$langCodes  = array_column($languages, 'code') ?: ['en'];

if (is_post()) {
    csrf_check();

    if (!rate_limit_hit('submit_news', rate_limit_key((int) $user['id']), 10, 3600)) {
        flash_set('error', 'You have submitted several stories recently. Please try again a little later.');
        old_flash($_POST);
        redirect('submit-news.php');
    }

    $title   = input('title');
    $catId   = (int) input('category_id', '0');
    $locId   = (int) input('location_id', '0');
    $lang    = input('language_code', 'en');
    $summary = input('summary');
    $body    = trim((string) ($_POST['body'] ?? ''));
    $srcLabel = input('source_label');
    $srcUrl   = input('source_url');

    $v = new Validator($_POST);
    $v->required('title', 'Headline')->min('title', 8)->max('title', 200);
    $v->in('category_id', $catIds, 'Category');
    if ($catId <= 0) { $v->add('category_id', 'Please choose a category.'); }
    $v->in('location_id', $locIds, 'Location');
    if ($locId <= 0) { $v->add('location_id', 'Please choose a location.'); }
    $v->in('language_code', $langCodes, 'Language');
    $v->required('body', 'Description')->min('body', 30)->max('body', 20000);
    if ($summary !== '') { $v->max('summary', 500, 'Short summary'); }
    if ($srcUrl !== '') { $v->url('source_url', 'Source URL')->max('source_url', 500); }
    if ($srcLabel !== '') { $v->max('source_label', 200, 'Source label'); }

    if ($v->fails()) {
        $errors = $v->errors();
        old_flash($_POST);
    } else {
        $mediaErrors = [];
        try {
            $newsId = db_transaction(function () use ($user, $title, $catId, $locId, $lang, $summary, $body, $srcLabel, $srcUrl, &$mediaErrors): int {
                $slug = unique_slug($title, static function (string $s): bool {
                    return (bool) fetch_column('SELECT 1 FROM news WHERE slug = ? LIMIT 1', [$s]);
                }, 200);

                $newsId = db_insert('news', [
                    'uuid'          => uuid4(),
                    'slug'          => $slug,
                    'user_id'       => (int) $user['id'],
                    'category_id'   => $catId,
                    'location_id'   => $locId,
                    'language_code' => $lang,
                    'title'         => $title,
                    'summary'       => $summary !== '' ? $summary : str_excerpt($body, 240),
                    'body'          => $body,
                    'status'        => 'pending',
                    'priority'      => 'normal',
                    'risk_level'    => 'unknown',
                    'submitted_at'  => date('Y-m-d H:i:s'),
                ]);

                // Optional media: photos (first becomes cover) + a video.
                $m = store_news_media($newsId, [
                    'photos' => $_FILES['photos'] ?? null,
                    'video'  => $_FILES['video'] ?? null,
                ]);
                $mediaErrors = $m['errors'];

                // Optional source/reference.
                if ($srcLabel !== '' || $srcUrl !== '') {
                    db_insert('news_sources', [
                        'news_id' => $newsId,
                        'label'   => $srcLabel !== '' ? $srcLabel : null,
                        'url'     => $srcUrl !== '' ? $srcUrl : null,
                    ]);
                }

                // Verification stub (automated engine fills this in Phase 4).
                db_insert('news_verification', [
                    'news_id'    => $newsId,
                    'risk_level' => 'unknown',
                ]);

                verification_log($newsId, 'SUBMITTED', [
                    'actor_type' => 'reporter',
                    'admin_id'   => (int) $user['id'],
                    'new_status' => 'pending',
                    'note'       => 'Submitted by reporter; awaiting verification.',
                ]);

                return $newsId;
            });

            // Notifications (reporter + verification admins).
            notify((int) $user['id'], 'news.submitted', 'Your story was submitted',
                'It is now awaiting verification. We will let you know once it is reviewed.',
                ['news_id' => $newsId]);
            notify_staff('news.verify', 'news.new_submission', 'New submission awaiting review',
                $title, ['news_id' => $newsId]);

            flash_set('success', 'Thank you! Your story has been submitted and is awaiting verification.');
            foreach ($mediaErrors as $me) {
                flash_set('warning', 'Media note: ' . $me);
            }
            redirect('profile.php#submissions');
        } catch (Throwable $ex) {
            error_log('news submission failed: ' . $ex->getMessage());
            flash_set('error', 'Something went wrong submitting your story. Please try again.');
            old_flash($_POST);
            redirect('submit-news.php');
        }
    }
}

$meta = ['title' => 'Submit news | ' . site_name(), 'robots' => 'noindex,nofollow'];
$activeNav = 'submit';
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-8">
    <div class="mb-3">
      <h1 class="h3 fw-bold mb-1">Submit local news</h1>
      <p class="text-muted-2 mb-0">Share what's happening around Khunta. Your story is reviewed before it goes live.</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">Please review the highlighted fields and try again.</div>
    <?php endif; ?>

    <!-- Step indicator (JS) -->
    <div class="kl-steps">
      <div class="kl-step is-active"><div class="kl-step__dot">1</div>Basics</div>
      <div class="kl-step"><div class="kl-step__dot">2</div>Details</div>
      <div class="kl-step"><div class="kl-step__dot">3</div>Media</div>
      <div class="kl-step"><div class="kl-step__dot">4</div>Review</div>
    </div>

    <div class="kl-form-card">
      <form method="post" action="<?= e_attr(base_url('submit-news.php')) ?>" enctype="multipart/form-data" data-multistep novalidate>
        <?= csrf_field() ?>

        <!-- Step 1: Basics -->
        <section class="kl-step-panel is-active">
          <h2 class="h5 mb-3">Step 1 — Basics</h2>
          <div class="mb-3">
            <label class="form-label" for="title">Headline</label>
            <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                   id="title" name="title" required maxlength="200" value="<?= e_attr(old('title')) ?>"
                   placeholder="e.g. Road construction begins near Khunta market">
            <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label" for="category_id">Category</label>
              <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                <option value="">Choose…</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === old('category_id') ? 'selected' : '' ?>>
                    <?= e(($c['icon'] ? $c['icon'] . ' ' : '') . $c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label" for="language_code">Language</label>
              <select class="form-select" id="language_code" name="language_code" required>
                <?php foreach ($languages as $lng): ?>
                  <option value="<?= e_attr($lng['code']) ?>" <?= old('language_code', 'en') === $lng['code'] ? 'selected' : '' ?>>
                    <?= e($lng['native_name'] . ' (' . $lng['name'] . ')') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label" for="location_id">Location</label>
              <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id" required>
                <option value="">Choose…</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= (int) $loc['id'] ?>" <?= (string) $loc['id'] === old('location_id') ? 'selected' : '' ?>>
                    <?= e($loc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['location_id'])): ?><div class="invalid-feedback"><?= e($errors['location_id']) ?></div><?php endif; ?>
            </div>
          </div>
          <div class="kl-step-nav justify-content-end mt-4">
            <button type="button" class="btn btn-emerald" data-step-next>Continue</button>
          </div>
        </section>

        <!-- Step 2: Details -->
        <section class="kl-step-panel">
          <h2 class="h5 mb-3">Step 2 — Details</h2>
          <div class="mb-3">
            <label class="form-label" for="summary">Short summary <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control <?= isset($errors['summary']) ? 'is-invalid' : '' ?>"
                   id="summary" name="summary" maxlength="500" value="<?= e_attr(old('summary')) ?>"
                   placeholder="One line that appears on the news card">
            <?php if (isset($errors['summary'])): ?><div class="invalid-feedback"><?= e($errors['summary']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="body">Description</label>
            <textarea class="form-control <?= isset($errors['body']) ? 'is-invalid' : '' ?>"
                      id="body" name="body" rows="8" required
                      placeholder="Describe what happened — where, when, and what you saw."><?= e(old('body')) ?></textarea>
            <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?= e($errors['body']) ?></div>
            <?php else: ?><div class="form-text">Be factual and specific. Avoid rumours; add a source below if you have one.</div><?php endif; ?>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-5">
              <label class="form-label" for="source_label">Source / reference <span class="text-muted-2 fw-normal">(optional)</span></label>
              <input class="form-control <?= isset($errors['source_label']) ? 'is-invalid' : '' ?>"
                     id="source_label" name="source_label" maxlength="200" value="<?= e_attr(old('source_label')) ?>"
                     placeholder="e.g. Local resident, official notice">
              <?php if (isset($errors['source_label'])): ?><div class="invalid-feedback"><?= e($errors['source_label']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-7">
              <label class="form-label" for="source_url">Source link <span class="text-muted-2 fw-normal">(optional)</span></label>
              <input class="form-control <?= isset($errors['source_url']) ? 'is-invalid' : '' ?>"
                     id="source_url" name="source_url" maxlength="500" value="<?= e_attr(old('source_url')) ?>"
                     placeholder="https://…">
              <?php if (isset($errors['source_url'])): ?><div class="invalid-feedback"><?= e($errors['source_url']) ?></div><?php endif; ?>
            </div>
          </div>
          <div class="kl-step-nav justify-content-between mt-4">
            <button type="button" class="btn btn-outline-emerald" data-step-prev>Back</button>
            <button type="button" class="btn btn-emerald" data-step-next>Continue</button>
          </div>
        </section>

        <!-- Step 3: Media -->
        <section class="kl-step-panel">
          <h2 class="h5 mb-3">Step 3 — Media</h2>
          <div class="mb-3">
            <label class="form-label" for="photos">Photos <span class="text-muted-2 fw-normal">(optional, up to 8 — first is the cover)</span></label>
            <input class="form-control" type="file" id="photos" name="photos[]" multiple
                   accept="image/jpeg,image/png,image/webp" data-image-preview="#photo-preview">
            <div class="form-text">JPG, PNG or WEBP, up to <?= e((string) round(setting_int('max_image_size', 5242880) / 1048576, 1)) ?> MB each.</div>
            <div id="photo-preview" class="mt-3 d-flex flex-wrap gap-2"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="video">Video <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control" type="file" id="video" name="video" accept="video/mp4,video/webm">
            <div class="form-text">MP4 or WEBM, up to <?= e((string) round(setting_int('max_video_size', 52428800) / 1048576, 1)) ?> MB.</div>
          </div>
          <div class="kl-step-nav justify-content-between mt-4">
            <button type="button" class="btn btn-outline-emerald" data-step-prev>Back</button>
            <button type="button" class="btn btn-emerald" data-step-next>Continue</button>
          </div>
        </section>

        <!-- Step 4: Review -->
        <section class="kl-step-panel">
          <h2 class="h5 mb-3">Step 4 — Review &amp; submit</h2>
          <div class="kl-card p-3 mb-3" style="box-shadow:none">
            <div data-preview class="text-muted-2">Your preview will appear here.</div>
          </div>
          <div class="kl-verify-note mb-3">
            <span>🛡️</span>
            <div>Your story goes through a verification review before publication. We collect evidence and
              flag concerns for a human reviewer — we never claim an automated system can prove a story is
              absolutely true.</div>
          </div>
          <div class="kl-step-nav justify-content-between mt-2">
            <button type="button" class="btn btn-outline-emerald" data-step-prev>Back</button>
          </div>
          <button type="submit" class="btn btn-emerald w-100 mt-3">Submit for verification</button>
        </section>

      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
