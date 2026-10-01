<?php
/**
 * KhuntaLocal — Profile.
 *   ?reporter=username  -> public reporter profile (published stories)
 *   (logged in, no param) -> own profile: edit + "My submissions" + stats
 * Full reporter dashboard with rich stat cards arrives in Phase 2.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$reporterParam = input('reporter');

/* =========================================================================
 * PUBLIC REPORTER VIEW
 * ====================================================================== */
if ($reporterParam !== '') {
    $reporter = fetch(
        "SELECT u.*, l.name AS location_name FROM users u
           LEFT JOIN locations l ON l.id = u.location_id
          WHERE u.username = ? AND u.status = 'active' LIMIT 1",
        [$reporterParam]
    );
    if (!$reporter) {
        http_response_code(404);
        $meta = ['title' => 'Reporter not found | ' . site_name(), 'robots' => 'noindex,follow'];
        require __DIR__ . '/includes/partials/head.php';
        render_empty_state('Reporter not found', 'This profile does not exist.', '👤');
        require __DIR__ . '/includes/partials/footer.php';
        exit;
    }

    $published = news_fetch_published(['user_id' => (int) $reporter['id']], 'latest', 12);
    $pubCount  = news_count_published(['user_id' => (int) $reporter['id']]);

    $meta = [
        'title'       => $reporter['name'] . ' — Reporter | ' . site_name(),
        'description' => 'Stories by ' . $reporter['name'] . ' on ' . site_name() . '.',
        'canonical'   => reporter_url((string) $reporter['username']),
    ];
    require __DIR__ . '/includes/partials/head.php';
    ?>
    <div class="kl-card p-4 mb-4" style="box-shadow:none">
      <div class="d-flex align-items-center gap-3">
        <span class="kl-logo-mark" style="width:64px;height:64px;font-size:1.6rem;background:var(--kl-primary-light);color:var(--kl-primary-dark)">
          <?php if (!empty($reporter['avatar'])): ?>
            <img src="<?= e_attr(upload_url($reporter['avatar'])) ?>" alt="<?= e_attr($reporter['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px">
          <?php else: ?><?= e(mb_substr($reporter['name'], 0, 1)) ?><?php endif; ?>
        </span>
        <div>
          <h1 class="h4 mb-1"><?= e($reporter['name']) ?></h1>
          <div class="small text-muted-2 d-flex flex-wrap gap-3">
            <?php if (!empty($reporter['show_location']) && !empty($reporter['location_name'])): ?>
              <span>📍 <?= e($reporter['location_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($reporter['reporter_since'])): ?>
              <span>🗓️ Reporter since <?= e(date('M Y', strtotime((string) $reporter['reporter_since']))) ?></span>
            <?php endif; ?>
            <span>📰 <?= e(format_count($pubCount)) ?> published</span>
          </div>
        </div>
      </div>
      <?php if (!empty($reporter['bio'])): ?><p class="mt-3 mb-0"><?= e($reporter['bio']) ?></p><?php endif; ?>
    </div>

    <div class="kl-section__head"><h2 class="kl-section__title">Published stories</h2></div>
    <?php if ($published): ?>
      <div class="row g-3"><?php foreach ($published as $n) { render_news_card($n); } ?></div>
    <?php else: ?>
      <?php render_empty_state('No published stories yet', '', '📰'); ?>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/partials/footer.php';
    exit;
}

/* =========================================================================
 * OWN PROFILE (edit + submissions)
 * ====================================================================== */
$user = require_login();
old_load();
$errors = [];

$locations = active_locations();
$languages = active_languages();
$locIds    = array_map('intval', array_column($locations, 'id'));
$langCodes = array_column($languages, 'code') ?: ['en'];

if (is_post()) {
    csrf_check();

    $name  = input('name');
    $bio   = trim((string) ($_POST['bio'] ?? ''));
    $phone = input('phone');
    $locId = (int) input('location_id', '0');
    $lang  = input('language_code', (string) $user['language_code']);

    $v = new Validator($_POST);
    $v->required('name', 'Name')->min('name', 2)->max('name', 120);
    if ($bio !== '')   { $v->max('bio', 500, 'Bio'); }
    if ($phone !== '') { $v->phone('phone'); }
    $v->in('language_code', $langCodes, 'Language');
    if ($locId > 0 && !in_array($locId, $locIds, true)) { $v->add('location_id', 'Please choose a valid location.'); }

    // Optional avatar.
    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        $res = upload_image($_FILES['avatar'], 'avatars', ['thumb_width' => 256, 'max_size' => 2 * 1024 * 1024]);
        if (!$res['ok']) {
            $v->add('avatar', $res['error'] ?? 'The avatar could not be uploaded.');
        } else {
            $avatarPath = $res['data']['thumb_path'] ?: $res['data']['path'];
        }
    }

    if ($v->fails()) {
        $errors = $v->errors();
        old_flash($_POST);
    } else {
        $update = [
            'name'          => $name,
            'bio'           => $bio !== '' ? $bio : null,
            'phone'         => $phone !== '' ? $phone : null,
            'location_id'   => $locId > 0 ? $locId : null,
            'language_code' => $lang,
            'show_location' => isset($_POST['show_location']) ? 1 : 0,
            'show_phone'    => isset($_POST['show_phone']) ? 1 : 0,
            'show_email'    => isset($_POST['show_email']) ? 1 : 0,
        ];
        if ($avatarPath !== null) {
            $update['avatar'] = $avatarPath;
        }
        try {
            db_update('users', $update, ['id' => (int) $user['id']]);
            audit_log('PROFILE_UPDATED', ['entity_type' => 'user', 'entity_id' => (int) $user['id']]);
            flash_set('success', 'Your profile has been updated.');
            redirect('profile.php');
        } catch (Throwable $ex) {
            error_log('profile update failed: ' . $ex->getMessage());
            flash_set('error', 'Could not update your profile. Please try again.');
            old_flash($_POST);
            redirect('profile.php');
        }
    }
}

// Stats by status.
$stats = ['total' => 0, 'pending' => 0, 'under_review' => 0, 'published' => 0, 'rejected' => 0, 'needs_information' => 0];
foreach (fetch_all('SELECT status, COUNT(*) c FROM news WHERE user_id = ? GROUP BY status', [(int) $user['id']]) as $r) {
    $stats[$r['status']] = (int) $r['c'];
    $stats['total'] += (int) $r['c'];
}

// Recent submissions (all statuses).
$submissions = fetch_all(
    'SELECT id, slug, title, status, submitted_at, reviewed_at, created_at
       FROM news WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
    [(int) $user['id']]
);

$locName = '';
foreach ($locations as $loc) {
    if ((int) $loc['id'] === (int) $user['location_id']) { $locName = $loc['name']; break; }
}

$activeNav = 'profile';
$meta = ['title' => 'My profile | ' . site_name(), 'robots' => 'noindex,nofollow'];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row g-4">
  <!-- Left: identity + stats -->
  <div class="col-12 col-lg-5">
    <div class="kl-card p-4 mb-3" style="box-shadow:none">
      <div class="d-flex align-items-center gap-3 mb-3">
        <span class="kl-logo-mark" style="width:60px;height:60px;font-size:1.5rem;background:var(--kl-primary-light);color:var(--kl-primary-dark)">
          <?php if (!empty($user['avatar'])): ?>
            <img src="<?= e_attr(upload_url((string) $user['avatar'])) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:10px">
          <?php else: ?><?= e(mb_substr((string) $user['name'], 0, 1)) ?><?php endif; ?>
        </span>
        <div>
          <h1 class="h5 mb-0"><?= e((string) $user['name']) ?></h1>
          <a class="small text-emerald" href="<?= e_attr(reporter_url((string) $user['username'])) ?>">View public profile ↗</a>
        </div>
      </div>

      <div class="row g-2">
        <?php
        $tiles = [
            ['Total', $stats['total']],
            ['Published', $stats['published']],
            ['Pending', $stats['pending']],
            ['Under review', $stats['under_review']],
            ['Needs info', $stats['needs_information']],
            ['Rejected', $stats['rejected']],
        ];
        foreach ($tiles as [$label, $num]): ?>
          <div class="col-6 col-sm-4">
            <div class="kl-stat">
              <div class="kl-stat__num"><?= e((string) $num) ?></div>
              <div class="kl-stat__label"><?= e($label) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right: edit form -->
  <div class="col-12 col-lg-7">
    <div class="kl-form-card">
      <h2 class="h5 mb-3">Edit profile</h2>
      <form method="post" action="<?= e_attr(base_url('profile.php')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="name">Name</label>
          <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name"
                 value="<?= e_attr(old('name', (string) $user['name'])) ?>" required>
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="bio">Bio <span class="text-muted-2 fw-normal">(optional)</span></label>
          <textarea class="form-control <?= isset($errors['bio']) ? 'is-invalid' : '' ?>" id="bio" name="bio" rows="3" maxlength="500"><?= e(old('bio', (string) ($user['bio'] ?? ''))) ?></textarea>
          <?php if (isset($errors['bio'])): ?><div class="invalid-feedback"><?= e($errors['bio']) ?></div><?php endif; ?>
        </div>
        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="phone">Phone <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone"
                   value="<?= e_attr(old('phone', (string) ($user['phone'] ?? ''))) ?>">
            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="language_code">Language</label>
            <select class="form-select" id="language_code" name="language_code">
              <?php foreach ($languages as $lng): ?>
                <option value="<?= e_attr($lng['code']) ?>" <?= (string) $user['language_code'] === $lng['code'] ? 'selected' : '' ?>>
                  <?= e($lng['native_name'] . ' (' . $lng['name'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label" for="location_id">Area</label>
          <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id">
            <option value="0">Not set</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= (int) $loc['id'] ?>" <?= (int) $user['location_id'] === (int) $loc['id'] ? 'selected' : '' ?>>
                <?= e($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['location_id'])): ?><div class="invalid-feedback"><?= e($errors['location_id']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="avatar">Profile photo <span class="text-muted-2 fw-normal">(optional)</span></label>
          <input class="form-control <?= isset($errors['avatar']) ? 'is-invalid' : '' ?>" type="file" id="avatar" name="avatar"
                 accept="image/jpeg,image/png,image/webp">
          <?php if (isset($errors['avatar'])): ?><div class="invalid-feedback d-block"><?= e($errors['avatar']) ?></div><?php endif; ?>
        </div>

        <fieldset class="mb-3">
          <legend class="form-label">Privacy</legend>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show_location" name="show_location" value="1" <?= !empty($user['show_location']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_location">Show my area on my public profile</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show_phone" name="show_phone" value="1" <?= !empty($user['show_phone']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_phone">Show my phone publicly</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show_email" name="show_email" value="1" <?= !empty($user['show_email']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_email">Show my email publicly</label>
          </div>
          <div class="form-text">We keep personal details private by default.</div>
        </fieldset>

        <button type="submit" class="btn btn-emerald">Save changes</button>
      </form>
    </div>
  </div>
</div>

<!-- My submissions -->
<section class="kl-section" id="submissions">
  <div class="kl-section__head">
    <h2 class="kl-section__title">My submissions</h2>
    <a href="<?= e_attr(base_url('submit-news.php')) ?>" class="btn btn-emerald btn-sm">＋ New submission</a>
  </div>
  <?php if ($submissions): ?>
    <div class="table-responsive kl-card" style="box-shadow:none">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="small text-muted-2">
            <th>Headline</th><th>Status</th><th class="d-none d-md-table-cell">Submitted</th>
            <th class="d-none d-md-table-cell">Reviewed</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($submissions as $s): [$label, $variant] = status_label((string) $s['status']); ?>
            <tr>
              <td class="fw-semibold" style="max-width:320px"><?= e(str_excerpt((string) $s['title'], 70)) ?></td>
              <td><span class="badge text-bg-<?= e_attr($variant) ?>"><?= e($label) ?></span></td>
              <td class="d-none d-md-table-cell small text-muted-2"><?= e($s['submitted_at'] ? time_ago($s['submitted_at']) : '—') ?></td>
              <td class="d-none d-md-table-cell small text-muted-2"><?= e($s['reviewed_at'] ? time_ago($s['reviewed_at']) : '—') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-emerald" href="<?= e_attr(news_url((string) $s['slug'])) ?>">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php render_empty_state('No submissions yet', 'Share your first local story — it only takes a minute.', '📝'); ?>
  <?php endif; ?>
</section>

<!-- Saved stories -->
<?php
$saved = fetch_all(
    news_select_base() . ' JOIN saved_news sv ON sv.news_id = n.id
      WHERE sv.user_id = ? AND n.status = \'published\'
      ORDER BY sv.created_at DESC LIMIT 6',
    [(int) $user['id']]
);
?>
<section class="kl-section" id="saved">
  <div class="kl-section__head"><h2 class="kl-section__title">🔖 Saved stories</h2></div>
  <?php if ($saved): ?>
    <div class="row g-3"><?php foreach ($saved as $n) { render_news_card($n); } ?></div>
  <?php else: ?>
    <?php render_empty_state('Nothing saved yet', 'Tap “Save” on any story to keep it here for later.', '🔖'); ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
