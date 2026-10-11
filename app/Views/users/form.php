<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
$isEditing = $userRecord !== null;
?>
<a class="crumb" href="<?= e(url('pengguna')) ?>"><?= ui_icon('arrow-left') ?>Kembali ke daftar pengguna</a>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title"><?= $isEditing ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>
        <p class="page-sub">Perubahan password berlaku pada login berikutnya dan mencabut sesi perangkat lain.</p>
    </div>
</div>

<?= ui_error_summary($errors) ?>

<form class="card form-card form-card--narrow" method="post" action="<?= e(url($isEditing ? 'pengguna/' . (int) $userRecord['id'] : 'pengguna')) ?>" autocomplete="off" data-validate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= (int) $userRecord['id'] ?>"><?php endif; ?>

    <div class="card__body">
        <div class="form-grid">
            <div class="field c-12">
                <label class="label" for="username">Username <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="username" name="username" value="<?= e($formData['username'] ?? '') ?>" maxlength="50" required>
                <div class="field-hint">3 sampai 50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.</div>
            </div>
            <div class="field c-12">
                <label class="label" for="password"><?= $isEditing ? 'Password baru (kosongkan jika tidak diubah)' : 'Password awal' ?><?php if (!$isEditing): ?> <span class="req" aria-hidden="true">*</span><?php endif; ?></label>
                <div class="input-affix">
                    <input class="input" type="password" id="password" name="password" minlength="12" <?= $isEditing ? '' : 'required' ?> autocomplete="new-password">
                    <button class="input-affix__btn" type="button" data-toggle-password="password" aria-label="Tampilkan password" aria-pressed="false"><?= ui_icon('eye') ?></button>
                </div>
                <div class="field-hint">Minimal 12 karakter. Password lama tidak ditampilkan.</div>
            </div>
            <div class="field c-6">
                <label class="label" for="role">Hak akses <span class="req" aria-hidden="true">*</span></label>
                <select class="select" id="role" name="role" required>
                    <option value="pelayanan" <?= ($formData['role'] ?? '') === 'pelayanan' ? 'selected' : '' ?>>Pelayanan</option>
                    <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <div class="field c-6">
                <label class="label" for="is_active">Status akun <span class="req" aria-hidden="true">*</span></label>
                <select class="select" id="is_active" name="is_active" required>
                    <option value="1" <?= (string) ($formData['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= (string) ($formData['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <span class="form-note"><span class="req" aria-hidden="true">*</span> wajib diisi</span>
        <a class="btn btn-secondary" href="<?= e(url('pengguna')) ?>">Batal</a>
        <button class="btn btn-primary" type="submit" data-loading-text="Menyimpan…">Simpan Pengguna</button>
    </div>
</form>
<?php require APP_ROOT . '/includes/footer.php'; ?>
