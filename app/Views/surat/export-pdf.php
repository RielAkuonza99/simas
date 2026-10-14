<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 22px 30px; }
        body { color: #17212b; font-family: DejaVu Sans, sans-serif; font-size: 8px; line-height: 1.4; }
        h1 { margin: 0; font-size: 16px; }
        h2 { margin: 14px 0 5px; font-size: 10px; }
        p { margin: 3px 0; }
        .report-head { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #0b5e91; }
        .report-head td { padding: 0 0 8px; }
        .report-meta { text-align: right; color: #465564; }
        .letter { page-break-inside: avoid; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 4px 5px; border: 1px solid #c8d0d8; vertical-align: top; overflow-wrap: break-word; }
        th { background: #edf3f7; color: #17384d; font-weight: bold; text-align: left; }
        .details th { width: 12%; }
        .details td { width: 38%; }
        .dispositions th:nth-child(1) { width: 5%; }
        .dispositions th:nth-child(2) { width: 16%; }
        .dispositions th:nth-child(3) { width: 19%; }
        .dispositions th:nth-child(4) { width: 11%; }
        .dispositions th:nth-child(5) { width: 31%; }
        .dispositions th:nth-child(6) { width: 18%; }
        .muted { color: #465564; }
        .empty { padding: 18px; border: 1px solid #c8d0d8; text-align: center; }
    </style>
</head>
<body>
<table class="report-head">
    <tr>
        <td><h1>Ekspor Surat Masuk</h1><p>Rentang: <?= e($rangeLabel) ?></p></td>
        <td class="report-meta">Dibuat pada <?= e($generatedAt) ?><br><?= count($rows) ?> surat</td>
    </tr>
</table>
<?php if ($rows === []): ?>
    <p class="empty">Tidak ada surat untuk rentang atau ID yang dipilih.</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <?php $stage = (int) $row['selesai_tahap']; ?>
        <section class="letter">
            <h2>Surat #<?= (int) $row['id'] ?> · B-<?= str_pad((string) $row['no'], 4, '0', STR_PAD_LEFT) ?></h2>
            <table class="details">
                <tbody>
                    <tr><th>Kode pencarian</th><td><?= e($row['kode_pencarian']) ?></td><th>Tanggal diterima</th><td><?= e($row['tanggal_masuk']) ?></td></tr>
                    <tr><th>Nomor surat</th><td><?= e($row['nomor_surat']) ?></td><th>Tanggal surat</th><td><?= e($row['tanggal_surat']) ?></td></tr>
                    <tr><th>Asal surat</th><td><?= e($row['surat_dari']) ?></td><th>Pengusul</th><td><?= e($row['pengusul']) ?></td></tr>
                    <tr><th>Sifat</th><td><?= e($row['sifat']) ?></td><th>Status disposisi</th><td><?= $stage > 0 ? 'Selesai pada tahap ' . $stage : 'Belum selesai' ?></td></tr>
                    <tr><th>Hal</th><td><?= nl2br(e($row['hal'])) ?></td><th>Catatan surat</th><td><?= nl2br(e($row['catatan'])) ?></td></tr>
                    <tr><th>Bukti surat</th><td><?= e($row['link_drive']) ?></td><th>Bukti disposisi akhir</th><td><?= e($row['link_selesai']) ?></td></tr>
                    <tr><th>Pejabat akhir</th><td><?= e($row['pejabat_akhir']) ?></td><th>Tanggal disposisi akhir</th><td><?= e($row['tanggal_akhir']) ?></td></tr>
                    <tr><th>Diselesaikan pada</th><td><?= e($row['selesai_pada']) ?></td><th>Dicatat oleh</th><td><?= e($row['dicatat_oleh']) ?></td></tr>
                    <tr><th>Dibuat oleh</th><td><?= e($row['dibuat_oleh']) ?></td><th>Dibuat / diubah</th><td><?= e($row['created_at']) ?> / <?= e($row['updated_at']) ?></td></tr>
                </tbody>
            </table>
            <h2>Rincian disposisi</h2>
            <?php if ($row['disposisi'] === []): ?>
                <p class="muted">Belum ada disposisi.</p>
            <?php else: ?>
                <table class="dispositions">
                    <thead><tr><th>Tahap</th><th>Asal</th><th>Tujuan</th><th>Tanggal</th><th>Catatan</th><th>Lampiran</th></tr></thead>
                    <tbody>
                    <?php foreach ($row['disposisi'] as $disposition): ?>
                        <tr>
                            <td><?= (int) $disposition['tahap'] ?></td>
                            <td><?= e($disposition['asal']) ?></td>
                            <td><?= e($disposition['tujuan']) ?></td>
                            <td><?= e($disposition['tanggal']) ?></td>
                            <td><?= nl2br(e($disposition['catatan'])) ?></td>
                            <td><?= e($disposition['link_lampiran']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
