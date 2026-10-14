<?php
$currentUser = currentUser();
$activePath = requestPath();
$isSuratPage = $activePath === '/surat' || str_starts_with($activePath, '/surat/');
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$isUserPage = str_starts_with($activePath, '/pengguna');
$isSyncPage = $activePath === '/sinkronisasi';
$isSettingsPage = $activePath === '/pengaturan' || $activePath === '/aktivitas';
$isFaqPage = $activePath === '/panduan';
$isAddPage = $activePath === '/surat/tambah';
$canCreateSurat = in_array($currentUser['role'] ?? '', ['admin', 'pelayanan'], true);
$username = (string) ($currentUser['username'] ?? '');
$navLink = static fn (bool $active): string => 'nav-link' . ($active ? ' active' : '');
$navCurrent = static fn (bool $active): string => $active ? ' aria-current="page"' : '';
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <span class="brand-seal">
            <img src="<?= e(url('assets/img/icon.svg')) ?>" alt="Logo BAPPERIDA" class="brand-icon" width="36" height="36">
        </span>
        <div class="brand-copy"><strong>BAPPERIDA</strong><small>Kota Tanjungbalai</small></div>
    </div>

    <nav class="nav" id="primary-navigation" aria-label="Navigasi utama">
        <?php if ($canCreateSurat): ?>
            <a class="nav-cta" href="<?= e(url('surat/tambah')) ?>" title="Tambah Surat"<?= $navCurrent($isAddPage) ?>>
                <?= ui_icon('plus', 'nav-icon') ?><span>Tambah Surat</span>
            </a>
        <?php endif; ?>

        <div class="nav-group">
            <a class="<?= $navLink($activePath === '/dashboard') ?>" href="<?= e(url('dashboard')) ?>" title="Dashboard"<?= $navCurrent($activePath === '/dashboard') ?>>
                <?= ui_icon('dashboard', 'nav-icon') ?><span>Dashboard</span>
            </a>
            <a class="<?= $navLink($isSuratPage && !$isAddPage) ?>" href="<?= e(url('surat')) ?>" title="Surat Masuk"<?= $navCurrent($isSuratPage && !$isAddPage) ?>>
                <?= ui_icon('inbox', 'nav-icon') ?><span>Surat Masuk</span>
            </a>
            <a class="<?= $navLink($activePath === '/riwayat-disposisi') ?>" href="<?= e(url('riwayat-disposisi')) ?>" title="Riwayat Disposisi"<?= $navCurrent($activePath === '/riwayat-disposisi') ?>>
                <?= ui_icon('activity', 'nav-icon') ?><span>Riwayat Disposisi</span>
            </a>
            <a class="<?= $navLink($isFaqPage) ?>" href="<?= e(url('panduan')) ?>" title="Panduan"<?= $navCurrent($isFaqPage) ?>>
                <?= ui_icon('help-circle', 'nav-icon') ?><span>Panduan</span>
            </a>
        </div>

        <?php if ($isAdmin): ?>
            <div class="nav-group">
                <p class="nav-heading">Administrasi</p>
                <a class="<?= $navLink($isUserPage) ?>" href="<?= e(url('pengguna')) ?>" title="Pengguna"<?= $navCurrent($isUserPage) ?>>
                    <?= ui_icon('users', 'nav-icon') ?><span>Pengguna</span>
                </a>
                <a class="<?= $navLink($isSyncPage) ?>" href="<?= e(url('sinkronisasi')) ?>" title="Sinkronisasi metadata"<?= $navCurrent($isSyncPage) ?>>
                    <?= ui_icon('sync', 'nav-icon') ?><span>Sinkronisasi metadata</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
        <a class="<?= $navLink($isSettingsPage) ?>" href="<?= e(url('pengaturan')) ?>" title="Pengaturan"<?= $navCurrent($isSettingsPage) ?>>
            <?= ui_icon('settings', 'nav-icon') ?><span>Pengaturan</span>
        </a>
    </div>
