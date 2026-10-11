<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$rawDriveLink = trim((string) ($surat['link_drive'] ?? ''));
$isAdminUser = (currentUser()['role'] ?? '') === 'admin';
$halShort = mb_strimwidth((string) ($surat['hal'] ?? ''), 0, 90, '…');
?>
<a class="crumb" href="<?= e(url('surat')) ?>"><?= ui_icon('arrow-left') ?>Kembali ke daftar</a>
<?= ui_alert($notice) ?>
<div class="page-head">
    <div class="page-head__main">
        <div class="page-meta">
            <span class="code"><?= e($surat['kode_pencarian'] ?: '-') ?></span>
            <?= sifatBadge($surat['sifat']) ?>
        </div>
        <h2 class="page-title">Surat Masuk #<?= (int) $surat['no'] ?></h2>
    </div>
    <div class="page-actions">
        <?php if ($driveUrl): ?>
            <a class="btn btn-secondary" href="<?= e($driveUrl) ?>" target="_blank" rel="noopener noreferrer"><?= ui_icon('external') ?>Buka Google Drive</a>
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
        <section class="card" aria-labelledby="isi-heading">
            <div class="card__head"><h3 class="card__title" id="isi-heading">Isi Surat</h3></div>
            <div class="card__body text-blocks">
                <div>
                    <div class="text-label">Hal</div>
                    <div class="lead-text"><?= $surat['hal'] ? nl2br(e($surat['hal'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-label">Pengusul</div>
                    <div class="text-value"><?= $surat['pengusul'] ? nl2br(e($surat['pengusul'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-label">Catatan</div>
                    <div class="text-value"><?= $surat['catatan'] ? nl2br(e($surat['catatan'])) : '-' ?></div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="disposisi-heading">
            <div class="card__head">
                <div>
                    <h3 class="card__title" id="disposisi-heading">Disposisi</h3>
                    <p class="card__desc">Alur penerusan surat, dari tahap awal hingga terakhir.</p>
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
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="card" aria-labelledby="dokumen-heading">
            <div class="card__head"><h3 class="card__title" id="dokumen-heading">Dokumen</h3></div>
            <div class="card__body">
                <?php if ($driveUrl): ?>
                    <div class="doc-state">
                        <span class="doc-state__icon"><?= ui_icon('file-text') ?></span>
                        <div>
                            <strong>Link Google Drive</strong>
                            <p>Link Google Drive tersedia. Gunakan tombol Buka Google Drive di bagian atas halaman.</p>
                        </div>
                    </div>
                <?php elseif ($rawDriveLink !== ''): ?>
                    <div class="doc-state doc-state--invalid">
                        <span class="doc-state__icon"><?= ui_icon('alert-triangle') ?></span>
                        <div>
                            <strong>Link Google Drive</strong>
                            <p>Link tersimpan tidak valid. Periksa atau perbarui data surat.</p>
                            <a class="btn btn-secondary" href="<?= e(url('surat/' . (int) $id . '/edit')) ?>"><?= ui_icon('pencil') ?>Perbarui link</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="doc-state doc-state--missing">
                        <span class="doc-state__icon"><?= ui_icon('file-text') ?></span>
                        <div>
                            <strong>Link Google Drive</strong>
                            <p>Belum ada link Google Drive.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="info-heading">
            <div class="card__head"><h3 class="card__title" id="info-heading">Informasi Surat</h3></div>
            <dl class="card__body kv">
                <div><dt>No Agenda</dt><dd class="tnum"><?= (int) $surat['no'] ?></dd></div>
                <div><dt>ID Pencarian</dt><dd class="code"><?= e($surat['kode_pencarian'] ?: '-') ?></dd></div>
                <div><dt>No Surat</dt><dd><?= e($surat['nomor_surat'] ?: '-') ?></dd></div>
                <div><dt>TGL Surat</dt><dd><?= e(formatTanggal($surat['tanggal_surat'])) ?></dd></div>
                <div><dt>TGL Diterima</dt><dd><?= e(formatTanggal($surat['tanggal_masuk'])) ?></dd></div>
                <div><dt>Sifat</dt><dd><?= e($surat['sifat'] ?: 'Tidak diisi') ?></dd></div>
                <div><dt>Surat Dari</dt><dd><?= e($surat['surat_dari'] ?: '-') ?></dd></div>
            </dl>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
