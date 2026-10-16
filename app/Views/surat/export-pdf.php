<?php
use App\Services\SuratExportFormatter as Fmt;

/** @var list<array> $rows */
/** @var string $rangeLabel */
/** @var string $generatedAt */
$columns = Fmt::columns();
$summary = Fmt::summary($rows);
$sifatClass = ['Sangat Segera' => 'sifat-urgent', 'Segera' => 'sifat-soon', 'Rahasia' => 'sifat-secret'];
$cell = static fn (string $text): string => preg_replace_callback(
    '/(^|<br \/>)( +)/',
    static fn (array $m): string => $m[1] . str_repeat('&nbsp;', strlen($m[2])),
    nl2br(e($text))
) ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 22px 40px; }
        body { color: #17212b; font-family: DejaVu Sans, sans-serif; font-size: 7.5px; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .head { margin-bottom: 8px; border-bottom: 2px solid #0b5e91; }
        .head td { padding: 0 0 7px; border: 0; }
        .head h1 { margin: 0; font-size: 15px; color: #0b3a57; }
        .head .org { margin: 2px 0 0; color: #465564; font-size: 8px; }
        .head .meta { text-align: right; color: #465564; font-size: 7.5px; line-height: 1.5; }

        .summary { width: auto; margin-bottom: 10px; }
        .summary td { padding: 4px 14px 4px 8px; border: 0; border-left: 3px solid #0b5e91; background: #f1f6fa; }
        .summary .gap { width: 6px; padding: 0; border: 0; background: none; }
        .summary b { display: block; font-size: 12px; color: #0b3a57; }
        .summary span { color: #465564; font-size: 7px; }

        .register { table-layout: fixed; }
        .register thead { display: table-header-group; }
        .register th { padding: 5px 4px; background: #0b3a57; color: #fff; font-size: 7.5px; text-align: center; border: 1px solid #0b3a57; }
        .register tr { page-break-inside: avoid; }
        .register td { padding: 4px; border: 1px solid #c8d0d8; word-wrap: break-word; }
        .register tr.alt td { background: #f1f6fa; }
        .center { text-align: center; }
        .link { font-size: 6px; color: #0b5e91; }
        .done { color: #1b6e3c; font-weight: bold; }
        .open { color: #94600a; font-weight: bold; }
        .sifat-urgent { color: #b42318; font-weight: bold; }
        .sifat-soon { color: #b54708; font-weight: bold; }
        .sifat-secret { color: #5b3fa0; font-weight: bold; }
        .empty { padding: 24px; border: 1px solid #c8d0d8; text-align: center; color: #465564; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td>
            <h1>Laporan Surat Masuk</h1>
            <p class="org">BAPPERIDA &middot; Periode: <?= e($rangeLabel) ?></p>
        </td>
        <td class="meta">Dibuat pada <?= e($generatedAt) ?><br><?= count($rows) ?> surat</td>
    </tr>
</table>

<table class="summary">
    <tr>
        <?php foreach ($summary as $label => $count): ?>
            <td><b><?= (int) $count ?></b><span><?= e($label) ?></span></td>
            <td class="gap"></td>
        <?php endforeach; ?>
    </tr>
</table>

<?php if ($rows === []): ?>
    <p class="empty">Tidak ada surat untuk periode atau ID yang dipilih.</p>
<?php else: ?>
    <table class="register">
        <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th style="width: <?= number_format((float) $column['pdf'], 1, '.', '') ?>%"><?= e($column['title']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <?php
            $values = Fmt::row($row, $index + 1);
            $finished = (int) ($row['selesai_tahap'] ?? 0) > 0;
            ?>
            <tr class="<?= $index % 2 === 1 ? 'alt' : '' ?>">
                <?php foreach ($columns as $position => $column): ?>
                    <?php
                    $value = $values[$position];
                    $classes = [];
                    if (($column['align'] ?? '') === 'center') {
                        $classes[] = 'center';
                    }
                    if ($column['title'] === 'Sifat') {
                        $classes[] = $sifatClass[$value] ?? '';
                    }
                    if ($column['title'] === 'Bukti Dukung') {
                        $classes[] = 'link';
                    }
                    ?>
                    <td class="<?= e(trim(implode(' ', array_filter($classes)))) ?>">
                        <?php if ($column['title'] === 'Status'): ?>
                            <?php [$statusLine, $statusRest] = array_pad(explode("\n", $value, 2), 2, ''); ?>
                            <span class="<?= $finished ? 'done' : 'open' ?>"><?= e($statusLine) ?></span><?= $statusRest !== '' ? '<br>' . $cell($statusRest) : '' ?>
                        <?php else: ?>
                            <?= $cell($value) ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
