<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use RuntimeException;

final class FeedbackUploadService
{
    public const MAX_FILES = 5;
    public const MAX_BYTES = 5242880;
    public const MAX_TOTAL_BYTES = 12582912;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @return array{filename: string, path: string, mime: string}|null */
    public function store(array $upload): ?array
    {
        $photos = $this->storeMany([
            'name' => [$upload['name'] ?? ''],
            'type' => [$upload['type'] ?? ''],
            'tmp_name' => [$upload['tmp_name'] ?? ''],
            'error' => [$upload['error'] ?? UPLOAD_ERR_NO_FILE],
            'size' => [$upload['size'] ?? 0],
        ]);

        return $photos[0] ?? null;
    }

    /** @return list<array{filename: string, path: string, mime: string}> */
    public function storeMany(array $uploads): array
    {
        $errors = $uploads['error'] ?? [];
        if (!is_array($errors)) {
            throw new DomainException('Data unggahan gambar tidak valid.');
        }

        $indexes = array_keys(array_filter(
            $errors,
            static fn (mixed $error): bool => (int) $error !== UPLOAD_ERR_NO_FILE
        ));
        if (count($indexes) > self::MAX_FILES) {
            throw new DomainException('Maksimal 5 foto untuk satu feedback.');
        }
        if ($indexes === []) {
            return [];
        }

        $stored = [];
        $totalBytes = 0;
        try {
            foreach ($indexes as $index) {
                $upload = [
                    'name' => $uploads['name'][$index] ?? '',
                    'type' => $uploads['type'][$index] ?? '',
                    'tmp_name' => $uploads['tmp_name'][$index] ?? '',
                    'error' => (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($uploads['size'][$index] ?? 0),
                ];
                $photo = $this->storeOne($upload);
                $stored[] = $photo;
                $totalBytes += filesize($photo['path']) ?: 0;
                if ($totalBytes > self::MAX_TOTAL_BYTES) {
                    throw new DomainException('Total ukuran semua foto maksimal 12 MB.');
                }
            }
        } catch (\Throwable $error) {
            $this->remove(array_column($stored, 'filename'));
            throw $error;
        }

        return $stored;
    }

    /** @return array{filename: string, path: string, mime: string} */
    private function storeOne(array $upload): array
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'Ukuran satu foto maksimal 5 MB atau batas unggahan server terlampaui.'
                : 'Foto tidak berhasil diunggah. Coba pilih file lagi.');
        }

        $temporaryPath = $upload['tmp_name'] ?? '';
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new DomainException('File foto tidak valid.');
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('Ukuran satu foto maksimal 5 MB.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($temporaryPath);
        $imageInfo = @getimagesize($temporaryPath);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])
            || !is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
            throw new DomainException('Format foto harus JPEG, PNG, atau WebP.');
        }

        $directory = APP_ROOT . '/storage/private/developer-feedback';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Penyimpanan foto feedback tidak tersedia.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $path = $directory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $path)) {
            throw new RuntimeException('Foto feedback tidak dapat disimpan.');
        }
        @chmod($path, 0600);

        return ['filename' => $filename, 'path' => $path, 'mime' => $mime];
    }

    public function pathFor(?string $filename): ?string
    {
        if ($filename === null || preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $filename) !== 1) {
            return null;
        }

        return APP_ROOT . '/storage/private/developer-feedback/' . $filename;
    }

    public function mimeFor(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    public function remove(string|array|null $filenames): void
    {
        foreach (is_array($filenames) ? $filenames : [$filenames] as $filename) {
            if (!is_string($filename)) {
                continue;
            }
            $path = $this->pathFor($filename);
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }
}