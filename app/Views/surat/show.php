<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$isAdminUser = (currentUser()['role'] ?? '') === 'admin';
$halShort = mb_strimwidth((string) ($surat['hal'] ?? ''), 0, 90, '…');
?>
<a class="crumb" href="<?= e(url('surat')) ?>"><?= ui_icon('arrow-left') ?>Kembali ke daftar</a>
<?= ui_alert($notice) ?>
<div class="page-head">
    <div class="page-head__main">
        <div class="page-meta">
            <span class="code">#<?= (int) $surat['id'] ?></span>
            <?= sifatBadge($surat['sifat']) ?>
        </div>
        <h2 class="page-title">Surat Masuk B-<?= str_pad((string) $surat['no'], 4, '0', STR_PAD_LEFT) ?></h2>
    </div>
    <div class="page-actions">
        <?php if ($driveUrl): ?>
            <a class="btn btn-secondary" href="<?= e($driveUrl) ?>" target="_blank" rel="noopener noreferrer"><?= ui_icon('external') ?>Bukti Dukung</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('surat/' . (int) $id . '/edit')) ?>"><?= ui_icon('pencil') ?>Edit</a>
        <?php if ($isAdminUser): ?>
            <form method="post" action="<?= e(url('surat/' . (int) $id . '/hapus')) ?>"
                  data-confirm
                  data-confirm-title="Hapus surat ini?"
                  data-confirm-message="<?= e('Surat ' . ($surat['kode_pencarian'] ?: '#' . (int) $surat['no']) . ($halShort !== '' ? ' (' . $halShort . ')' : '') . ' beserta seluruh disposisinya akan dihapus dari sistem. Tindakan ini tidak dapat dibatalkan.') ?>"
                  data-confirm-label="Hapus surat"
                  data-confirm-tone="danger">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button class="btn btn-quiet-danger" type="submit"><?= ui_icon('trash') ?>Hapus</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="detail-layout">
    <div>
        <section class="card" aria-labelledby="agenda-heading">
            <div class="card__head"><h3 class="card__title" id="agenda-heading">Agenda Surat</h3></div>
            <dl class="card__body kv">
                <div><dt>ID Surat</dt><dd class="code">#<?= (int) $surat['id'] ?></dd></div>
                <div><dt>No Agenda</dt><dd class="code">B-<?= str_pad((string) $surat['no'], 4, '0', STR_PAD_LEFT) ?></dd></div>
                <div><dt>Tanggal Surat Diterima</dt><dd><?= e(formatTanggal($surat['tanggal_masuk'])) ?></dd></div>
            </dl>
        </section>

        <section class="card" aria-labelledby="info-heading">
            <div class="card__head"><h3 class="card__title" id="info-heading">Informasi Surat</h3></div>
            <dl class="card__body kv">
                <div><dt>Asal Surat</dt><dd><?= e($surat['surat_dari'] ?: '-') ?></dd></div>
                <div><dt>No Surat</dt><dd><?= e($surat['nomor_surat'] ?: '-') ?></dd></div>
                <div><dt>Tanggal Surat</dt><dd><?= e(formatTanggal($surat['tanggal_surat'])) ?></dd></div>
                <div><dt>Sifat Surat</dt><dd><?= e($surat['sifat'] ?: 'Tidak diisi') ?></dd></div>
                <div class="kv__wide"><dt>Hal Surat</dt><dd><?= $surat['hal'] ? nl2br(e($surat['hal'])) : '-' ?></dd></div>
                <div><dt>Bukti Dukung</dt><dd><?php if ($driveUrl): ?><a href="<?= e($driveUrl) ?>" target="_blank" rel="noopener noreferrer">Buka Google Drive</a><?php else: ?>Belum ada<?php endif; ?></dd></div>
            </dl>
        </section>

        <section class="card" aria-labelledby="disposisi-heading">
            <div class="card__head">
                <div>
                    <h3 class="card__title" id="disposisi-heading">Disposisi</h3>
                    <p class="card__desc">Pejabat, tujuan, tanggal, dan catatan setiap tahap.</p>
                </div>
                <?= ui_disposisi_meter(count($surat['disposisi'])) ?>
            </div>
            <?php if (!$surat['disposisi']): ?>
                <?= ui_empty_state(
                    'file-text',
                    'Belum ada disposisi',
                    'Tahap disposisi dapat ditambahkan saat surat diedit.',
                    '<a class="btn btn-secondary btn-sm" href="' . e(url('surat/' . (int) $id . '/edit')) . '">' . ui_icon('pencil') . 'Edit surat</a>'
                ) ?>
            <?php else: ?>
                <div class="card__body">
                    <ol class="timeline">
                        <?php foreach ($surat['disposisi'] as $tahap => $disposisi): ?>
                            <li class="timeline__item">
                                <span class="timeline__marker" aria-hidden="true"><?= (int) $tahap ?></span>
                                <div>
                                    <h4 class="timeline__title">Tahap <?= (int) $tahap ?></h4>
                                    <div class="timeline__route">
                                        <span><?= e($disposisi['asal'] ?: '-') ?></span>
                                        <?= ui_icon('arrow-right') ?><span class="sr-only">ke</span>
                                        <span><?= e($disposisi['tujuan'] ?: '-') ?></span>
                                    </div>
                                    <div class="timeline__date"><?= ui_icon('calendar') ?><?= e(formatTanggal($disposisi['tanggal'])) ?></div>
                                    <?php if ($disposisi['catatan']): ?>
                                        <div class="timeline__note"><?= nl2br(e($disposisi['catatan'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($disposisi['driveUrl']): ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= e($disposisi['driveUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= ui_icon('paperclip') ?>Buka lampiran</a>
                                    <?php endif; ?>
                                    <?php if ((int) $surat['selesai_tahap'] === (int) $tahap): ?>
                                        <div class="completion-summary">
                                            <?= ui_badge('Disposisi akhir', 'ok', true) ?>
                                            <span class="cell-sub">Pejabat <?= e($disposisi['asal'] ?: '-') ?> · <?= e(formatTanggal($disposisi['tanggal'])) ?></span>
                                            <?php if ($completionDriveUrl): ?><a class="btn btn-secondary btn-sm" href="<?= e($completionDriveUrl) ?>" target="_blank" rel="noopener noreferrer"><?= ui_icon('external') ?>Bukti dukung akhir</a><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="card" aria-label="Status disposisi akhir">
            <div class="card__head"><h3 class="card__title">Status disposisi</h3></div>
            <div class="card__body">
                <?php if ((int) $surat['selesai_tahap'] > 0): ?>
                    <?= ui_badge('Selesai di disposisi ' . (int) $surat['selesai_tahap'], 'ok', true) ?>
                <?php else: ?>
                    <?= ui_badge('Belum selesai', 'neutral', true) ?>
                <?php endif; ?>
            </div>
        </section>
        <?php if ((currentUser()['role'] ?? '') === 'pelayanan'): ?>
            <section class="card" aria-labelledby="submission-heading">
                <div class="card__head"><h3 class="card__title" id="submission-heading">Pengajuan</h3></div>
                <div class="card__body">
                      <form method="post" action="<?= e(url('surat/' . (int) $id . '/pengajuan')) ?>" data-validate
                          data-confirm data-confirm-title="Kirim pengajuan?"
                          data-confirm-message="Pastikan pesan sudah benar. Pesan akan dikirim ke administrator."
                          data-confirm-input="message" data-confirm-label="Kirim pengajuan" data-confirm-tone="neutral">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <div class="field">
                            <label class="label" for="submission-message">Pesan untuk administrator</label>
                            <textarea class="textarea" id="submission-message" name="message" rows="3" maxlength="500" placeholder="Contoh: Surat sudah diperbarui, menunggu arahan." required></textarea>
                        </div>
                        <div class="form-actions"><button class="btn btn-secondary" type="submit">Kirim Pengajuan</button></div>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
