<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$isAdminUser = (currentUser()['role'] ?? '') === 'admin';
$canCreate = in_array(currentUser()['role'] ?? '', ['admin', 'pelayanan'], true);
$hasFilters = $filters['q'] !== '' || $filters['dari'] !== '' || $filters['sampai'] !== '';
$firstPage = $pagination['total'] === 0 ? 0 : (($pagination['page'] - 1) * $pagination['perPage']) + 1;
$lastPage = min($pagination['page'] * $pagination['perPage'], $pagination['total']);
$pageNumbers = range(max(1, $pagination['page'] - 2), min($pagination['pages'], $pagination['page'] + 2));
$pageUrl = static fn (int $page): string => url('surat', array_merge($filters, ['page' => $page]));
$chip = static function (string $label, string $removeKey) use ($filters): string {
    $remaining = array_filter(
        array_diff_key($filters, [$removeKey => true]),
        static fn ($value): bool => $value !== '' && $value !== null
    );

    return '<span class="chip"><span class="chip__text">' . e($label) . '</span>'
        . '<a class="chip__x" href="' . e(url('surat', $remaining)) . '" aria-label="Hapus filter ' . e($label) . '">' . ui_icon('x') . '</a></span>';
};
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Daftar Surat Masuk</h2>
        <p class="page-sub"><?= number_format($pagination['total'], 0, ',', '.') ?> surat<?= $hasFilters ? ' sesuai filter' : '' ?>.</p>
    </div>
    <?php if ($canCreate): ?>
        <div class="page-actions">
            <?php if ($isAdminUser): ?><button class="btn btn-secondary" type="button" data-export-open><?= ui_icon('external') ?>Ekspor</button><?php endif; ?>
            <a class="btn btn-primary" href="<?= e(url('surat/tambah')) ?>"><?= ui_icon('plus') ?>Tambah Surat</a>
        </div>
    <?php endif; ?>
</div>
<?= ui_alert($notice) ?>
<?php if ($savedSuratId > 0): ?><div data-post-save-confirm="<?= $savedSuratId ?>" data-detail-url="<?= e(url('surat/' . $savedSuratId)) ?>" hidden></div><?php endif; ?>

<form class="card card__body filter-card" method="get" action="<?= e(url('surat')) ?>" role="search">
    <div class="filter-grid">
        <div class="field">
            <label class="label" for="q">Cari surat</label>
            <div class="input-icon">
                <?= ui_icon('search') ?>
                <input id="q" class="input" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="200" placeholder="ID surat, agenda, nomor, asal, atau hal" data-search-shortcut>
            </div>
        </div>
        <div class="field">
            <label class="label" for="dari">Diterima dari</label>
            <input id="dari" type="date" class="input" name="dari" value="<?= e($filters['dari']) ?>">
        </div>
        <div class="field">
            <label class="label" for="sampai">Sampai</label>
            <input id="sampai" type="date" class="input" name="sampai" value="<?= e($filters['sampai']) ?>">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit"><?= ui_icon('search') ?>Cari</button>
            <a href="<?= e(url('surat')) ?>" class="btn btn-secondary"><?= ui_icon('reset') ?>Reset</a>
        </div>
    </div>
    <?php if ($hasFilters): ?>
        <div class="chips" aria-label="Filter aktif">
            <span class="chips__label">Filter aktif:</span>
            <?php if ($filters['q'] !== ''): ?><?= $chip('Kata kunci: ' . $filters['q'], 'q') ?><?php endif; ?>
            <?php if ($filters['dari'] !== ''): ?><?= $chip('Dari ' . formatTanggal($filters['dari']), 'dari') ?><?php endif; ?>
            <?php if ($filters['sampai'] !== ''): ?><?= $chip('Sampai ' . formatTanggal($filters['sampai']), 'sampai') ?><?php endif; ?>
        </div>
    <?php endif; ?>
</form>

