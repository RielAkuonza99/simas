<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$isAdminUser = (currentUser()['role'] ?? '') === 'admin';
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
    <?php if ($isAdminUser): ?>
        <div class="page-actions">
            <a class="btn btn-primary" href="<?= e(url('surat/tambah')) ?>"><?= ui_icon('plus') ?>Tambah Surat</a>
        </div>
    <?php endif; ?>
</div>

<form class="card card__body filter-card" method="get" action="<?= e(url('surat')) ?>" role="search">
    <div class="filter-grid">
        <div class="field">
            <label class="label" for="q">Cari surat</label>
            <div class="input-icon">
                <?= ui_icon('search') ?>
                <input id="q" class="input" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="200" placeholder="ID BAPPERIDA, agenda, nomor, pengirim, pengusul, hal" data-search-shortcut>
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
            $isAdminUser ? 'Administrator dapat menambahkan surat pertama.' : 'Hubungi administrator untuk menambahkan surat.',
            $isAdminUser ? '<a class="btn btn-primary" href="' . e(url('surat/tambah')) . '">' . ui_icon('plus') . 'Tambah Surat</a>' : ''
        ) ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table data-table--cards surat-list-table">
            <thead>
                <tr>
                    <th>ID Pencarian / Agenda</th><th>Hal</th><th>Surat Dari / Pengusul</th><th>TGL Diterima</th>
                    <th>Disposisi</th><th>Google Drive</th><th class="col-act">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $surat): ?>
                <?php
                $detailUrl = url('surat/' . (int) $surat['id']);
                $halShort = mb_strimwidth((string) ($surat['ringkas'] ?: ''), 0, 90, '…');
                ?>
                <tr>
                    <td class="c-id">
                        <span class="code"><?= e($surat['kode_pencarian'] ?: '-') ?></span>
                        <span class="cell-sub">Agenda <?= (int) $surat['no'] ?></span>
                    </td>
                    <td class="c-hal">
                        <a class="hal-link" href="<?= e($detailUrl) ?>"><?= e($surat['ringkas'] ?: '-') ?></a>
                        <div class="tags"><?= sifatBadge($surat['sifat'], true) ?></div>
                        <?php if (!empty($surat['nomor_surat'])): ?><span class="cell-sub">No Surat: <?= e($surat['nomor_surat']) ?></span><?php endif; ?>
                    </td>
                    <td class="c-from">
                        <span class="cell-primary"><?= e($surat['surat_dari'] ?: '-') ?></span>
                        <?php if (!empty($surat['pengusul'])): ?><span class="cell-sub">Pengusul: <?= e($surat['pengusul']) ?></span><?php endif; ?>
                    </td>
                    <td class="c-date nowrap"><?= e(formatTanggal($surat['tanggal_masuk'])) ?></td>
                    <td class="c-disp"><?= ui_disposisi_meter((int) $surat['jumlah_disposisi']) ?></td>
                    <td class="c-doc">
                        <?php if ($surat['driveUrl']): ?>
                            <a class="btn btn-secondary btn-sm" href="<?= e($surat['driveUrl']) ?>" target="_blank" rel="noopener noreferrer" title="Buka di Google Drive"><?= ui_icon('external') ?>Drive</a>
                        <?php else: ?>
                            <span class="muted text-sm" title="Belum ada tautan Google Drive">Belum ada tautan</span>
                        <?php endif; ?>
                    </td>
                    <td class="c-act col-act">
                        <div class="row-actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e($detailUrl) ?>" title="Detail" aria-label="Detail surat <?= e($surat['kode_pencarian']) ?>"><?= ui_icon('eye') ?><span class="btn-label">Detail</span></a>
                            <a class="btn btn-secondary btn-sm" href="<?= e(url('surat/' . (int) $surat['id'] . '/edit')) ?>" title="Edit" aria-label="Edit surat <?= e($surat['kode_pencarian']) ?>"><?= ui_icon('pencil') ?><span class="btn-label">Edit</span></a>
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
<?php require APP_ROOT . '/includes/footer.php'; ?>
