<?php
/**
 * KhuntaLocal — Login.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('');
}

old_load();
$errors = [];

if (is_post()) {
    csrf_check();

    $email    = strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');

    $v = new Validator($_POST);
    $v->required('email', 'Email')->email('email');
    $v->required('password', 'Password');

    if ($v->fails()) {
        $errors = $v->errors();
        old_flash($_POST);
    } elseif (login_is_locked($email)) {
        flash_set('error', 'Too many failed attempts. Please wait a few minutes and try again.');
        old_flash($_POST);
        redirect('login.php');
    } else {
        $user = attempt_login($email, $password);
        if ($user) {
            flash_set('success', 'Welcome back, ' . $user['name'] . '.');
            redirect(intended_url(''));
        } else {
            flash_set('error', 'Invalid email or password.');
            old_flash($_POST);
            redirect('login.php');
        }
    }
}

$meta = ['title' => 'Log in | ' . site_name(), 'robots' => 'noindex,follow'];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-7 col-lg-5 col-xl-4">
    <div class="text-center mb-3">
      <h1 class="h3 fw-bold mb-1">Welcome back</h1>
      <p class="text-muted-2">Log in to submit and manage your news.</p>
    </div>

    <div class="kl-form-card">
      <form method="post" action="<?= e_attr(base_url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                 id="email" name="email" value="<?= e_attr(old('email')) ?>" required autocomplete="email" autofocus>
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                 id="password" name="password" required autocomplete="current-password">
          <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-emerald w-100 mt-2">Log in</button>
      </form>
    </div>

    <p class="text-center mt-3 mb-0">
      New here? <a href="<?= e_attr(base_url('register.php')) ?>" class="fw-semibold text-emerald">Create an account</a>
    </p>
  </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