<?php if ($isAdminUser): ?>
    <form id="bulk-delete-form" method="post" action="<?= e(url('surat/hapus-terpilih')) ?>"
          data-confirm data-confirm-title="Hapus surat terpilih?"
          data-confirm-message="Surat yang dipilih beserta disposisinya akan dihapus dan perubahan arsip akan disinkronkan. Tindakan ini tidak dapat dibatalkan."
          data-confirm-label="Hapus surat" data-confirm-tone="danger">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    </form>
    <div class="bulk-toolbar" data-bulk-actions role="status" aria-live="polite" hidden>
        <span><strong data-selected-count>0</strong> surat dipilih</span>
        <button class="btn btn-quiet-danger" type="submit" form="bulk-delete-form"><?= ui_icon('trash') ?>Hapus terpilih</button>
    </div>
<?php endif; ?>

<section class="card list-card" aria-label="Daftar surat masuk">
<?php if (!$data): ?>
    <?php if ($hasFilters): ?>
        <?= ui_empty_state(
            'search',
            'Tidak ada surat yang cocok',
            'Ubah kata kunci atau rentang tanggal, atau hapus filter untuk melihat semua surat.',
            '<a class="btn btn-secondary" href="' . e(url('surat')) . '">' . ui_icon('reset') . 'Reset filter</a>'
        ) ?>
    <?php else: ?>
        <?= ui_empty_state(
            'inbox',
            'Belum ada surat masuk',
            $canCreate ? 'Tambahkan surat masuk pertama.' : 'Hubungi administrator untuk menambahkan surat.',
            $canCreate ? '<a class="btn btn-primary" href="' . e(url('surat/tambah')) . '">' . ui_icon('plus') . 'Tambah Surat</a>' : ''
        ) ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table data-table--cards surat-list-table">
            <thead>
                <tr>
                    <?php if ($isAdminUser): ?><th class="bulk-select-cell"><label class="bulk-selection-control"><input type="checkbox" data-select-all><span class="sr-only">Pilih semua surat di halaman</span></label></th><?php endif; ?>
                    <th>ID Surat / No Agenda</th><th>Hal Surat</th><th>Asal Surat</th><th>Diterima</th>
                    <th>Disposisi akhir</th><th>Bukti Dukung</th><th class="col-act">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $surat): ?>
                <?php
                $detailUrl = url('surat/' . (int) $surat['id']);
                $halShort = mb_strimwidth((string) ($surat['ringkas'] ?: ''), 0, 90, '…');
                ?>
                <tr>
                    <?php if ($isAdminUser): ?><td class="bulk-select-cell" data-label="Pilih"><label class="bulk-selection-control"><input type="checkbox" form="bulk-delete-form" name="ids[]" value="<?= (int) $surat['id'] ?>" data-select-letter><span class="sr-only">Pilih surat #<?= (int) $surat['id'] ?></span></label></td><?php endif; ?>
                    <td class="c-id">
                        <span class="code">#<?= (int) $surat['id'] ?></span>
                        <span class="cell-sub">B-<?= str_pad((string) $surat['no'], 4, '0', STR_PAD_LEFT) ?></span>
                    </td>
                    <td class="c-hal">
                        <a class="hal-link" href="<?= e($detailUrl) ?>"><?= e($surat['ringkas'] ?: '-') ?></a>
                        <div class="tags"><?= sifatBadge($surat['sifat'], true) ?></div>
                        <?php if (!empty($surat['nomor_surat'])): ?><span class="cell-sub">No Surat: <?= e($surat['nomor_surat']) ?></span><?php endif; ?>
                    </td>
                    <td class="c-from">
                        <span class="cell-primary"><?= e($surat['surat_dari'] ?: '-') ?></span>
                    </td>
                    <td class="c-date nowrap"><?= e(formatTanggal($surat['tanggal_masuk'])) ?></td>
                    <td class="c-disp">
                        <?php if ((int) $surat['selesai_tahap'] > 0): ?>
                            <?= ui_badge('Selesai disposisi ' . (int) $surat['selesai_tahap'], 'ok', true) ?>
                            <?php if (!empty($surat['pejabat_akhir'])): ?><span class="cell-sub"><?= e($surat['pejabat_akhir']) ?></span><?php endif; ?>
                        <?php else: ?><?= ui_badge('Belum selesai', 'neutral', true) ?><?php endif; ?>
                    </td>
                    <td class="c-doc">
                        <?php if ($surat['driveUrl']): ?>
                            <a class="btn btn-secondary btn-sm" href="<?= e($surat['driveUrl']) ?>" target="_blank" rel="noopener noreferrer" title="Buka bukti dukung di Google Drive"><?= ui_icon('external') ?>Buka</a>
                        <?php else: ?>
                            <span class="muted text-sm" title="Belum ada bukti dukung">Belum ada</span>
                        <?php endif; ?>
                    </td>
                    <td class="c-act col-act">
                        <div class="row-actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e($detailUrl) ?>" title="Detail" aria-label="Detail surat #<?= (int) $surat['id'] ?>"><?= ui_icon('eye') ?><span class="btn-label">Detail</span></a>
                            <?php if (isset($approvedEditSuratIds[(int) $surat['id']])): ?>
                                <a class="btn btn-primary btn-sm" href="<?= e(url('surat/' . (int) $surat['id'] . '/edit')) ?>" title="Telah dikonfirmasi" aria-label="Edit surat #<?= (int) $surat['id'] ?>, telah dikonfirmasi">
                                    <?= ui_icon('pencil') ?><span class="btn-label">Edit surat #<?= (int) $surat['id'] ?></span>
                                </a>
                            <?php else: ?>
                                <a class="btn btn-secondary btn-sm" href="<?= e(url('surat/' . (int) $surat['id'] . '/edit')) ?>" title="Edit" aria-label="Edit surat #<?= (int) $surat['id'] ?>"><?= ui_icon('pencil') ?><span class="btn-label">Edit</span></a>
                            <?php endif; ?>
                            <?php if ($isAdminUser): ?>
                                <form method="post" action="<?= e(url('surat/' . (int) $surat['id'] . '/hapus')) ?>"
                                      data-confirm
                                      data-confirm-title="Hapus surat ini?"
                                      data-confirm-message="<?= e('Surat ' . ($surat['kode_pencarian'] ?: '#' . (int) $surat['no']) . ($halShort !== '' ? ' (' . $halShort . ')' : '') . ' beserta seluruh disposisinya akan dihapus dari sistem. Tindakan ini tidak dapat dibatalkan.') ?>"
                                      data-confirm-label="Hapus surat"
                                      data-confirm-tone="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $surat['id'] ?>">
                                    <button class="btn btn-quiet-danger btn-sm" type="submit" title="Hapus" aria-label="Hapus surat <?= e($surat['kode_pencarian']) ?>"><?= ui_icon('trash') ?><span class="btn-label">Hapus</span></button>
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

