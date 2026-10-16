<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Satu definisi tabel untuk ekspor surat masuk, dipakai bersama oleh Excel dan PDF
 * supaya kedua berkas memuat kolom, urutan, dan isi yang sama persis.
 */
final class SuratExportFormatter
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function agenda(mixed $number): string
    {
        return 'B-' . str_pad((string) (int) $number, 4, '0', STR_PAD_LEFT);
    }

    public static function status(array $row): string
    {
        $stage = (int) ($row['selesai_tahap'] ?? 0);

        return $stage > 0 ? 'Selesai (disposisi ' . $stage . ')' : 'Belum selesai';
    }

    public static function date(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : \formatTanggal(substr($value, 0, 10));
    }

    /** "Oktober 2026" dari bulan 1-12 dan tahun. */
    public static function monthLabel(int $month, int $year): string
    {
        return (self::MONTHS[$month] ?? '') . ' ' . $year;
    }

    /** "12/10/2026 – 18/10/2026" dari dua tanggal Y-m-d. */
    public static function periodLabel(string $from, string $to): string
    {
        return self::date($from) . ' – ' . self::date($to);
    }

    /**
     * Kolom tabel: judul, lebar Excel (karakter), lebar PDF (persen), rata, dan wrap.
     * Jumlah lebar PDF = 100.
     *
     * @return list<array{title: string, width: int, pdf: float, align?: string, wrap?: bool, numeric?: bool}>
     */
    public static function columns(): array
    {
        return [
            ['title' => 'No', 'width' => 5, 'pdf' => 3.0, 'align' => 'center', 'numeric' => true],
            ['title' => 'ID Surat', 'width' => 9, 'pdf' => 4.0, 'align' => 'center', 'numeric' => true],
            ['title' => 'No Agenda', 'width' => 11, 'pdf' => 5.5, 'align' => 'center'],
            ['title' => 'Tgl Diterima', 'width' => 13, 'pdf' => 6.5, 'align' => 'center'],
            ['title' => 'Asal Surat', 'width' => 24, 'pdf' => 10.0, 'wrap' => true],
            ['title' => 'Nomor Surat', 'width' => 20, 'pdf' => 8.5, 'wrap' => true],
            ['title' => 'Tgl Surat', 'width' => 13, 'pdf' => 6.5, 'align' => 'center'],
            ['title' => 'Sifat', 'width' => 13, 'pdf' => 6.0, 'align' => 'center'],
            ['title' => 'Hal Surat', 'width' => 38, 'pdf' => 14.0, 'wrap' => true],
            ['title' => 'Disposisi', 'width' => 56, 'pdf' => 21.0, 'wrap' => true],
            ['title' => 'Status', 'width' => 22, 'pdf' => 9.0, 'wrap' => true],
            ['title' => 'Bukti Dukung', 'width' => 34, 'pdf' => 6.0, 'wrap' => true],
        ];
    }

    /** @return list<string> */
    public static function headers(): array
    {
        return array_column(self::columns(), 'title');
    }

    /**
     * Satu baris tabel. Nilai identik untuk Excel dan PDF; hanya penyajiannya yang berbeda.
     *
     * @return list<string>
     */
    public static function row(array $row, int $number): array
    {
        $status = self::status($row);
        if ((int) ($row['selesai_tahap'] ?? 0) > 0) {
            $final = array_filter([
                trim((string) ($row['pejabat_akhir'] ?? '')) !== '' ? 'Pejabat: ' . trim((string) $row['pejabat_akhir']) : '',
                self::date($row['tanggal_akhir'] ?? null) !== '' ? 'Tanggal: ' . self::date($row['tanggal_akhir']) : '',
            ]);
            $status .= $final === [] ? '' : "\n" . implode("\n", $final);
        }

        return [
            (string) $number,
            (string) (int) $row['id'],
            self::agenda($row['no'] ?? 0),
            self::date($row['tanggal_masuk'] ?? null),
            self::clean($row['surat_dari'] ?? ''),
            self::clean($row['nomor_surat'] ?? ''),
            self::date($row['tanggal_surat'] ?? null),
            self::clean($row['sifat'] ?? ''),
            self::clean($row['hal'] ?? ''),
            self::dispositionText($row['disposisi'] ?? []),
            $status,
            trim((string) ($row['link_drive'] ?? '')),
        ];
    }

    /**
     * Rincian semua tahap dalam satu sel:
     *   1. 12/10/2026 · Pejabat → Tujuan A, Tujuan B
     *      Catatan: ...
     */
    public static function dispositionText(array $dispositions): string
    {
        $lines = [];
        foreach ($dispositions as $item) {
            $route = array_filter([
                trim((string) ($item['asal'] ?? '')),
                trim((string) ($item['tujuan'] ?? '')),
            ], static fn (string $part): bool => $part !== '');
            $head = array_filter([
                self::date($item['tanggal'] ?? null),
                implode(' → ', $route),
            ], static fn (string $part): bool => $part !== '');
            $line = (int) ($item['tahap'] ?? 0) . '. ' . ($head === [] ? '-' : implode(' · ', $head));
            $note = self::clean($item['catatan'] ?? '');
            if ($note !== '') {
                $line .= "\n    Catatan: " . $note;
            }
            $link = trim((string) ($item['link_lampiran'] ?? ''));
            if ($link !== '') {
                $line .= "\n    Lampiran: " . $link;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** Ringkasan untuk blok judul di kedua berkas. @return array<string, int> */
    public static function summary(array $rows): array
    {
        $finished = 0;
        foreach ($rows as $row) {
            $finished += (int) ($row['selesai_tahap'] ?? 0) > 0 ? 1 : 0;
        }

        return ['Total surat' => count($rows), 'Selesai disposisi' => $finished, 'Belum selesai' => count($rows) - $finished];
    }

    /** Rapikan teks: samakan baris baru dan buang karakter kontrol. */
    public static function clean(mixed $value): string
    {
        $text = is_scalar($value) ? str_replace(["\r\n", "\r"], "\n", (string) $value) : '';

        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '');
    }
}
