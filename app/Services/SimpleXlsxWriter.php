<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Penulis XLSX sederhana (satu sheet) tanpa dependensi tambahan, hanya butuh ekstensi ZIP PHP.
 * Teks disimpan sebagai string sehingga Excel tidak pernah menafsirkannya sebagai formula atau tanggal.
 *
 * Lembar terdiri dari blok judul laporan (opsional), baris judul kolom yang dibekukan,
 * lalu data dengan garis tabel dan warna selang-seling. Siap cetak: A4 landscape, muat satu halaman lebar,
 * judul kolom berulang di setiap halaman, dan nomor halaman di kaki.
 */
final class SimpleXlsxWriter
{
    private const STYLE_HEADER = 1;
    private const STYLE_TITLE = 2;
    private const STYLE_SUBTITLE = 3;
    private const STYLE_DATA_BASE = 4;
    private const STYLE_EMPTY = 12;

    public function __construct(private string $sheetName = 'Sheet1')
    {
    }

    /**
     * @param list<array{title: string, width: float|int, wrap?: bool, numeric?: bool, align?: string}> $columns
     * @param iterable<list<string|int>> $rows
     * @param array{title?: string, lines?: list<string>, empty?: string} $report Blok judul di atas tabel.
     * @return string Path file sementara; hapus setelah dikirim.
     */
    public function save(array $columns, iterable $rows, array $report = []): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP PHP tidak tersedia.');
        }
        $directory = sys_get_temp_dir();
        if (!is_dir($directory) || !is_writable($directory)) {
            $directory = dirname(__DIR__, 2) . '/storage/private';
        }
        $path = tempnam($directory, 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara.');
        }

        [$sheetXml, $headerRow, $lastRow] = $this->sheet($columns, $rows, $report);

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file XLSX.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook(count($columns), $headerRow, $lastRow));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }

    /** @return array{0: string, 1: int, 2: int} XML, nomor baris judul kolom, nomor baris data terakhir */
    private function sheet(array $columns, iterable $rows, array $report): array
    {
        $columnCount = count($columns);
        $lastColumn = $this->columnName($columnCount);
        $lines = array_values($report['lines'] ?? []);
        $hasTitle = isset($report['title']) && $report['title'] !== '';
        $titleRows = ($hasTitle ? 1 : 0) + count($lines);
        $headerRow = $titleRows === 0 ? 1 : $titleRows + 2;
        $merges = [];

        $body = '';
        $rowNumber = 0;
        if ($hasTitle) {
            $rowNumber++;
            $body .= '<row r="' . $rowNumber . '" ht="26" customHeight="1">'
                . $this->textCell('A' . $rowNumber, (string) $report['title'], self::STYLE_TITLE) . '</row>';
            $merges[] = 'A' . $rowNumber . ':' . $lastColumn . $rowNumber;
        }
        foreach ($lines as $line) {
            $rowNumber++;
            $body .= '<row r="' . $rowNumber . '" ht="16" customHeight="1">'
                . $this->textCell('A' . $rowNumber, (string) $line, self::STYLE_SUBTITLE) . '</row>';
            $merges[] = 'A' . $rowNumber . ':' . $lastColumn . $rowNumber;
        }

        $body .= '<row r="' . $headerRow . '" ht="30" customHeight="1">';
        foreach ($columns as $index => $column) {
            $body .= $this->textCell($this->columnName($index + 1) . $headerRow, (string) $column['title'], self::STYLE_HEADER);
        }
        $body .= '</row>';

        $rowNumber = $headerRow;
        $dataCount = 0;
        foreach ($rows as $row) {
            $rowNumber++;
            $zebra = $dataCount % 2 === 1;
            $dataCount++;
            $body .= '<row r="' . $rowNumber . '" ht="' . $this->rowHeight($columns, $row) . '" customHeight="1">';
            foreach ($columns as $index => $column) {
                $ref = $this->columnName($index + 1) . $rowNumber;
                $style = $this->dataStyle($column, $zebra);
                $value = $row[$index] ?? '';
                if (!empty($column['numeric']) && is_numeric($value)) {
                    $body .= '<c r="' . $ref . '" s="' . $style . '"><v>' . (int) $value . '</v></c>';
                } elseif ((string) $value === '') {
                    $body .= '<c r="' . $ref . '" s="' . $style . '"/>';
                } else {
                    $body .= $this->textCell($ref, (string) $value, $style);
                }
            }
            $body .= '</row>';
        }

        if ($dataCount === 0) {
            $rowNumber++;
            $body .= '<row r="' . $rowNumber . '" ht="28" customHeight="1">'
                . $this->textCell('A' . $rowNumber, (string) ($report['empty'] ?? 'Tidak ada data.'), self::STYLE_EMPTY) . '</row>';
            $merges[] = 'A' . $rowNumber . ':' . $lastColumn . $rowNumber;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1)
            . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . ($headerRow + 1)
            . '" sqref="A' . ($headerRow + 1) . '"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($columns as $index => $column) {
            $number = $index + 1;
            $xml .= '<col min="' . $number . '" max="' . $number . '" width="' . (float) $column['width'] . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>' . $body . '</sheetData>';
        if ($dataCount > 0) {
            $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastColumn . $rowNumber . '"/>';
        }
        if ($merges !== []) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $merge) {
                $xml .= '<mergeCell ref="' . $merge . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '<headerFooter><oddFooter>&amp;L&amp;8Laporan Surat Masuk BAPPERIDA&amp;R&amp;8Halaman &amp;P dari &amp;N</oddFooter></headerFooter>'
            . '</worksheet>';

        return [$xml, $headerRow, $dataCount === 0 ? $headerRow : $rowNumber];
    }

    /**
     * Tinggi baris dihitung dari jumlah baris teks terpanjang pada kolom wrap. Tinggi eksplisit dipakai
     * karena tiap aplikasi spreadsheet menghitung tinggi otomatis dengan cara berbeda dan sering memotong teks.
     */
    private function rowHeight(array $columns, array $row): float
    {
        $lines = 1;
        foreach ($columns as $index => $column) {
            if (empty($column['wrap'])) {
                continue;
            }
            $charsPerLine = max(4, (int) floor((float) $column['width'] * 1.05));
            $count = 0;
            foreach (explode("\n", str_replace("\r\n", "\n", (string) ($row[$index] ?? ''))) as $line) {
                $count += max(1, (int) ceil(mb_strlen($line) / $charsPerLine));
            }
            $lines = max($lines, $count);
        }

        return min(409.0, max(18.0, round($lines * 13.2 + 5, 1)));
    }

    /** Indeks gaya data: selang-seling x rata tengah x wrap. */
    private function dataStyle(array $column, bool $zebra): int
    {
        return self::STYLE_DATA_BASE
            + ($zebra ? 4 : 0)
            + (($column['align'] ?? 'left') === 'center' ? 2 : 0)
            + (!empty($column['wrap']) ? 1 : 0);
    }

    private function textCell(string $ref, string $text, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . $this->escape($text) . '</t></is></c>';
    }

    private function escape(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', str_replace("\r\n", "\n", $text)) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function safeSheetName(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $this->sheetName) ?? 'Sheet1', 0, 31);
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(int $columnCount, int $headerRow, int $lastRow): string
    {
        $name = $this->escape($this->safeSheetName());
        $quoted = "'" . str_replace("'", "''", $this->safeSheetName()) . "'";
        $quoted = $this->escape($quoted);
        $lastColumn = $this->columnName($columnCount);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames>'
            . '<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">' . $quoted . '!$A$' . $headerRow . ':$' . $lastColumn . '$' . $lastRow . '</definedName>'
            . '<definedName name="_xlnm.Print_Titles" localSheetId="0">' . $quoted . '!$' . $headerRow . ':$' . $headerRow . '</definedName>'
            . '</definedNames></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        $xfs = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
            // 1 header
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>',
            // 2 judul
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>',
            // 3 subjudul
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>',
        ];
        // 4..11 data: [polos, zebra] x [kiri, tengah] x [tanpa wrap, wrap]
        foreach ([0, 3] as $fill) {
            foreach (['left', 'center'] as $horizontal) {
                foreach ([false, true] as $wrap) {
                    $xfs[] = '<xf numFmtId="0" fontId="0" fillId="' . $fill . '" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">'
                        . '<alignment horizontal="' . $horizontal . '" vertical="top"' . ($wrap ? ' wrapText="1"' : '') . '/></xf>';
                }
            }
        }
        // 12 keadaan kosong
        $xfs[] = '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF0B3A57"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF465564"/><name val="Calibri"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B3A57"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F6FA"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFC8D0D8"/></left><right style="thin"><color rgb="FFC8D0D8"/></right>'
            . '<top style="thin"><color rgb="FFC8D0D8"/></top><bottom style="thin"><color rgb="FFC8D0D8"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