<?php if ($pagination['pages'] > 1): ?>
    <nav class="pagination" aria-label="Halaman daftar surat">
        <span class="pagination__summary">Menampilkan <?= number_format($firstPage, 0, ',', '.') ?>–<?= number_format($lastPage, 0, ',', '.') ?> dari <?= number_format($pagination['total'], 0, ',', '.') ?> surat</span>
        <div class="pagination__links">
            <?php if ($pagination['page'] > 1): ?>
                <a class="page-link page-link--nav" href="<?= e($pageUrl($pagination['page'] - 1)) ?>" aria-label="Halaman sebelumnya"><?= ui_icon('chevron-left') ?>Sebelumnya</a>
            <?php endif; ?>
            <?php if ($pageNumbers[0] > 1): ?>
                <a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a>
                <?php if ($pageNumbers[0] > 2): ?><span class="page-gap" aria-hidden="true">&hellip;</span><?php endif; ?>
            <?php endif; ?>
            <?php foreach ($pageNumbers as $page): ?>
                <?php if ($page === $pagination['page']): ?>
                    <span class="page-link current" aria-current="page"><?= $page ?></span>
                <?php else: ?>
                    <a class="page-link" href="<?= e($pageUrl($page)) ?>"><?= $page ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($pageNumbers[count($pageNumbers) - 1] < $pagination['pages']): ?>
                <?php if ($pageNumbers[count($pageNumbers) - 1] < $pagination['pages'] - 1): ?><span class="page-gap" aria-hidden="true">&hellip;</span><?php endif; ?>
                <a class="page-link" href="<?= e($pageUrl($pagination['pages'])) ?>"><?= $pagination['pages'] ?></a>
            <?php endif; ?>
            <?php if ($pagination['page'] < $pagination['pages']): ?>
                <a class="page-link page-link--nav" href="<?= e($pageUrl($pagination['page'] + 1)) ?>" aria-label="Halaman berikutnya">Berikutnya<?= ui_icon('chevron-right') ?></a>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
