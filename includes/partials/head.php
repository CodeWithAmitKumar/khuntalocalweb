<?php
/**
 * Layout: document head + open body + site header + flash.
 *
 * A page sets (all optional) before including this file:
 *   $meta       array of SEO meta overrides (see default_meta())
 *   $bodyClass  extra class(es) for <body>
 *   $activeNav  current nav key: home|latest|categories|trending|videos|photos|submit|profile
 * then renders content, then requires partials/footer.php.
 */

declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { http_response_code(500); exit('Not bootstrapped.'); }

$meta      = isset($meta) && is_array($meta) ? array_merge(default_meta(), $meta) : default_meta();
$bodyClass = $bodyClass ?? '';
$activeNav = $activeNav ?? '';

// Inline SVG favicon (emerald "K" mark) — no binary asset required.
$favicon = 'data:image/svg+xml,'
    . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#1a7f5a"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="38" font-weight="700" fill="#fff">K</text></svg>');
?><!doctype html>
<html lang="<?= e((string) config('app.locale', 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a7f5a">
    <meta name="csrf-token" content="<?= e_attr(csrf_token()) ?>">
    <meta name="app-base" content="<?= e_attr(rtrim(base_url(), '/') . '/') ?>">
    <?php render_meta_tags($meta); ?>
    <link rel="icon" href="<?= e_attr($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Oriya:wght@400;600;700&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="<?= e_attr(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e_attr(asset('css/theme.css')) ?>">
</head>
<body class="has-bottom-nav <?= e_attr($bodyClass) ?>">
<?php require __DIR__ . '/header.php'; ?>
<main class="container container-kl py-3 py-lg-4">
    <?php render_flash(); ?>
