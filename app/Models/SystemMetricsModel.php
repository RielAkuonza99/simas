<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class SystemMetricsModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function dashboard(): array
    {
        $startedAt = hrtime(true);
        $this->pdo->query('SELECT 1')->fetchColumn();
        $databaseLatency = (hrtime(true) - $startedAt) / 1_000_000;

        $queueStatement = $this->pdo->query(
            "SELECT COUNT(*) AS archive_total,
                SUM(CASE WHEN latest.status IS NULL OR latest.status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN latest.status IN ('PROCESSING', 'SENT') THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN latest.status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN latest.status = 'FAILED' THEN 1 ELSE 0 END) AS failed
             FROM surat_masuk s
             LEFT JOIN (
                 SELECT current_event.surat_id, current_event.search_id, current_event.status
                 FROM archive_sync_queue current_event
                 INNER JOIN (
                     SELECT surat_id, search_id, MAX(id) AS id
                     FROM archive_sync_queue
                     WHERE operation = 'upsert' AND surat_id IS NOT NULL
                     GROUP BY surat_id, search_id
                 ) newest ON newest.id = current_event.id
             ) latest ON latest.surat_id = s.id AND latest.search_id = s.kode_pencarian"
        );
        $queueCounts = $queueStatement->fetch();
        $queue = [
            'PENDING' => (int) $queueCounts['pending'],
            'PROCESSING' => (int) $queueCounts['processing'],
            'SENT' => 0,
            'CONFIRMED' => (int) $queueCounts['confirmed'],
            'FAILED' => (int) $queueCounts['failed'],
        ];

        // Beberapa hosting bersama menonaktifkan disk_free_space()/disk_total_space().
        $diskFree = function_exists('disk_free_space') ? @disk_free_space(APP_ROOT) : false;
        $diskTotal = function_exists('disk_total_space') ? @disk_total_space(APP_ROOT) : false;
        $snapshot = [
            'php_memory_bytes' => memory_get_usage(true),
            'database_latency_ms' => round($databaseLatency, 2),
            'disk_free_bytes' => $diskFree === false ? null : (int) $diskFree,
            'disk_total_bytes' => $diskTotal === false ? null : (int) $diskTotal,
            'queue_pending' => $queue['PENDING'],
            'queue_processing' => $queue['PROCESSING'],
            'queue_failed' => $queue['FAILED'],
            'queue_confirmed' => $queue['CONFIRMED'],
            'archive_total' => (int) $queueCounts['archive_total'],
        ];

        $bucketTimestamp = intdiv(time(), 300) * 300;
        $bucket = gmdate('Y-m-d H:i:s', $bucketTimestamp);
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO system_metric_samples
                (sampled_at, php_memory_bytes, database_latency_ms, disk_free_bytes, disk_total_bytes,
                 queue_pending, queue_processing, queue_failed, queue_confirmed)
             VALUES
                (:sampled_at, :memory, :database_latency, :disk_free, :disk_total,
                 :pending, :processing, :failed, :confirmed)'
        );
        $insert->execute([
            'sampled_at' => $bucket,
            'memory' => $snapshot['php_memory_bytes'],
            'database_latency' => $snapshot['database_latency_ms'],
            'disk_free' => $snapshot['disk_free_bytes'],
            'disk_total' => $snapshot['disk_total_bytes'],
            'pending' => $snapshot['queue_pending'],
            'processing' => $snapshot['queue_processing'],
            'failed' => $snapshot['queue_failed'],
            'confirmed' => $snapshot['queue_confirmed'],
        ]);

        if ($insert->rowCount() === 1) {
            $this->pdo->exec(
                'DELETE FROM system_metric_samples
                 WHERE sampled_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
            );
        }

        $historyStatement = $this->pdo->query(
                'SELECT sampled_at, php_memory_bytes, database_latency_ms, disk_free_bytes, disk_total_bytes,
                    queue_pending, queue_processing, queue_failed, queue_confirmed
             FROM system_metric_samples
             WHERE sampled_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
             ORDER BY sampled_at DESC LIMIT 48'
        );
        $history = array_reverse($historyStatement->fetchAll());

        return [
            'current' => $snapshot,
            'history' => $history,
            'queue' => $queue,
        ];
    }
}
