<?php
/**
 * Admin console layout — head + sidebar + open content.
 *
 * A back-office page sets (optional) $pageTitle and $activeAdmin, then:
 *   require KL_INCLUDES . '/partials/admin-head.php';
 *   ... content ...
 *   require KL_INCLUDES . '/partials/admin-foot.php';
 *
 * Access must already be gated by the page (require_permission / require_login).
 */
declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { http_response_code(500); exit('Not bootstrapped.'); }

$pageTitle   = $pageTitle ?? 'Admin';
$activeAdmin = $activeAdmin ?? '';
$u           = current_user();

$favicon = 'data:image/svg+xml,'
    . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#136046"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="34" font-weight="700" fill="#fff">K</text></svg>');

/** sidebar link helper (only shows items the user may access) */
$items = [
    ['dashboard',    'admin/index.php',        '▣',  'Dashboard',          null],
    ['verification', 'admin/verification.php', '🛡️', 'Verification queue', 'news.verify'],
];
?><!doctype html>
<html lang="<?= e((string) config('app.locale', 'en')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($pageTitle) ?> · <?= e(site_name()) ?> Admin</title>
  <link rel="icon" href="<?= e_attr($favicon) ?>">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= e_attr(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(asset('css/theme.css')) ?>">
</head>
<body class="kl-admin">
<div class="kl-admin-shell">

  <!-- Sidebar -->
  <aside class="kl-admin-sidebar" id="klAdminSidebar">
    <a class="kl-brand text-white mb-3 px-2" href="<?= e_attr(base_url()) ?>">
      <span class="kl-logo-mark">K</span><span><?= e(site_name()) ?></span>
    </a>
    <div class="kl-admin-role px-2 mb-3"><?= e(is_staff((int) $u['id']) ? 'Admin console' : 'Console') ?></div>
    <nav class="kl-admin-nav">
      <?php foreach ($items as [$key, $href, $icon, $label, $perm]): ?>
        <?php if ($perm === null || can($perm, (int) $u['id'])): ?>
          <a class="kl-admin-link <?= $activeAdmin === $key ? 'active' : '' ?>" href="<?= e_attr(base_url($href)) ?>">
            <span class="ic"><?= $icon ?></span><span><?= e($label) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="kl-admin-sep"></div>
      <a class="kl-admin-link" href="<?= e_attr(base_url('reporter/index.php')) ?>"><span class="ic">📝</span><span>Reporter area</span></a>
      <a class="kl-admin-link" href="<?= e_attr(base_url()) ?>"><span class="ic">↗</span><span>View site</span></a>
    </nav>
    <div class="kl-admin-soon px-2">More admin tools (categories, settings, users, reports) arrive in later phases.</div>
  </aside>

  <!-- Main -->
  <div class="kl-admin-main">
    <header class="kl-admin-topbar">
      <button class="btn btn-sm btn-outline-emerald d-lg-none" type="button" onclick="document.getElementById('klAdminSidebar').classList.toggle('open')">☰</button>
      <h1 class="h5 mb-0"><?= e($pageTitle) ?></h1>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-muted-2 d-none d-sm-inline"><?= e((string) $u['name']) ?></span>
        <form action="<?= e_attr(base_url('logout.php')) ?>" method="post" class="m-0">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-emerald" type="submit">Log out</button>
        </form>
      </div>
    </header>
    <div class="kl-admin-content">
      <?php render_flash(); ?>
