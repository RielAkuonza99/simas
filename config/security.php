<?php
declare(strict_types=1);

set_exception_handler(static function (Throwable $error): void {
    $logDirectory = APP_ROOT . '/storage/logs';
    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0750, true);
    }

    error_log(
        sprintf("[%s] %s in %s:%d\n%s\n", date(DATE_ATOM), $error->getMessage(), $error->getFile(), $error->getLine(), $error->getTraceAsString()),
        3,
        $logDirectory . '/app.log'
    );

    if (PHP_SAPI !== 'cli') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        echo 'Terjadi kesalahan sistem.';
    }
});

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net data:");

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => APP_ENVIRONMENT !== 'local' || $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && ob_get_level() === 0) {
    ob_start();
}