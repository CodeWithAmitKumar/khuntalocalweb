<?php
/** Layout: mobile bottom navigation (hidden on large screens by CSS). */
declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { exit; }
$activeNav = $activeNav ?? '';
$a = fn(string $k): string => $activeNav === $k ? ' active' : '';
?>
<nav class="kl-bottom-nav d-lg-none" aria-label="Primary">
  <a class="<?= $a('home') ?>" href="<?= e_attr(base_url()) ?>"><span class="ic">🏠</span>Home</a>
  <a class="<?= $a('latest') ?>" href="<?= e_attr(base_url('latest.php')) ?>"><span class="ic">📰</span>Latest</a>
  <a class="kl-submit-fab" href="<?= e_attr(base_url('submit-news.php')) ?>"><span class="ic">＋</span>Submit</a>
  <a class="<?= $a('categories') ?>" href="<?= e_attr(base_url('categories.php')) ?>"><span class="ic">🗂️</span>Topics</a>
  <a class="<?= $a('profile') ?>" href="<?= e_attr(base_url(is_logged_in() ? 'profile.php' : 'login.php')) ?>"><span class="ic">👤</span>Profile</a>
</nav>
