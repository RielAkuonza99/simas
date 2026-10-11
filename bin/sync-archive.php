<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['limit:']);
$limit = filter_var($options['limit'] ?? 10, FILTER_VALIDATE_INT);
if ($limit === false || $limit < 1 || $limit > 100) {
    fwrite(STDERR, "Nilai --limit harus antara 1 dan 100.\n");
    exit(2);
}

try {
    $pdo = App\Database\Connection::get();
    $service = new App\Services\ArchiveSyncService(
        new App\Models\ArchiveSyncModel($pdo),
        new App\Services\SupabaseArchiveClient(),
        new App\Security\AuditLogger($pdo)
    );
    $result = $service->processBatch($limit);
    printf(
        "Diproses: %d, terkonfirmasi: %d, gagal: %d\n",
        $result['processed'],
        $result['confirmed'],
        $result['failed']
    );
    exit($result['failed'] > 0 ? 1 : 0);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
