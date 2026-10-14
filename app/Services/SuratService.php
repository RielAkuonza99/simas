<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SuratModel;
use App\Security\AuditLogger;

final class SuratService
{
    public const SIFAT = ['Sangat Segera', 'Segera', 'Rahasia'];
    public const TUJUAN_DISPOSISI = ['Kasubbag umum', 'Seketariat', 'Kepala'];
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
            'hal' => '',
            'link_drive' => '',
            'selesai_tahap' => 0,
            'link_selesai' => '',
            'disposisi' => array_fill_keys(
                SuratModel::DISPOSISI_DEFAULT_TAHAP,
                ['pejabat' => '', 'tujuan' => [], 'tanggal' => '', 'catatan' => '']
            ),
        ];
    }

    public function formFromRecord(array $surat): array
    {
        $form = $this->emptyForm();
        foreach (['nomor_surat', 'tanggal_surat', 'tanggal_masuk', 'sifat', 'surat_dari', 'hal', 'link_drive'] as $field) {
            $form[$field] = (string) ($surat[$field] ?? $form[$field]);
        }
        $form['selesai_tahap'] = (int) ($surat['selesai_tahap'] ?? 0);
        $form['link_selesai'] = (string) ($surat['link_selesai'] ?? '');
        $stages = array_unique(array_merge(
            SuratModel::DISPOSISI_DEFAULT_TAHAP,
            array_map('intval', array_keys($surat['disposisi'] ?? []))
        ));
        sort($stages);
        $form['disposisi'] = array_fill_keys($stages, ['pejabat' => '', 'tujuan' => [], 'tanggal' => '', 'catatan' => '']);

        foreach ($surat['disposisi'] ?? [] as $tahap => $row) {
            $form['disposisi'][$tahap] = [
                'pejabat' => (string) ($row['pejabat'] ?? $row['asal'] ?? ''),
                'tujuan' => array_values(array_intersect(
                    self::TUJUAN_DISPOSISI,
                    array_map('trim', explode(',', (string) ($row['tujuan'] ?? '')))
                )),
                'tanggal' => (string) ($row['tanggal'] ?? ''),
                'catatan' => (string) ($row['catatan'] ?? ''),
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
            'hal' => $text('hal'),
            'link_drive' => $text('link_drive'),
            'selesai_tahap' => 0,
            'link_selesai' => $text('link_selesai'),
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
                ['surat_dari', 255, 'Asal surat'],
            ] as [$field, $max, $label]
        ) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[] = "{$label} maksimal {$max} karakter.";
            }
        }
        if (strlen($data['hal']) > 65535) {
            $errors[] = 'Hal surat tidak boleh melebihi batas penyimpanan.';
        }

        if ($data['link_drive'] !== '' && !$this->validDriveUrl($data['link_drive'])) {
            $errors[] = 'Link Google Drive harus berupa URL https dari drive.google.com atau docs.google.com.';
        }

        $submittedDisposisi = is_array($input['disposisi'] ?? null) ? $input['disposisi'] : [];
        if (count($submittedDisposisi) > count(SuratModel::DISPOSISI_TAHAP)) {
            $errors[] = 'Disposisi maksimal 5 tahap.';
        }
        $previousDate = '';
        foreach (SuratModel::DISPOSISI_TAHAP as $tahap) {
            $slot = $submittedDisposisi[$tahap] ?? [];
            $slot = is_array($slot) ? $slot : [];
            $row = [
                'pejabat' => is_scalar($slot['pejabat'] ?? $slot['asal'] ?? null)
                    ? trim((string) ($slot['pejabat'] ?? $slot['asal'] ?? '')) : '',
                'tanggal' => is_scalar($slot['tanggal'] ?? null) ? trim((string) $slot['tanggal']) : '',
                'catatan' => is_scalar($slot['catatan'] ?? null) ? trim((string) $slot['catatan']) : '',
                'tujuan' => [],
            ];
            if (mb_strlen($row['pejabat']) > 150) {
                $errors[] = "Disposisi {$tahap}: pejabat maksimal 150 karakter.";
            }
            if (strlen($row['catatan']) > 65535) {
                $errors[] = "Catatan disposisi {$tahap} terlalu panjang.";
            }

            $targets = $slot['tujuan'] ?? [];
            if (!is_array($targets)) {
                $targets = is_scalar($targets) ? [(string) $targets] : [];
            }
            if (count($targets) > 5) {
                $errors[] = "Disposisi {$tahap}: pilih maksimal 5 tujuan.";
            }
            foreach ($targets as $target) {
                if (!is_string($target) || !in_array($target, self::TUJUAN_DISPOSISI, true)) {
                    $errors[] = "Disposisi {$tahap}: tujuan tidak tersedia.";
                    continue;
                }
                if (!in_array($target, $row['tujuan'], true)) {
                    $row['tujuan'][] = $target;
                }
            }

            if ($row['pejabat'] === '' && $row['tujuan'] === [] && $row['tanggal'] === '' && $row['catatan'] === '') {
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

        $completedStage = filter_var($input['selesai_tahap'] ?? 0, FILTER_VALIDATE_INT);
        if ($completedStage === false || $completedStage < 0 || $completedStage > 5) {
            $errors[] = 'Tahap disposisi selesai tidak valid.';
        } else {
            $data['selesai_tahap'] = $completedStage;
            if ($completedStage > 0
                && (!isset($data['disposisi'][$completedStage])
                    || $data['disposisi'][$completedStage]['pejabat'] === ''
                    || $data['disposisi'][$completedStage]['tujuan'] === []
                    || $data['disposisi'][$completedStage]['tanggal'] === '')
            ) {
                $errors[] = 'Isi pejabat, tujuan, dan tanggal disposisi sebelum menandainya selesai.';
            }
        }
        if ($data['link_selesai'] !== '' && ($data['selesai_tahap'] === 0 || !$this->validDriveUrl($data['link_selesai']))) {
            $errors[] = 'Bukti dukung akhir hanya dapat diisi setelah disposisi selesai dengan tautan Google Drive yang valid.';
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
    
    public function deleteMany(array $ids): array
    {
        $deletedIds = $this->surat->deleteMany($ids);
        if ($deletedIds !== []) {
            $this->audit->record('BULK_DELETE', 'surat_masuk', null, (int) $_SESSION['user_id'], null, [
                'deleted_ids' => $deletedIds,
            ]);
        }
        
        return $deletedIds;
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
