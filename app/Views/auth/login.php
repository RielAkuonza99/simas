<?php require APP_ROOT . '/includes/header.php'; ?>
<aside class="auth-aside">
    <div class="brand">
        <span class="brand-seal">
            <img src="<?= e(url('assets/img/icon.svg')) ?>" alt="Logo BAPPERIDA" class="brand-icon" width="36" height="36">
        </span>
        <div class="brand-copy"><strong>BAPPERIDA</strong><small>Kota Tanjungbalai</small></div>
    </div>
    <div class="auth-pitch">
        <h2>Sistem Surat Masuk</h2>
        <p>Catat surat masuk, teruskan disposisi, dan temukan arsip dalam satu tempat.</p>
    </div>
    <p class="auth-note">Khusus pengguna terdaftar. Aktivitas pada aplikasi ini dicatat untuk keperluan audit.</p>
</aside>

<div class="auth-main">
    <div class="auth-card">
        <h1>Login Sistem</h1>
        <p class="auth-sub">Surat Masuk BAPPERIDA. Masuk dengan akun dari administrator.</p>

        <?php if ($error !== ''): ?>
            <?= ui_alert(['type' => 'danger', 'message' => $error]) ?>
        <?php endif; ?>

        <?php if ($loginRequired): ?>
            <?= ui_alert(['type' => 'warning', 'message' => 'Silakan login terlebih dahulu untuk melanjutkan.']) ?>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login')) ?>" class="auth-form" autocomplete="off" data-validate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="field">
                <label for="username" class="label">Username</label>
                <input type="text" class="input" id="username" name="username" value="<?= e($username) ?>" maxlength="50" required autofocus>
            </div>
            <div class="field">
                <label for="password" class="label">Password</label>
                <div class="input-affix">
                    <input type="password" class="input" id="password" name="password" required>
                    <button class="input-affix__btn" type="button" data-toggle-password="password" aria-label="Tampilkan password" aria-pressed="false">
                        <?= ui_icon('eye') ?>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" data-loading-text="Memeriksa…">Masuk</button>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
