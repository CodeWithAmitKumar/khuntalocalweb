<?php
/**
 * KhuntaLocal — Registration.
 * Every member is also a community reporter.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Already logged in? Go home.
if (is_logged_in()) {
    redirect('');
}

old_load();
$errors = [];

if (is_post()) {
    csrf_check();

    if (!setting_bool('registration_enabled', true)) {
        flash_set('error', 'Registration is currently closed. Please check back later.');
        redirect('register.php');
    }

    // Throttle sign-ups per IP.
    if (!rate_limit_hit('register', rate_limit_key(), 5, 3600)) {
        flash_set('error', 'Too many sign-up attempts. Please try again later.');
        old_flash($_POST);
        redirect('register.php');
    }

    $name     = input('name');
    $email    = strtolower(input('email'));
    $phone    = input('phone');
    $password = (string) ($_POST['password'] ?? '');
    $locId    = (int) input('location_id', '0');
    $lang     = input('language_code', 'en');

    $langCodes = array_column(active_languages(), 'code') ?: ['en'];
    $locIds    = array_map('intval', array_column(active_locations(), 'id'));

    $v = new Validator($_POST);
    $v->required('name', 'Full name')->min('name', 2)->max('name', 120);
    $v->required('email', 'Email')->email('email')->max('email', 190);
    if ($phone !== '') { $v->phone('phone'); }
    $v->required('password', 'Password')->min('password', 8)->max('password', 200);
    $v->matches('password_confirm', 'password', 'Password confirmation');
    $v->in('language_code', $langCodes, 'Language');
    if ($locId > 0 && !in_array($locId, $locIds, true)) {
        $v->add('location_id', 'Please choose a valid location.');
    }
    $v->accepted('terms', 'terms');

    // Unique email.
    if (!$v->fails()) {
        $taken = fetch_column('SELECT 1 FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($taken) {
            $v->add('email', 'An account with this email already exists.');
        }
    }

    if ($v->fails()) {
        $errors = $v->errors();
        old_flash($_POST);
    } else {
        try {
            $userId = register_user([
                'name'          => $name,
                'email'         => $email,
                'phone'         => $phone !== '' ? $phone : null,
                'password'      => $password,
                'location_id'   => $locId > 0 ? $locId : null,
                'language_code' => $lang,
            ]);
            audit_log('USER_REGISTERED', ['entity_type' => 'user', 'entity_id' => $userId, 'admin_id' => $userId]);
            login_user_session($userId);
            flash_set('success', 'Welcome to KhuntaLocal! Your reporter account is ready.');
            redirect(intended_url(''));
        } catch (Throwable $ex) {
            error_log('registration failed: ' . $ex->getMessage());
            flash_set('error', 'Something went wrong creating your account. Please try again.');
            old_flash($_POST);
            redirect('register.php');
        }
    }
}

$meta = ['title' => 'Create your account | ' . site_name(), 'robots' => 'noindex,follow'];
$bodyClass = 'bg-auth';
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6 col-xl-5">
    <div class="text-center mb-3">
      <h1 class="h3 fw-bold mb-1">Join KhuntaLocal</h1>
      <p class="text-muted-2">Create an account to read and report local news.</p>
    </div>

    <div class="kl-form-card">
      <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e('Please fix the highlighted fields below.') ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e_attr(base_url('register.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label" for="name">Full name</label>
          <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                 id="name" name="name" value="<?= e_attr(old('name')) ?>" required autocomplete="name">
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                 id="email" name="email" value="<?= e_attr(old('email')) ?>" required autocomplete="email">
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="phone">Phone <span class="text-muted-2 fw-normal">(optional)</span></label>
            <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                   id="phone" name="phone" value="<?= e_attr(old('phone')) ?>" autocomplete="tel">
            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="language_code">Preferred language</label>
            <select class="form-select" id="language_code" name="language_code">
              <?php foreach (active_languages() as $lng): ?>
                <option value="<?= e_attr($lng['code']) ?>" <?= old('language_code', 'en') === $lng['code'] ? 'selected' : '' ?>>
                  <?= e($lng['native_name'] . ' (' . $lng['name'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label class="form-label" for="location_id">Your area</label>
          <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id">
            <option value="0">Select your area (optional)</option>
            <?php foreach (active_locations() as $loc): ?>
              <option value="<?= (int) $loc['id'] ?>" <?= (string) $loc['id'] === old('location_id') ? 'selected' : '' ?>>
                <?= e($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['location_id'])): ?><div class="invalid-feedback"><?= e($errors['location_id']) ?></div><?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                   id="password" name="password" required minlength="8" autocomplete="new-password">
            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div>
            <?php else: ?><div class="form-text">At least 8 characters.</div><?php endif; ?>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="password_confirm">Confirm password</label>
            <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                   id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-check mt-3">
          <input class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>" type="checkbox"
                 id="terms" name="terms" value="1">
          <label class="form-check-label" for="terms">
            I agree to report responsibly and follow community guidelines.
          </label>
          <?php if (isset($errors['terms'])): ?><div class="invalid-feedback d-block"><?= e($errors['terms']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-emerald w-100 mt-4">Create account</button>
      </form>
    </div>

    <p class="text-center mt-3 mb-0">
      Already have an account? <a href="<?= e_attr(base_url('login.php')) ?>" class="fw-semibold text-emerald">Log in</a>
    </p>
  </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
