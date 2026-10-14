<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Penulis XLSX sederhana (satu sheet) tanpa dependensi tambahan, hanya butuh ekstensi ZIP PHP.
 * Semua teks disimpan sebagai string, sehingga Excel tidak pernah menafsirkannya sebagai formula atau tanggal.
 */
final class SimpleXlsxWriter
{
    private const STYLE_HEADER = 1;
    private const STYLE_TEXT = 2;
    private const STYLE_WRAP = 3;
    private const STYLE_NUMBER = 4;

    public function __construct(private string $sheetName = 'Sheet1')
    {
    }

    /**
     * @param list<array{title: string, width: float|int, wrap?: bool, numeric?: bool}> $columns
     * @param iterable<list<string|int>> $rows
     * @return string Path file sementara; hapus setelah dikirim.
     */
    public function save(array $columns, iterable $rows): string
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

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file XLSX.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($columns, $rows));
        $zip->close();

        return $path;
    }

    private function sheet(array $columns, iterable $rows): string
    {
        $lastColumn = $this->columnName(count($columns));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($columns as $index => $column) {
            $number = $index + 1;
            $xml .= '<col min="' . $number . '" max="' . $number . '" width="' . (float) $column['width'] . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData><row r="1" ht="30" customHeight="1">';
        foreach ($columns as $index => $column) {
            $xml .= $this->textCell($this->columnName($index + 1) . '1', (string) $column['title'], self::STYLE_HEADER);
        }
        $xml .= '</row>';

        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($columns as $index => $column) {
                $ref = $this->columnName($index + 1) . $rowNumber;
                $value = $row[$index] ?? '';
                if (!empty($column['numeric']) && is_numeric($value)) {
                    $xml .= '<c r="' . $ref . '" s="' . self::STYLE_NUMBER . '"><v>' . (int) $value . '</v></c>';
                } elseif ((string) $value === '') {
                    $xml .= '<c r="' . $ref . '" s="' . self::STYLE_TEXT . '"/>';
                } else {
                    $xml .= $this->textCell($ref, (string) $value, !empty($column['wrap']) ? self::STYLE_WRAP : self::STYLE_TEXT);
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData><autoFilter ref="A1:' . $lastColumn . max(1, $rowNumber) . '"/>'
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToHeight="0"/></worksheet>';
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

    private function workbook(): string
    {
        $name = $this->escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $this->sheetName) ?? 'Sheet1', 0, 31));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B3A57"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD0D7DE"/></left><right style="thin"><color rgb="FFD0D7DE"/></right>'
            . '<top style="thin"><color rgb="FFD0D7DE"/></top><bottom style="thin"><color rgb="FFD0D7DE"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
