<?php
declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestedFile = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $path, '/'));
if ($path !== '/' && is_file($requestedFile)) {
    return false;
}

require __DIR__ . '/index.php';
