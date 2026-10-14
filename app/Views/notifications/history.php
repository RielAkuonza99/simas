<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
$pageUrl = static fn (int $page): string => url('riwayat-notifikasi', ['page' => $page]);
$isAdmin = (currentUser()['role'] ?? '') === 'admin';
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Riwayat Notifikasi</h2>
        <p class="page-sub"><?= number_format($history['total'], 0, ',', '.') ?> pesan.</p>
    </div>
    <?php if ($history['deletableCount'] > 0): ?>
        <form method="post" action="<?= e(url('notifikasi/bersihkan')) ?>"
              data-confirm data-confirm-title="Bersihkan seluruh riwayat?"
              data-confirm-message="Notifikasi yang dapat dihapus akan dihapus permanen. Permintaan edit yang masih menunggu keputusan tetap ada."
              data-confirm-label="Bersihkan riwayat" data-confirm-tone="danger">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button class="btn btn-quiet-danger" type="submit"><?= ui_icon('trash') ?>Bersihkan riwayat</button>
        </form>
    <?php endif; ?>
</div>

<?= ui_alert($notice) ?>

<section class="card list-card" aria-label="Pesan notifikasi">
    <?php if (!$history['items']): ?>
        <?= ui_empty_state('inbox', 'Belum ada notifikasi', 'Pesan baru akan muncul di sini.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--stack notification-history-table">
                <thead><tr><th>Pesan</th><th>Dari</th><th>Surat</th><th>Status</th><th>Waktu</th><th class="col-act">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($history['items'] as $item): ?>
                    <?php $pendingEdit = $item['kind'] === 'EDIT_REQUEST' && $item['request_status'] === 'PENDING'; ?>
                    <tr id="notification-<?= (int) $item['id'] ?>">
                        <td data-label="Pesan"><div class="notification-history-message"><?= nl2br(e($item['message'])) ?></div></td>
                        <td data-label="Dari"><?= e($item['sender_username'] ?: 'Pengguna dihapus') ?></td>
                        <td data-label="Surat">
                            <?php if ($item['surat_id']): ?><a href="<?= e(url('surat/' . (int) $item['surat_id'])) ?>">#<?= (int) $item['surat_id'] ?></a><?php else: ?>-<?php endif; ?>
                        </td>
                        <td data-label="Status"><?= (int) $item['is_read'] === 1 ? ui_badge('Dibaca', 'neutral') : ui_badge('Belum dibaca', 'info') ?></td>
                        <td class="nowrap" data-label="Waktu"><?= e($item['created_at']) ?></td>
                        <td class="col-act" data-label="Aksi">
                            <div class="row-actions notification-history-actions">
                                <?php if ((int) $item['is_read'] === 0 && !$pendingEdit): ?>
                                    <form method="post" action="<?= e(url('notifikasi/' . (int) $item['id'] . '/baca-riwayat')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <button class="btn btn-secondary btn-sm" type="submit">Tandai dibaca</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isAdmin && $pendingEdit): ?>
                                    <form method="post" action="<?= e(url('pengajuan/' . (int) $item['edit_request_id'] . '/keputusan')) ?>" data-notification-review-form>
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-primary btn-sm" type="submit">Setujui</button>
                                    </form>
                                    <form method="post" action="<?= e(url('pengajuan/' . (int) $item['edit_request_id'] . '/keputusan')) ?>" data-notification-review-form>
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-quiet-danger btn-sm" type="submit">Tolak</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$pendingEdit): ?>
                                <form method="post" action="<?= e(url('notifikasi/' . (int) $item['id'] . '/hapus-riwayat')) ?>"
                                      data-confirm data-confirm-title="Hapus pesan ini?"
                                      data-confirm-message="Pesan ini akan dihapus permanen dari riwayat notifikasi Anda."
                                      data-confirm-label="Hapus pesan" data-confirm-tone="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
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
        <?php if ($history['pages'] > 1): ?>
            <nav class="pagination" aria-label="Halaman riwayat notifikasi">
                <span class="pagination__summary">Halaman <?= (int) $history['page'] ?> dari <?= (int) $history['pages'] ?></span>
                <div class="pagination__links">
                    <?php if ($history['page'] > 1): ?><a class="page-link page-link--nav" href="<?= e($pageUrl($history['page'] - 1)) ?>"><?= ui_icon('chevron-left') ?>Sebelumnya</a><?php endif; ?>
                    <?php if ($history['page'] < $history['pages']): ?><a class="page-link page-link--nav" href="<?= e($pageUrl($history['page'] + 1)) ?>">Berikutnya<?= ui_icon('chevron-right') ?></a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>