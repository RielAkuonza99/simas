<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Pengelolaan Pengguna</h2>
        <p class="page-sub">Akun, role, dan status akses pengguna.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('pengguna/tambah')) ?>"><?= ui_icon('plus') ?>Tambah Pengguna</a>
    </div>
</div>

<?= ui_alert($notice) ?>

<section class="card" aria-label="Daftar pengguna">
<?php if (!$users): ?>
    <?= ui_empty_state(
        'users',
        'Belum ada akun pengguna',
        'Tambahkan akun Pelayanan untuk memberi akses.',
        '<a class="btn btn-primary" href="' . e(url('pengguna/tambah')) . '">' . ui_icon('plus') . 'Tambah Pengguna</a>'
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table data-table--stack">
            <thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Terakhir diubah</th><th class="col-act">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $isSelf = (int) $user['id'] === (int) currentUser()['id']; ?>
                <tr>
                    <td data-label="Username">
                        <span class="user-cell">
                            <?= ui_avatar((string) $user['username']) ?>
                            <strong><?= e($user['username']) ?></strong>
                            <?php if ($isSelf): ?><?= ui_badge('Anda', 'info') ?><?php endif; ?>
                        </span>
                    </td>
                    <td data-label="Role"><?= ui_badge($user['role'] === 'admin' ? 'Administrator' : 'Pelayanan', $user['role'] === 'admin' ? 'info' : 'neutral') ?></td>
                    <td data-label="Status"><?= (int) $user['is_active'] === 1 ? ui_badge('Aktif', 'ok', true) : ui_badge('Nonaktif', 'neutral', true) ?></td>
                    <td class="nowrap" data-label="Dibuat"><?= e($user['created_at']) ?></td>
                    <td class="nowrap" data-label="Terakhir diubah"><?= e($user['updated_at']) ?></td>
                    <td class="col-act" data-label="Aksi">
                        <div class="row-actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e(url('pengguna/' . (int) $user['id'] . '/edit')) ?>"><?= ui_icon('pencil') ?>Edit</a>
                            <?php if (!$isSelf): ?>
                                <form method="post" action="<?= e(url('pengguna/' . (int) $user['id'] . '/hapus')) ?>"
                                      data-confirm
                                      data-confirm-title="<?= e('Hapus pengguna ' . $user['username'] . '?') ?>"
                                      data-confirm-message="<?= e('Akun ' . $user['username'] . ' akan dihapus dan tidak dapat masuk lagi. Tindakan ini tidak dapat dibatalkan.') ?>"
                                      data-confirm-label="Hapus pengguna"
                                      data-confirm-tone="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn btn-quiet-danger btn-sm" type="submit"><?= ui_icon('trash') ?>Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
