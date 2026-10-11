<?php
require_once __DIR__ . '/ui.php';
if (!isset($pageTitle)) $pageTitle = 'Surat Masuk Kantor';
$sidebarCollapsed = (($_COOKIE['bapperida_sidebar_collapsed'] ?? '') === '1');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b5e91">
    <title><?= e($pageTitle) ?> · Surat Masuk BAPPERIDA</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/icon.svg')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/public-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link href="<?= e(url('assets/css/app.css?v=20')) ?>" rel="stylesheet">
</head>
<body class="<?= isLoggedIn() ? 'is-app' : 'is-auth' ?>">
<?php ui_sprite(); ?>
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<?php if (!isLoggedIn()): ?>
<div class="auth-page-shell">
<?php else: ?>
<div class="app<?= $sidebarCollapsed ? ' sidebar-collapsed' : '' ?>">
<?php endif; ?>
