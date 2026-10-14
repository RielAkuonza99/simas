<?php
use App\Services\SuratExportFormatter as Fmt;

$total = count($rows);
$finished = 0;
$bySifat = [];
foreach ($rows as $item) {
    $finished += (int) $item['selesai_tahap'] > 0 ? 1 : 0;
    $key = (string) ($item['sifat'] ?? '') !== '' ? (string) $item['sifat'] : 'Tanpa sifat';
    $bySifat[$key] = ($bySifat[$key] ?? 0) + 1;
}
$sifatClass = ['Sangat Segera' => 'sifat-urgent', 'Segera' => 'sifat-soon', 'Rahasia' => 'sifat-secret'];
$summary = ['Total surat' => $total, 'Selesai' => $finished, 'Belum selesai' => $total - $finished] + $bySifat;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 22px 40px; }
        body { color: #17212b; font-family: DejaVu Sans, sans-serif; font-size: 8px; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .head { margin-bottom: 8px; border-bottom: 2px solid #0b5e91; }
        .head td { padding: 0 0 7px; border: 0; }
        .head h1 { margin: 0; font-size: 15px; color: #0b3a57; }
        .head .org { margin: 2px 0 0; color: #465564; font-size: 8px; }
        .head .meta { text-align: right; color: #465564; font-size: 7.5px; line-height: 1.5; }

        .summary { margin-bottom: 10px; width: auto; }
        .summary td { padding: 4px 12px 4px 8px; border: 0; border-left: 3px solid #0b5e91; background: #f1f6fa; }
        .summary .gap { width: 6px; padding: 0; border: 0; background: none; }
        .summary b { display: block; font-size: 12px; color: #0b3a57; }
        .summary span { color: #465564; font-size: 7px; }

        .register { table-layout: fixed; }
        .register > thead { display: table-header-group; }
        .register > thead > tr > th { padding: 5px 5px; background: #0b3a57; color: #fff; font-size: 7.5px; text-align: left; border: 1px solid #0b3a57; }
        .register > tbody > tr.letter { page-break-inside: avoid; }
        .register > tbody > tr.letter > td { padding: 0; border: 0; }
        .inner { table-layout: fixed; }
        .inner > tbody > .main > td { padding: 5px 5px 4px; background: #f1f6fa; border-top: 1px solid #9fb3c2; font-size: 8px; }
        .inner > tbody > .detail > td { padding: 3px 5px 7px 14px; border-bottom: 1px solid #c8d0d8; color: #2b3a47; }
        .agenda { font-weight: bold; color: #0b3a57; }
        .sub { display: block; color: #6a7885; font-size: 6.5px; }
        .done { color: #1b6e3c; font-weight: bold; }
        .open { color: #94600a; font-weight: bold; }
        .sifat-urgent { color: #b42318; font-weight: bold; }
        .sifat-soon { color: #b54708; font-weight: bold; }
        .sifat-secret { color: #5b3fa0; font-weight: bold; }

        .facts { margin: 0 0 3px; font-size: 7.5px; color: #2b3a47; }
        .facts b { color: #465564; }
        .stages { width: 97%; margin-top: 2px; }
        .stages th { padding: 2px 4px; background: #e3ebf1; color: #17384d; font-size: 7px; text-align: left; border: 1px solid #c8d0d8; }
        .stages td { padding: 2px 4px; border: 1px solid #d5dce2; font-size: 7.5px; }
        .none { color: #6a7885; font-style: italic; }
        .empty { padding: 24px; border: 1px solid #c8d0d8; text-align: center; color: #465564; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td>
            <h1>Laporan Surat Masuk</h1>
            <p class="org">BAPPERIDA · Rentang: <?= e($rangeLabel) ?></p>
        </td>
        <td class="meta">Dibuat pada <?= e($generatedAt) ?><br><?= $total ?> surat</td>
    </tr>
</table>

<?php if ($rows === []): ?>
    <p class="empty">Tidak ada surat untuk rentang atau ID yang dipilih.</p>
<?php else: ?>
    <table class="summary">
        <tr>
            <?php foreach ($summary as $label => $count): ?>
                <td><b><?= (int) $count ?></b><span><?= e($label) ?></span></td>
                <td class="gap"></td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php $colgroup = '<colgroup><col style="width: 7%"><col style="width: 8%"><col style="width: 17%"><col style="width: 11%"><col style="width: 8%"><col style="width: 28%"><col style="width: 8%"><col style="width: 13%"></colgroup>'; ?>
    <table class="register">
        <?= $colgroup ?>
        <thead>
            <tr>
                <th>No Agenda</th><th>Diterima</th><th>Asal Surat</th><th>Nomor Surat</th>
                <th>Tgl Surat</th><th>Hal</th><th>Sifat</th><th>Status</th>
            </tr>
        </thead>
        <?php foreach ($rows as $row): ?>
            <?php
            $stage = (int) $row['selesai_tahap'];
            $facts = [
                'Pengusul' => (string) $row['pengusul'],
                'Catatan' => (string) $row['catatan'],
                'Bukti surat' => Fmt::shortLink($row['link_drive']),
            ];
            if ($stage > 0) {
                $facts += [
                    'Pejabat akhir' => (string) $row['pejabat_akhir'],
                    'Tgl disposisi akhir' => (string) $row['tanggal_akhir'],
                    'Diselesaikan' => (string) $row['selesai_pada'],
                    'Dicatat oleh' => (string) $row['dicatat_oleh'],
                    'Bukti akhir' => Fmt::shortLink($row['link_selesai']),
                ];
            }
            $facts += [
                'Dibuat oleh' => trim((string) $row['dibuat_oleh'] . ' · ' . (string) $row['created_at'], ' ·'),
            ];
            $facts = array_filter($facts, static fn (string $value): bool => trim($value) !== '');
            $hasLink = false;
            foreach ($row['disposisi'] as $disposition) {
                $hasLink = $hasLink || trim((string) $disposition['link_lampiran']) !== '';
            }
            ?>
            <tbody>
                <tr class="letter"><td colspan="8">
                <table class="inner">
                <?= $colgroup ?>
                <tbody>
                <tr class="main">
                    <td><span class="agenda"><?= e(Fmt::agenda($row['no'])) ?></span><span class="sub">ID #<?= (int) $row['id'] ?></span></td>
                    <td><?= e($row['tanggal_masuk']) ?></td>
                    <td><?= e($row['surat_dari']) ?></td>
                    <td><?= e($row['nomor_surat']) ?></td>
                    <td><?= e($row['tanggal_surat']) ?></td>
                    <td><?= nl2br(e($row['hal'])) ?></td>
                    <td class="<?= e($sifatClass[(string) $row['sifat']] ?? '') ?>"><?= e($row['sifat']) ?></td>
                    <td class="<?= $stage > 0 ? 'done' : 'open' ?>"><?= e(Fmt::status($row)) ?></td>
                </tr>
                <tr class="detail">
                    <td colspan="8">
                        <?php if ($facts !== []): ?>
                            <p class="facts">
                                <?php $parts = []; foreach ($facts as $label => $value) { $parts[] = '<b>' . e($label) . ':</b> ' . e($value); } ?>
                                <?= implode(' &nbsp;|&nbsp; ', $parts) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($row['disposisi'] === []): ?>
                            <span class="none">Belum ada disposisi.</span>
                        <?php else: ?>
                            <table class="stages">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">Tahap</th><th style="width: 10%">Tanggal</th>
                                        <th style="width: 20%">Asal</th><th style="width: 20%">Tujuan</th>
                                        <th><?= $hasLink ? 'Catatan' : 'Catatan disposisi' ?></th>
                                        <?php if ($hasLink): ?><th style="width: 22%">Lampiran</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($row['disposisi'] as $disposition): ?>
                                    <tr>
                                        <td><?= (int) $disposition['tahap'] ?></td>
                                        <td><?= e($disposition['tanggal']) ?></td>
                                        <td><?= e($disposition['asal']) ?></td>
                                        <td><?= e($disposition['tujuan']) ?></td>
                                        <td><?= nl2br(e($disposition['catatan'])) ?></td>
                                        <?php if ($hasLink): ?><td><?= e(Fmt::shortLink($disposition['link_lampiran'])) ?></td><?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
                </tbody>
                </table>
                </td></tr>
            </tbody>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
</body>
</html>
