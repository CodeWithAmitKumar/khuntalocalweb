<?php
/**
 * KhuntaLocal — Logout (POST + CSRF protected).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    logout();
    // Start a fresh session so we can flash a message.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    flash_set('success', 'You have been logged out.');
    redirect('');
}

// GET: show a tiny confirmation form (avoids logout-by-link CSRF).
$meta = ['title' => 'Log out | ' . site_name(), 'robots' => 'noindex,nofollow'];
require __DIR__ . '/includes/partials/head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-6 col-lg-4">
    <div class="kl-form-card text-center">
      <h1 class="h4 mb-3">Log out?</h1>
      <p class="text-muted-2">Are you sure you want to log out of <?= e(site_name()) ?>?</p>
      <form method="post" action="<?= e_attr(base_url('logout.php')) ?>">
        <?= csrf_field() ?>
        <div class="d-flex gap-2 justify-content-center">
          <a href="<?= e_attr(base_url()) ?>" class="btn btn-outline-emerald">Cancel</a>
          <button type="submit" class="btn btn-emerald">Log out</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
