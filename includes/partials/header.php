<?php
/** Layout: top navigation bar (desktop) + brand (mobile). */
declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { exit; }

$u            = current_user();
$activeNav    = $activeNav ?? '';
$defaultLocId = setting_int('default_location_id', 0);
$locName      = 'Khunta, Mayurbhanj';
if ($defaultLocId) {
    foreach (active_locations() as $loc) {
        if ((int) $loc['id'] === $defaultLocId) {
            $locName = $loc['name'] . ($loc['parent_id'] ? '' : '');
            break;
        }
    }
}

/** nav link helper */
$nav = function (string $key, string $href, string $label) use ($activeNav): string {
    $cls = 'kl-nav-link' . ($activeNav === $key ? ' active' : '');
    return '<a class="' . $cls . '" href="' . e_attr(base_url($href)) . '">' . e($label) . '</a>';
};
?>
<nav class="kl-navbar">
  <div class="container container-kl">
    <div class="d-flex align-items-center justify-content-between py-2 gap-2">

      <!-- Brand + location -->
      <div class="d-flex align-items-center gap-3">
        <a class="kl-brand" href="<?= e_attr(base_url()) ?>">
          <span class="kl-logo-mark">K</span>
          <span class="d-none d-sm-inline"><?= e(site_name()) ?></span>
        </a>
        <span class="kl-location-pill d-none d-md-inline-flex" title="Primary coverage area">
          📍 <?= e($locName) ?>
        </span>
      </div>

      <!-- Desktop nav -->
      <div class="d-none d-lg-flex align-items-center gap-1">
        <?= $nav('home', '', 'Home') ?>
        <?= $nav('latest', 'latest.php', 'Latest') ?>
        <?= $nav('categories', 'categories.php', 'Categories') ?>
        <?= $nav('trending', 'latest.php?sort=trending', 'Trending') ?>
        <?= $nav('videos', 'latest.php?media=video', 'Videos') ?>
        <?= $nav('photos', 'latest.php?media=photo', 'Photos') ?>
      </div>

      <!-- Right side: search + auth -->
      <div class="d-flex align-items-center gap-2">
        <form class="d-none d-md-flex" role="search" action="<?= e_attr(base_url('search.php')) ?>" method="get">
          <input class="form-control form-control-sm" type="search" name="q"
                 placeholder="Search news…" aria-label="Search" style="min-width:180px">
        </form>

        <?php if ($u): ?>
          <a href="<?= e_attr(base_url('submit-news.php')) ?>" class="btn btn-emerald btn-sm d-none d-sm-inline-flex">＋ Submit</a>
          <div class="dropdown">
            <button class="btn btn-outline-emerald btn-sm dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
              <?= e(mb_strimwidth($u['name'], 0, 16, '…')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e_attr(base_url('profile.php')) ?>">My profile</a></li>
              <li><a class="dropdown-item" href="<?= e_attr(base_url('submit-news.php')) ?>">Submit news</a></li>
              <li><a class="dropdown-item" href="<?= e_attr(base_url('profile.php#submissions')) ?>">My submissions</a></li>
              <?php if (is_staff((int) $u['id'])): ?>
                <li><hr class="dropdown-divider"></li>
                <li><span class="dropdown-item-text text-muted-2 small">Admin tools — Phase 2</span></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form action="<?= e_attr(base_url('logout.php')) ?>" method="post" class="px-1">
                  <?= csrf_field() ?>
                  <button type="submit" class="dropdown-item text-danger">Log out</button>
                </form>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= e_attr(base_url('login.php')) ?>" class="btn btn-outline-emerald btn-sm">Log in</a>
          <a href="<?= e_attr(base_url('register.php')) ?>" class="btn btn-emerald btn-sm d-none d-sm-inline-flex">Join</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</nav>
