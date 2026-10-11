<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['after-id::', 'limit:']);
$afterId = filter_var($options['after-id'] ?? 0, FILTER_VALIDATE_INT);
$limit = filter_var($options['limit'] ?? 100, FILTER_VALIDATE_INT);
if ($afterId === false || $afterId < 0 || $limit === false || $limit < 1 || $limit > 100) {
    fwrite(STDERR, "Gunakan --after-id dengan angka >= 0 dan --limit antara 1 dan 100.\n");
    exit(2);
}

try {
    $result = (new App\Models\ArchiveSyncModel(App\Database\Connection::get()))
        ->queueBackfill($afterId, $limit);
    printf("Masuk antrean: %d, lanjutkan dengan --after-id=%d\n", $result['queued'], $result['last_id']);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