</aside>
<button class="mobile-nav-backdrop" type="button" data-mobile-nav-backdrop aria-label="Tutup menu navigasi" hidden></button>
<div class="main">
<div class="navigation-loadbar is-loading" data-navigation-loadbar aria-hidden="true"><span></span></div>
<header class="topbar">
    <div class="topbar-leading">
        <button class="icon-btn sidebar-collapse-toggle" type="button" data-sidebar-toggle aria-controls="primary-navigation" aria-expanded="true" aria-label="Tutup navigasi samping" title="Tutup navigasi samping">
            <?= ui_icon('menu') ?>
        </button>
        <span class="topbar-brand">Surat Masuk</span>
        <?php if ($activePath !== '/surat'): ?>
            <form class="topbar-search" method="get" action="<?= e(url('surat')) ?>" role="search">
                <label class="sr-only" for="topbar-q">Cari surat</label>
                <?= ui_icon('search', 'topbar-search__icon') ?>
                <input id="topbar-q" class="topbar-search__input" type="search" name="q" maxlength="200" placeholder="Cari ID, agenda, nomor, asal, hal" autocomplete="off" data-search-shortcut>
                <kbd class="topbar-search__hint" aria-hidden="true">/</kbd>
            </form>
        <?php endif; ?>
    </div>
    <div class="topbar-actions">
        <details class="account-menu topbar-notifications" data-notification-menu
                 data-list-url="<?= e(url('notifikasi')) ?>"
                 data-notification-base="<?= e(url('notifikasi')) ?>"
                 data-history-url="<?= e(url('riwayat-notifikasi')) ?>"
                 data-decision-base="<?= e(url('pengajuan')) ?>"
                 data-letter-base="<?= e(url('surat')) ?>"
                 data-csrf="<?= e(csrfToken()) ?>"
                 data-user-id="<?= (int) ($currentUser['id'] ?? 0) ?>"
                 data-is-admin="<?= $isAdmin ? '1' : '0' ?>">
            <summary aria-label="Notifikasi" title="Notifikasi">
                <?= ui_icon('inbox') ?><span class="account-menu__name">Notifikasi</span>
                <span class="notification-count" data-notification-count hidden>0</span>
            </summary>
            <div class="popover notification-popover" aria-label="Notifikasi terbaru">
                <div class="notification-head"><strong>Notifikasi</strong><span data-notification-unread-label>0 belum dibaca</span></div>
                <div class="notification-list" data-notification-list role="list"></div>
                <a class="notification-more" href="<?= e(url('riwayat-notifikasi')) ?>">Lihat selengkapnya</a>
            </div>
        </details>
        <details class="account-menu topbar-settings">
            <summary aria-label="Menu akun" title="Menu akun">
                <?= ui_avatar($username) ?>
                <span class="account-menu__name"><?= e($username) ?></span>
                <?= ui_icon('chevron-down', 'account-menu__chevron') ?>
            </summary>
            <div class="popover settings-popover">
                <div class="popover__user">
                    <?= ui_avatar($username, 'avatar--lg') ?>
                    <div>
                        <strong><?= e($username) ?></strong>
                        <span><?= e(ui_role_label($currentUser['role'] ?? '')) ?> · ID #<?= (int) ($currentUser['id'] ?? 0) ?></span>
                    </div>
                </div>
                <a class="popover__item" href="<?= e(url('pengaturan')) ?>"><?= ui_icon('settings') ?>Profil dan pengaturan</a>
                <form id="logout-form" method="post" action="<?= e(url('logout')) ?>"
                      data-confirm
                      data-confirm-title="Keluar dari aplikasi?"
                      data-confirm-message="Sesi pada perangkat ini akan diakhiri. Anda perlu masuk kembali untuk menggunakan aplikasi."
                      data-confirm-label="Ya, keluar"
                      data-confirm-tone="neutral">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <button class="popover__item popover__item--danger" type="submit"><?= ui_icon('log-out') ?>Keluar dari aplikasi</button>
                </form>
            </div>
        </details>
    </div>
</header>
<main class="content" id="main-content" tabindex="-1">
