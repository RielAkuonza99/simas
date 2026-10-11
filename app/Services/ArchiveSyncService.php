<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ArchiveSyncModel;
use App\Security\AuditLogger;
use JsonException;
use RuntimeException;

final class ArchiveSyncService
{
    public function __construct(
        private ArchiveSyncModel $queue,
        private SupabaseArchiveClient $provider,
        private AuditLogger $audit
    ) {
    }

    public function processBatch(int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Batas pemrosesan harus antara 1 dan 100 data.');
        }

        $processed = 0;
        $confirmed = 0;
        $failed = 0;
        while ($processed < $limit && ($event = $this->queue->claimNext()) !== null) {
            $processed++;
            try {
                $this->provider->send($event);
                $this->queue->markSent((int) $event['id']);
                $this->queue->markConfirmed((int) $event['id']);
                $confirmed++;
            } catch (RuntimeException | JsonException $error) {
                $this->queue->markFailed(
                    (int) $event['id'],
                    (int) $event['attempt_count'] + 1,
                    $error->getMessage()
                );
                $this->audit->record(
                    'SUPABASE_SYNC_FAILED',
                    'archive_sync',
                    (int) $event['id'],
                    null,
                    null,
                    ['attempt' => (int) $event['attempt_count'] + 1]
                );
                $failed++;
            }
        }

        return ['processed' => $processed, 'confirmed' => $confirmed, 'failed' => $failed];
    }
}
