<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
$pageUrl = static fn (int $page): string => url('riwayat-disposisi', ['page' => $page]);
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Riwayat Disposisi</h2>
        <p class="page-sub"><?= number_format($history['total'], 0, ',', '.') ?> surat selesai didisposisikan.</p>
    </div>
</div>
<section class="card list-card" aria-label="Riwayat disposisi akhir">
    <?php if (!$history['items']): ?>
        <?= ui_empty_state('activity', 'Belum ada disposisi selesai', 'Riwayat akan tercatat setelah suatu tahap ditandai selesai.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <thead><tr><th>Pejabat akhir</th><th>Tanggal disposisi akhir</th><th>ID Surat</th><th>No Agenda</th><th>Status</th><th>Dicatat oleh</th></tr></thead>
                <tbody>
                <?php foreach ($history['items'] as $item): ?>
                    <tr>
                        <td data-label="Pejabat akhir"><?= e($item['pejabat_akhir'] ?: '-') ?></td>
                        <td data-label="Tanggal disposisi akhir"><?= e(formatTanggal($item['tanggal_disposisi'])) ?></td>
                        <td data-label="ID Surat"><a href="<?= e(url('surat/' . (int) $item['surat_id'])) ?>">#<?= (int) $item['surat_id'] ?></a></td>
                        <td data-label="No Agenda">B-<?= str_pad((string) $item['no'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td data-label="Status"><?= ui_badge('Selesai disposisi ' . (int) $item['selesai_tahap'], 'ok', true) ?></td>
                        <td data-label="Dicatat oleh"><?= e($item['dicatat_oleh'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($history['pages'] > 1): ?>
            <nav class="pagination" aria-label="Halaman riwayat disposisi">
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