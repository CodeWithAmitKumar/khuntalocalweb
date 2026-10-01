<?php
/**
 * KhuntaLocal — Edit a submission (owner only, eligible statuses).
 * Editing a "needs_information" item and saving resubmits it for review.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id   = (int) input('id', '0');

$news = $id > 0 ? fetch('SELECT * FROM news WHERE id = ? LIMIT 1', [$id]) : null;
if (!$news || (int) $news['user_id'] !== (int) $user['id']) {
    http_response_code(404);
    $meta = ['title' => 'Not found | ' . site_name(), 'robots' => 'noindex,nofollow'];
    require KL_INCLUDES . '/partials/head.php';
    render_empty_state('Submission not found', 'You can only edit your own submissions.', '🔍');
    require KL_INCLUDES . '/partials/footer.php';
    exit;
}

$editable = ['draft', 'pending', 'needs_information'];
if (!in_array($news['status'], $editable, true)) {
    flash_set('info', 'This story can no longer be edited (current status: ' . status_label((string) $news['status'])[0] . ').');
    redirect('reporter/index.php');
}

$categories = active_categories();
$locations  = active_locations();
$languages  = active_languages();
$catIds     = array_map('intval', array_column($categories, 'id'));
$locIds     = array_map('intval', array_column($locations, 'id'));
$langCodes  = array_column($languages, 'code') ?: ['en'];

$firstSource = fetch('SELECT * FROM news_sources WHERE news_id = ? ORDER BY id LIMIT 1', [$id]);
$errors = [];

if (is_post()) {
    csrf_check();

    $title    = input('title');
    $catId    = (int) input('category_id', '0');
    $locId    = (int) input('location_id', '0');
    $lang     = input('language_code', (string) $news['language_code']);
    $summary  = input('summary');
    $body     = trim((string) ($_POST['body'] ?? ''));
    $srcLabel = input('source_label');
    $srcUrl   = input('source_url');

    $v = new Validator($_POST);
    $v->required('title', 'Headline')->min('title', 8)->max('title', 200);
    $v->in('category_id', $catIds, 'Category'); if ($catId <= 0) $v->add('category_id', 'Please choose a category.');
    $v->in('location_id', $locIds, 'Location'); if ($locId <= 0) $v->add('location_id', 'Please choose a location.');
    $v->in('language_code', $langCodes, 'Language');
    $v->required('body', 'Description')->min('body', 30)->max('body', 20000);
    if ($summary !== '') $v->max('summary', 500, 'Short summary');
    if ($srcUrl !== '')  $v->url('source_url', 'Source URL')->max('source_url', 500);

    $coverData = null;
    if (!empty($_FILES['cover']['name'])) {
        $res = upload_image($_FILES['cover'], 'news', ['thumb_width' => 640]);
        if (!$res['ok']) $v->add('cover', $res['error'] ?? 'The cover image could not be uploaded.');
        else $coverData = $res['data'];
    }

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            $resubmit = in_array($news['status'], ['needs_information', 'draft'], true);
            db_transaction(function () use ($id, $title, $catId, $locId, $lang, $summary, $body, $srcLabel, $srcUrl, $coverData, $firstSource, $resubmit) {
                $update = [
                    'title'         => $title,
                    'category_id'   => $catId,
                    'location_id'   => $locId,
                    'language_code' => $lang,
                    'summary'       => $summary !== '' ? $summary : str_excerpt($body, 240),
                    'body'          => $body,
                ];
                if ($resubmit) {
                    $update['status']       = 'pending';
                    $update['submitted_at'] = date('Y-m-d H:i:s');
                }
                db_update('news', $update, ['id' => $id]);

                // Cover replacement.
                if ($coverData) {
                    $mediaId = db_insert('news_media', [
                        'news_id' => $id, 'type' => 'image',
                        'path' => $coverData['path'], 'thumb_path' => $coverData['thumb_path'],
                        'mime' => $coverData['mime'], 'size_bytes' => $coverData['size'],
                        'width' => $coverData['width'], 'height' => $coverData['height'],
                        'original_name' => $coverData['original_name'], 'sort_order' => 0,
                    ]);
                    db_update('news', ['cover_media_id' => $mediaId], ['id' => $id]);
                }

                // Source upsert (single source in Phase 1/2).
                if ($srcLabel !== '' || $srcUrl !== '') {
                    if ($firstSource) {
                        db_update('news_sources', ['label' => $srcLabel ?: null, 'url' => $srcUrl ?: null], ['id' => (int) $firstSource['id']]);
                    } else {
                        db_insert('news_sources', ['news_id' => $id, 'label' => $srcLabel ?: null, 'url' => $srcUrl ?: null]);
                    }
                } elseif ($firstSource) {
                    db_run('DELETE FROM news_sources WHERE id = ?', [(int) $firstSource['id']]);
                }

                if ($resubmit) {
                    verification_log($id, 'RESUBMITTED', [
                        'actor_type' => 'reporter', 'admin_id' => auth_user_id(),
                        'new_status' => 'pending', 'note' => 'Reporter updated and resubmitted.',
                    ]);
                } else {
                    verification_log($id, 'EDITED', [
                        'actor_type' => 'reporter', 'admin_id' => auth_user_id(),
                        'note' => 'Reporter edited the submission.',
                    ]);
                }
            });

            if (in_array($news['status'], ['needs_information', 'draft'], true)) {
                notify_staff('news.verify', 'news.resubmitted', 'A submission was updated and resubmitted', $title, ['news_id' => $id]);
                flash_set('success', 'Your updated story has been resubmitted for verification.');
            } else {
                flash_set('success', 'Your submission has been updated.');
            }
            redirect('reporter/index.php');
        } catch (Throwable $ex) {
            error_log('edit submission failed: ' . $ex->getMessage());
            flash_set('error', 'Could not save your changes. Please try again.');
            redirect('reporter/edit.php?id=' . $id);
        }
    }
}

// Values for the form (POST re-fill or DB).
$val = static function (string $k, $default) {
    return is_post() ? (string) ($_POST[$k] ?? '') : (string) $default;
};

$activeNav = 'profile';
[$statusLabel, $statusVariant] = status_label((string) $news['status']);
$meta = ['title' => 'Edit submission | ' . site_name(), 'robots' => 'noindex,nofollow'];
require KL_INCLUDES . '/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-8">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <a class="btn btn-sm btn-outline-emerald" href="<?= e_attr(base_url('reporter/index.php')) ?>">← Dashboard</a>
      <h1 class="h4 mb-0">Edit submission</h1>
      <span class="badge text-bg-<?= e_attr($statusVariant) ?>"><?= e($statusLabel) ?></span>
    </div>

    <?php if ($news['status'] === 'needs_information' && !empty($news['rejection_reason'])): ?>
      <div class="alert alert-warning"><strong>Reviewer asked for more information:</strong><br><?= e((string) $news['rejection_reason']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger">Please fix the highlighted fields.</div><?php endif; ?>

    <div class="kl-form-card">
      <form method="post" action="<?= e_attr(base_url('reporter/edit.php?id=' . $id)) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="title">Headline</label>
          <input class="form-control <?= isset($errors['title'])?'is-invalid':'' ?>" id="title" name="title" maxlength="200" required value="<?= e_attr($val('title', $news['title'])) ?>">
          <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select <?= isset($errors['category_id'])?'is-invalid':'' ?>" id="category_id" name="category_id" required>
              <option value="">Choose…</option>
              <?php foreach ($categories as $c): $sel = (int)$val('category_id', $news['category_id']) === (int)$c['id']; ?>
                <option value="<?= (int)$c['id'] ?>" <?= $sel?'selected':'' ?>><?= e(($c['icon']?$c['icon'].' ':'').$c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="language_code">Language</label>
            <select class="form-select" id="language_code" name="language_code" required>
              <?php foreach ($languages as $lng): $sel = $val('language_code', $news['language_code']) === $lng['code']; ?>
                <option value="<?= e_attr($lng['code']) ?>" <?= $sel?'selected':'' ?>><?= e($lng['native_name'].' ('.$lng['name'].')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="location_id">Location</label>
            <select class="form-select <?= isset($errors['location_id'])?'is-invalid':'' ?>" id="location_id" name="location_id" required>
              <option value="">Choose…</option>
              <?php foreach ($locations as $loc): $sel = (int)$val('location_id', $news['location_id']) === (int)$loc['id']; ?>
                <option value="<?= (int)$loc['id'] ?>" <?= $sel?'selected':'' ?>><?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label" for="summary">Short summary <span class="text-muted-2 fw-normal">(optional)</span></label>
          <input class="form-control" id="summary" name="summary" maxlength="500" value="<?= e_attr($val('summary', $news['summary'])) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="body">Description</label>
          <textarea class="form-control <?= isset($errors['body'])?'is-invalid':'' ?>" id="body" name="body" rows="8" required><?= e($val('body', $news['body'])) ?></textarea>
          <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?= e($errors['body']) ?></div><?php endif; ?>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-5">
            <label class="form-label" for="source_label">Source / reference <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control" id="source_label" name="source_label" maxlength="200" value="<?= e_attr($val('source_label', $firstSource['label'] ?? '')) ?>">
          </div>
          <div class="col-12 col-md-7">
            <label class="form-label" for="source_url">Source link <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control <?= isset($errors['source_url'])?'is-invalid':'' ?>" id="source_url" name="source_url" maxlength="500" value="<?= e_attr($val('source_url', $firstSource['url'] ?? '')) ?>">
            <?php if (isset($errors['source_url'])): ?><div class="invalid-feedback"><?= e($errors['source_url']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label" for="cover">Replace cover photo <span class="text-muted-2 fw-normal">(optional)</span></label>
          <input class="form-control <?= isset($errors['cover'])?'is-invalid':'' ?>" type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp" data-image-preview="#cover-preview">
          <?php if (isset($errors['cover'])): ?><div class="invalid-feedback d-block"><?= e($errors['cover']) ?></div><?php endif; ?>
          <?php if (!empty($news['cover_media_id'])): $cm = fetch('SELECT thumb_path, path FROM news_media WHERE id = ?', [(int)$news['cover_media_id']]); if ($cm): ?>
            <div class="form-text">Current cover is kept unless you choose a new one.</div>
            <img src="<?= e_attr(upload_url($cm['thumb_path'] ?: $cm['path'])) ?>" alt="current cover" class="rounded mt-2" style="max-height:140px">
          <?php endif; endif; ?>
          <div id="cover-preview" class="mt-2"></div>
        </div>

        <button type="submit" class="btn btn-emerald">
          <?= in_array($news['status'], ['needs_information','draft'], true) ? 'Save &amp; resubmit' : 'Save changes' ?>
        </button>
      </form>
    </div>
  </div>
</div>
<?php require KL_INCLUDES . '/partials/footer.php'; ?>
