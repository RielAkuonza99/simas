<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class ArchiveSyncModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function enqueueUpsert(int $suratId, bool $isDeleted = false): void
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id AS surat_id, s.kode_pencarian, s.no, s.nomor_surat, s.tanggal_surat, s.tanggal_masuk,
                     s.sifat, s.surat_dari, s.hal, s.link_drive, s.selesai_tahap, s.selesai_pada,
                     s.link_selesai, u.username AS selesai_oleh_nama, d.asal AS pejabat_akhir,
                     d.tanggal AS tanggal_disposisi_akhir
             FROM surat_masuk s LEFT JOIN users u ON u.id = s.selesai_oleh
                 LEFT JOIN surat_disposisi d ON d.surat_id = s.id AND d.tahap = s.selesai_tahap
             WHERE s.id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $suratId]);
        $surat = $statement->fetch();
        if (!$surat) {
            throw new RuntimeException('Metadata surat untuk antrean arsip tidak ditemukan.');
        }

        $disposisi = $this->pdo->prepare(
            'SELECT tahap, asal, tujuan, tanggal, catatan
             FROM surat_disposisi WHERE surat_id = :id ORDER BY tahap'
        );
        $disposisi->execute(['id' => $suratId]);

        $payload = [
            'letter_id' => (int) $surat['surat_id'],
            'search_id' => $surat['kode_pencarian'],
            'agenda_number' => (int) $surat['no'],
            'nature' => $surat['sifat'],
            'letter_number' => $surat['nomor_surat'],
            'letter_date' => $surat['tanggal_surat'],
            'received_date' => $surat['tanggal_masuk'],
            'sender' => $surat['surat_dari'],
            'hal' => $surat['hal'],
            'drive_url' => $surat['link_drive'],
            'completion_stage' => (int) $surat['selesai_tahap'],
            'completion_status' => (int) $surat['selesai_tahap'] > 0
                ? 'Selesai disposisi ke-' . (int) $surat['selesai_tahap'] : 'Belum selesai',
            'completion_actor' => $surat['pejabat_akhir'],
            'completion_date' => $surat['tanggal_disposisi_akhir'],
            'completion_recorded_by' => $surat['selesai_oleh_nama'],
            'completion_recorded_at' => $surat['selesai_pada'],
            'completion_drive_url' => $surat['link_selesai'],
            'is_deleted' => $isDeleted,
            'deleted_at' => $isDeleted ? gmdate('Y-m-d\TH:i:s\Z') : null,
            'dispositions' => array_map(
                static fn (array $row): array => [
                    'stage' => (int) $row['tahap'],
                    'official' => $row['asal'],
                    'targets' => $row['tujuan'] === null ? [] : array_map('trim', explode(',', $row['tujuan'])),
                    'date' => $row['tanggal'],
                    'catatan' => $row['catatan'],
                ],
                $disposisi->fetchAll()
            ),
        ];

        $this->enqueue($suratId, (string) $surat['kode_pencarian'], 'upsert', $payload);
    }

    public function enqueueDelete(int $suratId): void
    {
        $this->enqueueUpsert($suratId, true);
    }

    public function enqueueAllCurrent(): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
                        $ids = array_map('intval', $this->pdo->query(
                                "SELECT s.id
                                 FROM surat_masuk s
                                 WHERE NOT EXISTS (
                                         SELECT 1 FROM archive_sync_queue q
                                         WHERE q.surat_id = s.id AND q.search_id = s.kode_pencarian
                                             AND q.operation = 'upsert'
                                             AND q.status IN ('PENDING', 'PROCESSING', 'SENT')
                                 )
                                     AND NOT EXISTS (
                                         SELECT 1 FROM archive_sync_queue q
                                         WHERE q.surat_id = s.id AND q.search_id = s.kode_pencarian
                                             AND q.operation = 'upsert' AND q.status = 'CONFIRMED'
                                             AND q.confirmed_at >= s.updated_at
                                 )
                                 ORDER BY s.id"
                        )->fetchAll(PDO::FETCH_COLUMN));
            foreach ($ids as $id) {
                $this->enqueueUpsert($id);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return count($ids);
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function queueBackfill(int $afterId, int $limit): array
    {
        $limit = min(max(1, $limit), 100);
        $statement = $this->pdo->prepare(
            "SELECT s.id
             FROM surat_masuk s
             WHERE s.id > :after_id
               AND NOT EXISTS (
                   SELECT 1 FROM archive_sync_queue q
                   WHERE q.surat_id = s.id
                                         AND q.search_id = s.kode_pencarian
                                         AND q.operation = 'upsert'
                     AND q.status IN ('PENDING', 'PROCESSING', 'SENT', 'FAILED')
               )
               AND NOT EXISTS (
                   SELECT 1 FROM archive_sync_queue q
                   WHERE q.surat_id = s.id
                                         AND q.search_id = s.kode_pencarian
                                         AND q.operation = 'upsert'
                     AND q.status = 'CONFIRMED'
                     AND q.confirmed_at >= s.updated_at
               )
             ORDER BY s.id ASC LIMIT :limit"
        );
        $statement->bindValue(':after_id', max(0, $afterId), PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

        foreach ($ids as $id) {
            $this->pdo->beginTransaction();
            try {
                $this->enqueueUpsert($id);
                $this->pdo->commit();
            } catch (\Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        }

        return [
            'queued' => count($ids),
            'last_id' => $ids === [] ? max(0, $afterId) : $ids[array_key_last($ids)],
        ];
    }

    /**
     * @return array{PENDING: int, PROCESSING: int, SENT: int, CONFIRMED: int, FAILED: int, SUPERSEDED: int}
     */
    public function summary(): array
    {
        $statement = $this->pdo->query(
            "SELECT current_event.status, COUNT(*) AS total
             FROM archive_sync_queue current_event
             INNER JOIN (
                 SELECT search_id, MAX(id) AS id
                 FROM archive_sync_queue
                 GROUP BY search_id
             ) newest ON newest.id = current_event.id
             GROUP BY current_event.status"
        );
        $summary = array_fill_keys(['PENDING', 'PROCESSING', 'SENT', 'CONFIRMED', 'FAILED', 'SUPERSEDED'], 0);
        foreach ($statement->fetchAll() as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    public function latest(int $limit = 50): array
    {
        $limit = min(max(1, $limit), 100);
        $statement = $this->pdo->prepare(
            'SELECT id, search_id, operation, status, attempt_count, last_attempt_at,
                    last_sent_at, confirmed_at, last_error, created_at
             FROM archive_sync_queue ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec(
                "UPDATE archive_sync_queue
                 SET status = 'FAILED', locked_at = NULL, available_at = NOW(),
                     last_error = 'Worker berhenti sebelum konfirmasi; aman dicoba ulang dengan upsert idempoten.'
                 WHERE status IN ('PROCESSING', 'SENT')
                   AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );

            $statement = $this->pdo->query(
                "SELECT id, search_id, operation, payload, attempt_count
                 FROM archive_sync_queue
                 WHERE status IN ('PENDING', 'FAILED') AND available_at <= NOW()
                 ORDER BY id ASC LIMIT 1 FOR UPDATE"
            );
            $event = $statement->fetch();
            if (!$event) {
                $this->pdo->commit();

                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE archive_sync_queue
                 SET status = 'PROCESSING', attempt_count = attempt_count + 1,
                     locked_at = NOW(), last_attempt_at = NOW(), last_error = NULL
                 WHERE id = :id"
            );
            $update->execute(['id' => $event['id']]);
            $this->pdo->commit();

            $event['payload'] = json_decode((string) $event['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($event['payload'])) {
                throw new RuntimeException('Payload antrean metadata arsip tidak valid.');
            }

            return $event;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function markSent(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE archive_sync_queue
             SET status = 'SENT', last_sent_at = NOW()
             WHERE id = :id AND status = 'PROCESSING'"
        );
        $statement->execute(['id' => $id]);
        $this->assertUpdated($statement->rowCount());
    }

    public function markConfirmed(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE archive_sync_queue
             SET status = 'CONFIRMED', confirmed_at = NOW(), locked_at = NULL
             WHERE id = :id AND status = 'SENT'"
        );
        $statement->execute(['id' => $id]);
        $this->assertUpdated($statement->rowCount());
    }

    public function markFailed(int $id, int $attempt, string $message): void
    {
        $delay = min(86400, 60 * (2 ** min(max(0, $attempt - 1), 10)));
        $statement = $this->pdo->prepare(
            "UPDATE archive_sync_queue
             SET status = 'FAILED', locked_at = NULL,
                 available_at = TIMESTAMPADD(SECOND, :delay, NOW()),
                 last_error = :error
             WHERE id = :id AND status = 'PROCESSING'"
        );
        $statement->execute([
            'delay' => $delay,
            'error' => mb_substr($message, 0, 1000),
            'id' => $id,
        ]);
        $this->assertUpdated($statement->rowCount());
    }

    public function retry(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE archive_sync_queue
             SET status = 'PENDING', available_at = NOW(), last_error = NULL
             WHERE id = :id AND status = 'FAILED'"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    private function enqueue(int $suratId, string $searchId, string $operation, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $supersede = $this->pdo->prepare(
            "UPDATE archive_sync_queue
             SET status = 'SUPERSEDED', locked_at = NULL
             WHERE search_id = :search_id AND status IN ('PENDING', 'FAILED')"
        );
        $supersede->execute(['search_id' => $searchId]);

        $statement = $this->pdo->prepare(
            'INSERT INTO archive_sync_queue
                (surat_id, search_id, operation, payload, status, available_at, created_at)
             VALUES (:surat_id, :search_id, :operation, :payload, \'PENDING\', NOW(), NOW())'
        );
        $statement->execute([
            'surat_id' => $suratId,
            'search_id' => $searchId,
            'operation' => $operation,
            'payload' => $json,
        ]);
    }

    private function assertUpdated(int $rowCount): void
    {
        if ($rowCount !== 1) {
            throw new RuntimeException('Status antrean sinkronisasi berubah sebelum dapat diperbarui.');
        }
    }
}