<?php if ($isAdminUser): ?>
    <?php
    $exportMonths = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $exportYear = (int) date('Y');
    $exportMonth = (int) date('n');
    ?>
    <dialog class="dialog export-dialog" data-export-dialog data-search-url="<?= e(url('surat/ekspor/cari')) ?>" aria-labelledby="export-dialog-title">
        <header class="export-dialog__head">
            <div><h2 class="dialog__title" id="export-dialog-title">Ekspor surat masuk</h2><p class="field-hint">Pilih rentang waktu, lalu unduh sebagai Excel atau PDF. Isi tabel keduanya sama.</p></div>
            <button class="icon-btn" type="button" data-export-close aria-label="Tutup ekspor" title="Tutup"><?= ui_icon('x') ?></button>
        </header>
        <form method="post" action="<?= e(url('surat/ekspor/xlsx')) ?>" data-export-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="export-dialog__body">
                <fieldset class="export-period">
                    <legend class="label">Rentang waktu surat diterima</legend>
                    <div class="export-period__choices">
                        <label><input type="radio" name="range" value="week" checked><span>Per minggu</span></label>
                        <label><input type="radio" name="range" value="month"><span>Per bulan</span></label>
                        <label><input type="radio" name="range" value="all"><span>Seluruhnya</span></label>
                    </div>
                </fieldset>
                <div class="field" data-export-week>
                    <label class="label" for="export-week-date">Pilih minggu</label>
                    <input class="input" type="date" id="export-week-date" name="week_date" value="<?= e(date('Y-m-d')) ?>" data-export-week-input>
                    <div class="field-hint" data-export-week-hint>Pilih tanggal mana pun di minggu yang diinginkan (Senin sampai Minggu).</div>
                </div>
                <div class="field" data-export-month hidden>
                    <span class="label" id="export-month-label">Pilih bulan</span>
                    <div class="export-month-pick" role="group" aria-labelledby="export-month-label">
                        <select class="select" name="month_m" aria-label="Bulan">
                            <?php foreach ($exportMonths as $number => $label): ?>
                                <option value="<?= $number ?>" <?= $number === $exportMonth ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="select" name="month_y" aria-label="Tahun">
                            <?php for ($year = $exportYear; $year >= $exportYear - 5; $year--): ?>
                                <option value="<?= $year ?>" <?= $year === $exportYear ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <p class="export-dialog__hint">Surat yang dipilih berdasarkan ID di bawah akan diekspor tanpa mengikuti rentang waktu.</p>
                <div class="field">
                    <label class="label" for="export-letter-id">Cari ID surat (opsional)</label>
                    <div class="export-id-search">
                        <input class="input" type="number" id="export-letter-id" min="1" step="1" inputmode="numeric" placeholder="Contoh: 125" data-export-id>
                        <button class="btn btn-secondary" type="button" data-export-search><?= ui_icon('search') ?>Cari surat</button>
                    </div>
                </div>
                <p class="export-search-status" data-export-search-status role="status" aria-live="polite"></p>
                <ul class="export-search-results" data-export-results aria-label="Hasil pencarian surat"></ul>
                <section class="export-selected" aria-labelledby="export-selected-title">
                    <div class="export-selected__head"><h3 id="export-selected-title">Surat terpilih</h3><span data-export-selected-count>0</span></div>
                    <ul class="export-selected__list" data-export-selected-list></ul>
                </section>
            </div>
            <footer class="export-dialog__actions">
                <button class="btn btn-secondary" type="button" data-export-close>Batal</button>
                <button class="btn btn-secondary" type="submit" formaction="<?= e(url('surat/ekspor/xlsx')) ?>" name="format" value="xlsx"><?= ui_icon('file-text') ?>Unduh Excel</button>
                <button class="btn btn-primary" type="submit" formaction="<?= e(url('surat/ekspor/pdf')) ?>" name="format" value="pdf"><?= ui_icon('file-text') ?>Unduh PDF</button>
            </footer>
        </form>
    </dialog>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
