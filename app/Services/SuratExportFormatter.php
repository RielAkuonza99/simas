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

    /** @return list<string> */
    public static function csvHeaders(): array
    {
        return [
            'ID Surat', 'No Agenda', 'Kode Pencarian', 'Tanggal Diterima', 'Nomor Surat', 'Tanggal Surat',
            'Asal Surat', 'Pengusul', 'Sifat', 'Hal', 'Catatan Surat', 'Bukti Surat',
            'Status Disposisi', 'Jumlah Tahap Disposisi', 'Pejabat Akhir', 'Tanggal Disposisi Akhir',
            'Diselesaikan Pada', 'Dicatat Oleh', 'Bukti Disposisi Akhir', 'Rincian Disposisi',
            'Dibuat Oleh', 'Dibuat Pada', 'Diubah Pada',
        ];
    }

    /**
     * Satu baris per surat. Kolom urut sama dengan csvHeaders().
     *
     * @return list<string>
     */
    public static function csvRow(array $row): array
    {
        $dispositions = $row['disposisi'] ?? [];
        $values = [
            '#' . (int) $row['id'],
            self::agenda($row['no'] ?? 0),
            $row['kode_pencarian'] ?? '',
            $row['tanggal_masuk'] ?? '',
            $row['nomor_surat'] ?? '',
            $row['tanggal_surat'] ?? '',
            $row['surat_dari'] ?? '',
            $row['pengusul'] ?? '',
            $row['sifat'] ?? '',
            $row['hal'] ?? '',
            $row['catatan'] ?? '',
            $row['link_drive'] ?? '',
            self::status($row),
            count($dispositions),
            $row['pejabat_akhir'] ?? '',
            $row['tanggal_akhir'] ?? '',
            $row['selesai_pada'] ?? '',
            $row['dicatat_oleh'] ?? '',
            $row['link_selesai'] ?? '',
            self::dispositionText($dispositions),
            $row['dibuat_oleh'] ?? '',
            $row['created_at'] ?? '',
            $row['updated_at'] ?? '',
        ];

        return array_map(self::safeCell(...), $values);
    }

    /** Rincian semua tahap dalam satu sel, satu tahap per baris. */
    public static function dispositionText(array $dispositions): string
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

        return implode("\n", $lines);
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
