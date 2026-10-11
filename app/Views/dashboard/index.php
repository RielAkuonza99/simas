<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$percentDone = $total > 0 ? (int) round($disposisi / $total * 100) : 0;
$percentRest = $total > 0 ? 100 - $percentDone : 0;
$allClear = $total > 0 && $belum === 0;
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Dashboard</h2>
        <p class="page-sub">Ringkasan surat masuk dan status disposisi.</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="page-actions">
            <a class="btn btn-primary" href="<?= e(url('surat/tambah')) ?>"><?= ui_icon('plus') ?>Tambah Surat</a>
        </div>
    <?php endif; ?>
</div>

<section class="kpi-grid" aria-label="Ringkasan">
    <article class="card kpi kpi--focus<?= $allClear ? ' is-clear' : '' ?>">
        <div class="kpi__label"><?= $allClear ? 'Semua surat sudah didisposisi' : 'Belum disposisi' ?></div>
        <div class="kpi__value">
            <span><?= number_format((int) $belum, 0, ',', '.') ?></span>
            <span class="kpi__unit">surat<?= $allClear ? '' : ' butuh perhatian' ?></span>
        </div>
        <?php if ($total > 0): ?>
            <div class="progress<?= $allClear ? ' progress--ok' : '' ?>" role="img" aria-label="<?= (int) $percentDone ?> persen surat sudah didisposisi" style="--value: <?= (int) $percentDone ?>%"><div class="progress__bar"></div></div>
            <div class="progress-legend">
                <span><i></i>Sudah disposisi <?= (int) $percentDone ?>%</span>
                <?php if ($percentRest > 0): ?><span><i class="is-rest"></i>Belum <?= (int) $percentRest ?>%</span><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="kpi__note">Belum ada surat yang tercatat.</div>
        <?php endif; ?>
    </article>
    <article class="card kpi">
        <div class="kpi__label">Total surat</div>
        <div class="kpi__value"><?= number_format((int) $total, 0, ',', '.') ?></div>
        <div class="kpi__note">Semua arsip aktif</div>
    </article>
    <article class="card kpi">
        <div class="kpi__label">Sudah disposisi</div>
        <div class="kpi__value"><?= number_format((int) $disposisi, 0, ',', '.') ?></div>
        <div class="kpi__note">Siap ditindaklanjuti</div>
    </article>
</section>

<section class="card" aria-labelledby="recent-heading">
    <div class="card__head">
        <div>
            <h3 class="card__title" id="recent-heading">Surat terbaru</h3>
            <p class="card__desc">Surat yang paling baru dicatat.</p>
        </div>
        <a class="card__link" href="<?= e(url('surat')) ?>">Lihat semua</a>
    </div>
    <?php if (!$terbaru): ?>
        <?= ui_empty_state(
            'inbox',
            'Belum ada surat masuk',
            $isAdmin ? 'Administrator dapat menambahkan surat pertama.' : 'Hubungi administrator untuk menambahkan surat.',
            $isAdmin ? '<a class="btn btn-primary" href="' . e(url('surat/tambah')) . '">' . ui_icon('plus') . 'Tambah Surat</a>' : ''
        ) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <thead>
                    <tr><th>Surat</th><th>Hal</th><th>Surat Dari</th><th>Tanggal</th><th>Disposisi</th><th>Google Drive</th></tr>
                </thead>
                <tbody>
                <?php foreach ($terbaru as $surat): ?>
                    <tr>
                        <td class="c-id">
                            <span class="code"><?= e($surat['kode_pencarian'] ?: '-') ?></span>
                            <span class="cell-sub">Agenda <?= (int) $surat['no'] ?></span>
                        </td>
                        <td class="c-hal">
                            <a class="hal-link" href="<?= e(url('surat/' . (int) $surat['id'])) ?>"><?= e($surat['ringkas'] ?: '-') ?></a>
                            <?php if (!empty($surat['sifat'])): ?><div class="tags"><?= sifatBadge($surat['sifat'], true) ?></div><?php endif; ?>
                        </td>
                        <td class="c-from"><?= e($surat['surat_dari'] ?: '-') ?></td>
                        <td class="c-date nowrap"><?= e(formatTanggal($surat['tanggal_masuk'])) ?></td>
                        <td class="c-disp"><?= ui_disposisi_meter((int) $surat['jumlah_disposisi']) ?></td>
                        <td class="c-doc">
                            <?php if ($surat['driveUrl']): ?>
                                <a class="btn btn-secondary btn-sm" href="<?= e($surat['driveUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= ui_icon('external') ?>Buka Drive</a>
                            <?php else: ?>
                                <span class="muted text-sm">Belum ada tautan</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
