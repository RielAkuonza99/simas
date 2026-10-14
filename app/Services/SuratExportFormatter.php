<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Format data surat untuk ekspor CSV dan PDF supaya keduanya konsisten.
 */
final class SuratExportFormatter
{
    /** Titik koma agar langsung terbaca rapi di Excel dengan pengaturan regional Indonesia. */
    public const CSV_DELIMITER = ';';

    public static function agenda(mixed $number): string
    {
        return 'B-' . str_pad((string) (int) $number, 4, '0', STR_PAD_LEFT);
    }

    public static function status(array $row): string
    {
        $stage = (int) ($row['selesai_tahap'] ?? 0);

        return $stage > 0 ? 'Selesai pada tahap ' . $stage : 'Belum selesai';
    }

    /**
     * Definisi kolom bersama untuk CSV dan Excel (judul, lebar kolom Excel, wrap, numerik).
     *
     * @return list<array{title: string, width: int, wrap?: bool, numeric?: bool}>
     */
    public static function columns(): array
    {
        return [
            ['title' => 'ID Surat', 'width' => 9],
            ['title' => 'No Agenda', 'width' => 12],
            ['title' => 'Kode Pencarian', 'width' => 19],
            ['title' => 'Tanggal Diterima', 'width' => 15],
            ['title' => 'Nomor Surat', 'width' => 16],
            ['title' => 'Tanggal Surat', 'width' => 14],
            ['title' => 'Asal Surat', 'width' => 26],
            ['title' => 'Pengusul', 'width' => 20],
            ['title' => 'Sifat', 'width' => 14],
            ['title' => 'Hal', 'width' => 40, 'wrap' => true],
            ['title' => 'Catatan Surat', 'width' => 30, 'wrap' => true],
            ['title' => 'Bukti Surat', 'width' => 28],
            ['title' => 'Status Disposisi', 'width' => 21],
            ['title' => 'Jumlah Tahap Disposisi', 'width' => 12, 'numeric' => true],
            ['title' => 'Pejabat Akhir', 'width' => 20],
            ['title' => 'Tanggal Disposisi Akhir', 'width' => 16],
            ['title' => 'Diselesaikan Pada', 'width' => 20],
            ['title' => 'Dicatat Oleh', 'width' => 15],
            ['title' => 'Bukti Disposisi Akhir', 'width' => 28],
            ['title' => 'Rincian Disposisi', 'width' => 75, 'wrap' => true],
            ['title' => 'Dibuat Oleh', 'width' => 15],
            ['title' => 'Dibuat Pada', 'width' => 20],
            ['title' => 'Diubah Pada', 'width' => 20],
        ];
    }

    /** @return list<string> */
    public static function csvHeaders(): array
    {
        return array_column(self::columns(), 'title');
    }

    /**
     * CSV: satu baris per surat, tahap disposisi dipisah " | " agar tinggi baris tetap satu baris.
     *
     * @return list<string>
     */
    public static function csvRow(array $row): array
    {
        return array_map(self::safeCell(...), self::values($row, ' | '));
    }

    /**
     * Excel: nilai apa adanya (sel teks tidak pernah dijalankan sebagai formula), tahap dipisah baris baru.
     *
     * @return list<string|int>
     */
    public static function xlsxRow(array $row): array
    {
        return array_map(
            static fn (string|int $value): string|int => is_int($value) ? $value : trim(str_replace(["\r\n", "\r"], "\n", $value)),
            self::values($row, "\n")
        );
    }

    /** @return list<string|int> */
    private static function values(array $row, string $stageSeparator): array
    {
        $dispositions = $row['disposisi'] ?? [];

        return [
            '#' . (int) $row['id'],
            self::agenda($row['no'] ?? 0),
            (string) ($row['kode_pencarian'] ?? ''),
            (string) ($row['tanggal_masuk'] ?? ''),
            (string) ($row['nomor_surat'] ?? ''),
            (string) ($row['tanggal_surat'] ?? ''),
            (string) ($row['surat_dari'] ?? ''),
            (string) ($row['pengusul'] ?? ''),
            (string) ($row['sifat'] ?? ''),
            (string) ($row['hal'] ?? ''),
            (string) ($row['catatan'] ?? ''),
            (string) ($row['link_drive'] ?? ''),
            self::status($row),
            count($dispositions),
            (string) ($row['pejabat_akhir'] ?? ''),
            (string) ($row['tanggal_akhir'] ?? ''),
            (string) ($row['selesai_pada'] ?? ''),
            (string) ($row['dicatat_oleh'] ?? ''),
            (string) ($row['link_selesai'] ?? ''),
            self::dispositionText($dispositions, $stageSeparator),
            (string) ($row['dibuat_oleh'] ?? ''),
            (string) ($row['created_at'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** Rincian semua tahap dalam satu sel. */
    public static function dispositionText(array $dispositions, string $separator = "\n"): string
    {
        $lines = [];
        foreach ($dispositions as $item) {
            $parts = [
                trim((string) ($item['tanggal'] ?? '')),
                trim((string) ($item['asal'] ?? '')) . ' → ' . trim((string) ($item['tujuan'] ?? '')),
            ];
            $note = trim((string) ($item['catatan'] ?? ''));
            if ($note !== '') {
                $parts[] = $note;
            }
            $link = trim((string) ($item['link_lampiran'] ?? ''));
            if ($link !== '') {
                $parts[] = 'Lampiran: ' . $link;
            }
            $lines[] = (int) ($item['tahap'] ?? 0) . '. ' . implode(' · ', array_filter($parts, static fn (string $part): bool => $part !== ''));
        }

        return implode($separator, $lines);
    }

    /** Rapikan teks dan cegah formula injection saat CSV dibuka di spreadsheet. */
    public static function safeCell(mixed $value): string
    {
        $text = is_scalar($value) ? trim(str_replace(["\r\n", "\r"], "\n", (string) $value)) : '';

        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $text) ? "'" . $text : $text;
    }

    /** Potong tautan panjang agar tidak merusak tata letak PDF. */
    public static function shortLink(?string $url, int $max = 60): string
    {
        $url = trim((string) $url);

        return $url === '' ? '' : mb_strimwidth($url, 0, $max, '…', 'UTF-8');
    }
}
