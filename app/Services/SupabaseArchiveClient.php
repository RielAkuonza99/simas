<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;
use RuntimeException;

final class SupabaseArchiveClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $table;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (app_env('SUPABASE_URL') ?: ''), '/');
        $this->apiKey = (string) (app_env('SUPABASE_SERVICE_ROLE_KEY') ?: '');
        $this->table = (string) (app_env('SUPABASE_ARCHIVE_TABLE') ?: 'archive_surat_metadata');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->validBaseUrl() && $this->validTable();
    }

    public function configurationStatus(): string
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return 'Belum dikonfigurasi';
        }
        if (!$this->validBaseUrl() || !$this->validTable()) {
            return 'Konfigurasi tidak valid';
        }

        return 'Siap';
    }

    public function previewMetadata(int $limit = 50): array
    {
        $empty = ['status' => '', 'message' => '', 'items' => []];
        if (!$this->isConfigured()) {
            return array_merge($empty, [
                'status' => $this->configurationStatus(),
                'message' => 'Isi URL dan service role key pada konfigurasi server.',
            ]);
        }

        $columns = 'letter_id,search_id,agenda_number,letter_number,letter_date,received_date,sender,nature,hal,drive_url,completion_stage,completion_status,completion_actor,completion_date,completion_recorded_by,completion_recorded_at,completion_drive_url,dispositions,is_deleted,deleted_at,archived_at';
        $query = http_build_query([
            'select' => $columns,
            'order' => 'received_date.desc,search_id.asc',
            'limit' => min(max(1, $limit), 50),
        ], '', '&', PHP_QUERY_RFC3986);
        $handle = curl_init($this->baseUrl . '/rest/v1/' . rawurlencode($this->table) . '?' . $query);
        if ($handle === false) {
            return array_merge($empty, [
                'status' => 'Tidak terhubung',
                'message' => 'Koneksi ke Supabase tidak dapat dimulai dari server.',
            ]);
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->apiKey,
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false) {
            return array_merge($empty, [
                'status' => 'Tidak terhubung',
                'message' => 'Supabase tidak merespons. Periksa koneksi jaringan server.',
            ]);
        }
        if ($statusCode === 401) {
            return array_merge($empty, [
                'status' => 'Key ditolak',
                'message' => 'Supabase menolak service role key. Periksa konfigurasi server.',
            ]);
        }
        if ($statusCode === 403) {
            return array_merge($empty, [
                'status' => 'Akses ditolak',
                'message' => 'Role key belum memiliki izin membaca tabel metadata.',
            ]);
        }
        if ($statusCode === 404) {
            return array_merge($empty, [
                'status' => 'Tabel tidak ditemukan',
                'message' => 'Periksa nama tabel dan jalankan schema metadata di Supabase.',
            ]);
        }
        if ($statusCode === 400) {
            return array_merge($empty, [
                'status' => 'Struktur tabel tidak cocok',
                'message' => 'Kolom metadata belum sesuai. Jalankan schema metadata terbaru di Supabase.',
            ]);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            return array_merge($empty, [
                'status' => 'Supabase bermasalah',
                'message' => 'Supabase mengembalikan HTTP ' . $statusCode . '.',
            ]);
        }

        try {
            $items = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return array_merge($empty, [
                'status' => 'Respons tidak valid',
                'message' => 'Supabase memberi respons yang tidak dapat dibaca.',
            ]);
        }
        if (!is_array($items)) {
            return array_merge($empty, [
                'status' => 'Respons tidak valid',
                'message' => 'Supabase memberi respons yang tidak dapat dibaca.',
            ]);
        }

        return [
            'status' => 'Terhubung',
            'message' => $items === [] ? 'Belum ada metadata pada tabel Supabase.' : 'Metadata terbaru berhasil dibaca dari Supabase.',
            'items' => $items,
        ];
    }

    public function exportMetadata(): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Konfigurasi Supabase belum siap untuk ekspor.');
        }

        $columns = 'letter_id,search_id,agenda_number,letter_number,letter_date,received_date,sender,nature,hal,drive_url,completion_stage,completion_status,completion_actor,completion_date,completion_recorded_by,completion_recorded_at,completion_drive_url,dispositions,is_deleted,deleted_at,archived_at';
        $items = [];
        $pageSize = 1000;
        $maxRows = 100000;
        $offset = 0;
        while ($offset < $maxRows) {
            $query = http_build_query([
                'select' => $columns,
                'order' => 'search_id.asc',
                'limit' => $pageSize,
            ], '', '&', PHP_QUERY_RFC3986);
            $handle = curl_init($this->baseUrl . '/rest/v1/' . rawurlencode($this->table) . '?' . $query);
            if ($handle === false) {
                throw new RuntimeException('Ekspor metadata tidak dapat dimulai.');
            }
            $rangeEnd = $offset + $pageSize - 1;
            $contentRange = '';
            curl_setopt_array($handle, [
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $this->apiKey,
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: application/json',
                    'Range-Unit: items',
                    'Range: ' . $offset . '-' . $rangeEnd,
                    'Prefer: count=exact',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$contentRange): int {
                    if (stripos($line, 'Content-Range:') === 0) {
                        $contentRange = trim(substr($line, strlen('Content-Range:')));
                    }
                    return strlen($line);
                },
            ]);

            $response = curl_exec($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($statusCode === 416 && $offset > 0) {
                return $items;
            }
            if ($response === false || $statusCode < 200 || $statusCode >= 300) {
                throw new RuntimeException('Supabase gagal mengirim data ekspor.');
            }
            $page = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($page) || !array_is_list($page)) {
                throw new RuntimeException('Format data ekspor Supabase tidak valid.');
            }
            if ($page === []) {
                return $items;
            }
            $items = array_merge($items, $page);
            $offset += count($page);
            if (preg_match('/\/(\d+)$/', $contentRange, $match) === 1 && (int) $match[1] <= $offset) {
                return $items;
            }
        }

        throw new RuntimeException('Ekspor dibatasi sampai 100.000 baris per unduhan.');
    }

    /**
     * @throws JsonException
     */
    public function send(array $event): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Konfigurasi Supabase belum lengkap atau tidak valid.');
        }

        $endpoint = $this->baseUrl . '/rest/v1/' . rawurlencode($this->table);
        $method = strtoupper((string) ($event['operation'] ?? ''));
        $searchId = (string) ($event['search_id'] ?? '');
        if ($searchId === '') {
            throw new RuntimeException('ID pencarian pada antrean metadata tidak tersedia.');
        }

        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        if ($method === 'UPSERT') {
            $payload = $event['payload'] ?? null;
            if (!is_array($payload) || ($payload['search_id'] ?? null) !== $searchId) {
                throw new RuntimeException('Payload antrean metadata tidak sesuai dengan ID pencarian.');
            }
            $endpoint .= '?on_conflict=search_id';
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Prefer: resolution=merge-duplicates,return=representation';
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } elseif ($method === 'DELETE') {
            $endpoint .= '?search_id=eq.' . rawurlencode($searchId);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Prefer: return=representation';
            $body = json_encode([
                'is_deleted' => true,
                'deleted_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ], JSON_THROW_ON_ERROR);
        } else {
            throw new RuntimeException('Operasi antrean metadata tidak dikenal.');
        }

        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw new RuntimeException('Koneksi ke provider metadata tidak dapat dimulai.');
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method === 'UPSERT' ? 'POST' : 'PATCH',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException('Koneksi provider metadata gagal: ' . ($curlError ?: 'kesalahan transport.'));
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Provider metadata mengembalikan HTTP ' . $status . '.');
        }

        if ($method === 'UPSERT') {
            $rows = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || !isset($rows[0]['search_id']) || $rows[0]['search_id'] !== $searchId) {
                throw new RuntimeException('Provider tidak mengonfirmasi metadata surat yang dikirim.');
            }
        } else {
            $rows = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || ($rows !== [] && (!isset($rows[0]['search_id']) || $rows[0]['search_id'] !== $searchId))) {
                throw new RuntimeException('Supabase tidak mengonfirmasi pengarsipan metadata surat.');
            }
        }
    }

    private function validBaseUrl(): bool
    {
        $parts = parse_url($this->baseUrl);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }

    private function validTable(): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,62}$/', $this->table) === 1;
    }
}
