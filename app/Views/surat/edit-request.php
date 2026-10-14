<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
?>
<a class="crumb" href="<?= e(url('surat/' . (int) $id)) ?>"><?= ui_icon('arrow-left') ?>Kembali ke detail surat</a>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Minta Akses Edit Surat</h2>
        <p class="page-sub">Persetujuan berlaku selama 24 jam untuk surat ini.</p>
    </div>
</div>
<?= ui_alert($_SESSION['flash'] ?? null) ?>
<?php unset($_SESSION['flash']); ?>
<section class="card form-card form-card--narrow" aria-labelledby="edit-request-heading">
    <div class="card__head"><h3 class="card__title" id="edit-request-heading">Surat #<?= (int) $surat['id'] ?> · B-<?= str_pad((string) $surat['no'], 4, '0', STR_PAD_LEFT) ?></h3></div>
    <div class="card__body">
        <?php if ($request && $request['status'] === 'PENDING'): ?>
            <p>Permintaan menunggu persetujuan administrator.</p>
            <p class="text-value"><?= e($request['message']) ?></p>
        <?php elseif ($request && $request['status'] === 'APPROVED'): ?>
            <p>Permintaan disetujui. Muat ulang halaman untuk membuka form edit sebelum 24 jam berakhir.</p>
        <?php else: ?>
            <?php if ($request && $request['status'] === 'REJECTED'): ?><p>Permintaan sebelumnya ditolak. Anda dapat mengirim alasan baru.</p><?php endif; ?>
            <?php if ($request && $request['status'] === 'EXPIRED'): ?><p>Persetujuan sebelumnya telah melewati 24 jam. Kirim permintaan baru untuk mengedit surat ini.</p><?php endif; ?>
            <form method="post" action="<?= e(url('surat/' . (int) $id . '/edit-request')) ?>" data-validate
                data-confirm data-confirm-title="Kirim permintaan edit?"
                data-confirm-message="Periksa alasan permintaan sebelum dikirim ke administrator."
                data-confirm-input="message" data-confirm-label="Kirim permintaan" data-confirm-tone="neutral">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="field">
                    <label class="label" for="edit-request-message">Alasan meminta akses edit <span class="req" aria-hidden="true">*</span></label>
                    <textarea class="textarea" id="edit-request-message" name="message" rows="4" maxlength="500" required></textarea>
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="<?= e(url('surat/' . (int) $id)) ?>">Batal</a>
                    <button class="btn btn-primary" type="submit">Kirim Permintaan</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>