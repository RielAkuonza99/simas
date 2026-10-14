<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Ekstensi PHP ZIP wajib diaktifkan.\n");
    exit(2);
}

$projectRoot = realpath(dirname(__DIR__));
$outputArgument = $argv[1] ?? '';
if ($projectRoot === false || $outputArgument === '') {
    fwrite(STDERR, "Penggunaan: php bin/package-release.php <path-zip>\n");
    exit(2);
}

$outputDirectory = realpath(dirname($outputArgument));
if ($outputDirectory === false || !is_dir($outputDirectory)) {
    fwrite(STDERR, "Folder tujuan ZIP harus sudah tersedia.\n");
    exit(2);
}
$outputPath = $outputDirectory . DIRECTORY_SEPARATOR . basename($outputArgument);
$excludedDirectories = [
    '.git', '.github', '.agents', '.claude', '.gemini', '.opencode',
    'anti-slop', 'database', 'github', 'build', 'dist',
];
$excludedExtensions = ['key', 'pem', 'p12', 'pfx', 'ppk', 'jks', 'sql', 'zip'];
$shouldExclude = static function (string $relativePath, string $absolutePath) use (
    $outputPath,
    $excludedDirectories,
    $excludedExtensions
): bool {
    if ($absolutePath === $outputPath) {
        return true;
    }

    $parts = explode('/', strtolower($relativePath));
    $basename = end($parts) ?: '';
    if (array_intersect($parts, $excludedDirectories) !== []) {
        return true;
    }
    foreach ($parts as $part) {
        if (str_starts_with($part, '.env')) {
            return true;
        }
    }
    if (str_starts_with($relativePath, 'storage/logs/') || str_starts_with($relativePath, 'storage/private/')) {
        return true;
    }
    if (preg_match('/secret|credential|private[-_.]?key|id_rsa|id_ed25519/i', $basename)) {
        return true;
    }
    if (in_array(strtolower(pathinfo($basename, PATHINFO_EXTENSION)), $excludedExtensions, true)) {
        return true;
    }

    return str_ends_with($basename, '.md');
};

$archive = new ZipArchive();
$opened = $archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($opened !== true) {
    fwrite(STDERR, "ZIP tujuan tidak dapat dibuat.\n");
    exit(1);
}

$addedFiles = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink()) {
        continue;
    }
    $absolutePath = $file->getPathname();
    $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($projectRoot) + 1));
    if ($shouldExclude($relativePath, $absolutePath)) {
        continue;
    }
    if (!$archive->addFile($absolutePath, $relativePath)) {
        $archive->close();
        @unlink($outputPath);
        fwrite(STDERR, "Gagal menambahkan berkas ke ZIP.\n");
        exit(1);
    }
    $addedFiles++;
}

if (!$archive->close() || $addedFiles === 0) {
    @unlink($outputPath);
    fwrite(STDERR, "ZIP kosong atau gagal diselesaikan.\n");
    exit(1);
}

printf("Paket dibuat: %s (%d berkas)\n", $outputPath, $addedFiles);