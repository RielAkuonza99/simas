<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SuratModel;
use App\Security\AuditLogger;

final class SuratService
{
    public const SIFAT = ['Sangat Segera', 'Segera', 'Rahasia'];
    private const DRIVE_HOSTS = ['drive.google.com', 'docs.google.com'];

    public function __construct(private SuratModel $surat, private AuditLogger $audit)
    {
    }

    public function dashboard(): array
    {
        return $this->surat->dashboardSummary();
    }

    public function search(array $filters, int $page = 1): array
    {
        return $this->surat->search($filters, $page);
    }

    public function find(int $id): ?array
    {
        return $id > 0 ? $this->surat->find($id) : null;
    }

    public function safeDriveUrl(?string $url): ?string
    {
        return $url !== null && $this->validDriveUrl($url) ? $url : null;
    }

    public function emptyForm(): array
    {
        return [
            'nomor_surat' => '',
            'tanggal_surat' => '',
            'tanggal_masuk' => date('Y-m-d'),
            'sifat' => '',
            'surat_dari' => '',
            'pengusul' => '',
            'hal' => '',
            'catatan' => '',
            'link_drive' => '',
            'disposisi' => array_fill_keys(
                SuratModel::DISPOSISI_DEFAULT_TAHAP,
                ['asal' => '', 'tujuan' => '', 'tanggal' => '', 'catatan' => '', 'link_lampiran' => '']
            ),
        ];
    }

    public function formFromRecord(array $surat): array
    {
        $form = $this->emptyForm();
        foreach (['nomor_surat', 'tanggal_surat', 'tanggal_masuk', 'sifat', 'surat_dari', 'pengusul', 'hal', 'catatan', 'link_drive'] as $field) {
            $form[$field] = (string) ($surat[$field] ?? $form[$field]);
        }
        $stages = array_unique(array_merge(
            SuratModel::DISPOSISI_DEFAULT_TAHAP,
            array_map('intval', array_keys($surat['disposisi'] ?? []))
        ));
        sort($stages);
        $form['disposisi'] = array_fill_keys($stages, ['asal' => '', 'tujuan' => '', 'tanggal' => '', 'catatan' => '', 'link_lampiran' => '']);

        foreach ($surat['disposisi'] ?? [] as $tahap => $row) {
            $form['disposisi'][$tahap] = [
                'asal' => (string) ($row['asal'] ?? ''),
                'tujuan' => (string) ($row['tujuan'] ?? ''),
                'tanggal' => (string) ($row['tanggal'] ?? ''),
                'catatan' => (string) ($row['catatan'] ?? ''),
                'link_lampiran' => (string) ($row['link_lampiran'] ?? ''),
            ];
        }

        return $form;
    }

    public function validate(array $input): array
    {
        $text = static function (string $key) use ($input): string {
            $value = $input[$key] ?? '';

            return is_scalar($value) ? trim((string) $value) : '';
        };
        $data = [
            'nomor_surat' => $text('nomor_surat'),
            'tanggal_surat' => $text('tanggal_surat'),
            'tanggal_masuk' => $text('tanggal_masuk'),
            'sifat' => $text('sifat'),
            'surat_dari' => $text('surat_dari'),
            'pengusul' => $text('pengusul'),
            'hal' => $text('hal'),
            'catatan' => $text('catatan'),
            'link_drive' => $text('link_drive'),
            'disposisi' => [],
        ];
        $errors = [];

        if (!$this->validDate($data['tanggal_masuk'])) {
            $errors[] = 'Tanggal diterima wajib berupa tanggal yang valid.';
        }
        if ($data['tanggal_surat'] !== '' && !$this->validDate($data['tanggal_surat'])) {
            $errors[] = 'Tanggal surat tidak valid.';
        }
        if ($data['hal'] === '') {
            $errors[] = 'Hal surat wajib diisi.';
        }
        if ($data['sifat'] !== '' && !in_array($data['sifat'], self::SIFAT, true)) {
            $errors[] = 'Pilih salah satu sifat surat yang tersedia.';
        }

        foreach (
            [
                ['nomor_surat', 100, 'Nomor surat'],
                ['surat_dari', 255, 'Surat dari'],
                ['pengusul', 255, 'Pengusul'],
            ] as [$field, $max, $label]
        ) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[] = "{$label} maksimal {$max} karakter.";
            }
        }
        if (strlen($data['hal']) > 65535 || strlen($data['catatan']) > 65535) {
            $errors[] = 'Hal dan catatan tidak boleh melebihi batas penyimpanan.';
        }

        if ($data['link_drive'] !== '' && !$this->validDriveUrl($data['link_drive'])) {
            $errors[] = 'Link Google Drive harus berupa URL https dari drive.google.com atau docs.google.com.';
        }

        $submittedDisposisi = is_array($input['disposisi'] ?? null) ? $input['disposisi'] : [];
        if (count($submittedDisposisi) > count(SuratModel::DISPOSISI_TAHAP)) {
            $errors[] = 'Disposisi maksimal 5 tahap.';
        }
        $previousDate = '';
        foreach (array_slice(array_values($submittedDisposisi), 0, count(SuratModel::DISPOSISI_TAHAP)) as $slot) {
            $tahap = count($data['disposisi']) + 1;
            $slot = is_array($slot) ? $slot : [];
            $row = [];
            foreach (['asal' => 150, 'tujuan' => 150, 'tanggal' => 10, 'catatan' => 65535, 'link_lampiran' => 500] as $field => $max) {
                $value = $slot[$field] ?? '';
                $row[$field] = is_scalar($value) ? trim((string) $value) : '';
                if ($field !== 'tanggal' && $field !== 'catatan' && mb_strlen($row[$field]) > $max) {
                    $errors[] = "Disposisi {$tahap}: {$field} maksimal {$max} karakter.";
                }
                if ($field === 'catatan' && strlen($row[$field]) > $max) {
                    $errors[] = "Catatan disposisi {$tahap} terlalu panjang.";
                }
            }

            if ($row['link_lampiran'] !== '' && !$this->validDriveUrl($row['link_lampiran'])) {
                $errors[] = "Lampiran disposisi {$tahap} harus berupa tautan Google Drive yang valid.";
            }
            if (!array_filter($row, static fn (string $value): bool => $value !== '')) {
                continue;
            }

            if ($row['tanggal'] !== '') {
                if (!$this->validDate($row['tanggal'])) {
                    $errors[] = "Tanggal disposisi {$tahap} tidak valid.";
                } elseif ($previousDate !== '' && $row['tanggal'] < $previousDate) {
                    $errors[] = "Tanggal disposisi {$tahap} tidak boleh lebih awal dari disposisi sebelumnya.";
                } else {
                    $previousDate = $row['tanggal'];
                }
            }
            $data['disposisi'][$tahap] = $row;
        }

        return [$data, $errors];
    }

    public function create(array $data): int
    {
        $id = $this->surat->create($data, (int) $_SESSION['user_id']);
        $this->audit->record('CREATE', 'surat_masuk', $id, (int) $_SESSION['user_id']);

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->surat->update($id, $data);
        $this->audit->record('UPDATE', 'surat_masuk', $id, (int) $_SESSION['user_id']);
    }

    public function delete(int $id): bool
    {
        $deleted = $this->surat->delete($id);
        if ($deleted) {
            $this->audit->record('DELETE', 'surat_masuk', $id, (int) $_SESSION['user_id']);
        }

        return $deleted;
    }

    private function validDriveUrl(string $url): bool
    {
        if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::DRIVE_HOSTS, true)
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
