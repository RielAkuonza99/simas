<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/**
 * Baca variabel environment tanpa bergantung pada putenv()/getenv(),
 * karena sebagian hosting bersama menonaktifkan fungsi tersebut.
 */
function app_env(string $key): string|false
{
    if (isset($_ENV[$key]) && is_string($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    if (function_exists('getenv')) {
        $value = getenv($key);
        if (is_string($value)) {
            return $value;
        }
    }
    return false;
}

/** Catat error awal (sebelum handler utama di config/security.php aktif). */
function app_early_log(string $message): void
{
    $directory = APP_ROOT . '/storage/logs';
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
    @file_put_contents(
        $directory . '/app.log',
        '[' . date(DATE_ATOM) . '] ' . $message . "\n",
        FILE_APPEND
    );
}

function app_render_error_page(int $statusCode, string $referenceId = ''): void
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? str_replace(["\r", "\n", "\0"], '', $requestPath) : '/';
    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $requestMethod = preg_replace('/[^A-Z0-9_-]/', '', $requestMethod) ?: 'UNKNOWN';
    $occurredAt = date(DATE_ATOM);
    $homeUrl = defined('APP_BASE_URL') ? APP_BASE_URL . '/' : '/';
    $logPath = APP_ROOT . '/storage/logs/app.log';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $template = $statusCode === 404 ? '404.php' : '500.php';

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        if ($referenceId !== '') {
            header('X-Request-ID: ' . $referenceId);
        }
    }

    http_response_code($statusCode);
    require APP_ROOT . '/app/Views/errors/' . $template;
}

function app_handle_exception(Throwable $error): void
{
    try {
        $referenceId = strtoupper(bin2hex(random_bytes(6)));
    } catch (Throwable) {
        $referenceId = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 12));
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? str_replace(["\r", "\n", "\0"], '', $requestPath) : '/';
    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
    $requestMethod = preg_replace('/[^A-Z0-9_-]/', '', $requestMethod) ?: 'UNKNOWN';
    app_early_log(sprintf(
        "ERROR ref=%s request=%s %s\n%s in %s:%d\n%s",
        $referenceId,
        $requestMethod,
        $requestPath,
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString()
    ));

    if (PHP_SAPI !== 'cli') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        app_render_error_page(500, $referenceId);
    }
}

set_exception_handler(static function (Throwable $error): void {
    app_handle_exception($error);
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_early_log(sprintf('FATAL: %s in %s:%d', $last['message'], $last['file'], $last['line']));
    }
});

$envFile = APP_ROOT . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('File .env tidak dapat dibaca.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        $separator = strpos($line, '=');
        if ($separator === false) {
            throw new RuntimeException('File .env berisi baris yang tidak valid. Gunakan format KEY=VALUE.');
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new RuntimeException('File .env berisi nama variabel yang tidak valid.');
        }
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }

        if (app_env($key) === false) {
            $_ENV[$key] = $value;
            if (function_exists('putenv')) {
                @putenv($key . '=' . $value);
            }
        }
    }
}
require_once APP_ROOT . '/app/Helpers/functions.php';
require_once APP_ROOT . '/config/app.php';

$missingRequiredSettings = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
    $value = app_env($key);
    if (APP_ENVIRONMENT === 'production' && (trim((string) ($value === false ? '' : $value)) === '')) {
        $missingRequiredSettings[] = $key;
    }
}
if ($missingRequiredSettings !== []) {
    throw new RuntimeException('Konfigurasi production belum lengkap: ' . implode(', ', $missingRequiredSettings) . '. Isi file .env sebelum deploy.');
}

require_once APP_ROOT . '/config/security.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
