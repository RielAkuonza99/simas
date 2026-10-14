<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatTanggal(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '-';
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if ($parsed === false || $parsed->format('Y-m-d') !== $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '-';
    }

    return $parsed->format('d/m/Y');
}

function describeUserAgent(?string $userAgent): string
{
    $userAgent = trim((string) $userAgent);
    if ($userAgent === '') {
        return 'Perangkat tidak teridentifikasi';
    }

    $application = '';
    if (preg_match('~\bCode/([\d.]+)~i', $userAgent, $matches)) {
        $application = 'Visual Studio Code ' . $matches[1];
    }

    $platform = 'Sistem operasi tidak dikenali';
    if (preg_match('~Windows NT 10\.0~i', $userAgent)) {
        $platform = 'Windows 10/11';
    } elseif (preg_match('~Windows NT 6\.3~i', $userAgent)) {
        $platform = 'Windows 8.1';
    } elseif (preg_match('~Windows NT 6\.2~i', $userAgent)) {
        $platform = 'Windows 8';
    } elseif (preg_match('~Windows NT 6\.1~i', $userAgent)) {
        $platform = 'Windows 7';
    } elseif (preg_match('~Android\s([\d.]+)(?:;\s*([^;)]+))?~i', $userAgent, $matches)) {
        $platform = 'Android ' . $matches[1];
        if (!empty($matches[2]) && !preg_match('~^(?:arm|x86|wv|build)~i', $matches[2])) {
            $platform .= ' (' . trim($matches[2]) . ')';
        }
    } elseif (preg_match('~iPhone|iPad|iPod~i', $userAgent)) {
        $device = preg_match('~iPad~i', $userAgent) ? 'iPad' : (preg_match('~iPod~i', $userAgent) ? 'iPod' : 'iPhone');
        $platform = $device;
        if (preg_match('~OS\s([\d_]+)~i', $userAgent, $matches)) {
            $platform .= ' (iOS ' . str_replace('_', '.', $matches[1]) . ')';
        }
    } elseif (preg_match('~Mac OS X|Macintosh~i', $userAgent)) {
        $platform = 'macOS';
    } elseif (preg_match('~Linux|X11~i', $userAgent)) {
        $platform = 'Linux';
    }

    if (preg_match('~(?:Win64|x64|x86_64|WOW64|arm64)~i', $userAgent)) {
        $platform .= ' 64-bit';
    } elseif (preg_match('~(?:Win32|i686|i386)~i', $userAgent)) {
        $platform .= ' 32-bit';
    }

    $runtime = '';
    if (preg_match('~Electron/([\d.]+)~i', $userAgent, $electron)) {
        $runtime = 'Electron ' . $electron[1];
        if (preg_match('~Chrome/([\d.]+)~i', $userAgent, $chromium)) {
            $runtime .= ' (Chromium ' . $chromium[1] . ')';
        }
    } elseif (preg_match('~Edg/([\d.]+)~i', $userAgent, $matches)) {
        $runtime = 'Microsoft Edge ' . $matches[1];
    } elseif (preg_match('~OPR/([\d.]+)~i', $userAgent, $matches)) {
        $runtime = 'Opera ' . $matches[1];
    } elseif (preg_match('~(?:Chrome|CriOS)/([\d.]+)~i', $userAgent, $matches)) {
        $runtime = 'Google Chrome ' . $matches[1];
    } elseif (preg_match('~Firefox/([\d.]+)~i', $userAgent, $matches)) {
        $runtime = 'Mozilla Firefox ' . $matches[1];
    } elseif (preg_match('~Version/([\d.]+).*Safari~i', $userAgent, $matches)) {
        $runtime = 'Safari ' . $matches[1];
    }

    return implode(' · ', array_filter([$application, $platform, $runtime], static fn (string $part): bool => $part !== ''));
}

function sifatBadge(?string $sifat, bool $compact = false): string
{
    $label = trim((string) ($sifat ?? ''));
    if ($label === '') {
        $label = 'Tidak diisi';
    }

    $variant = match (true) {
        in_array($label, ['Sangat Segera', 'Segera', 'Rahasia'], true) => match ($label) {
            'Sangat Segera' => ' sifat-segera',
            'Segera' => ' sifat-penting',
            'Rahasia' => ' sifat-rahasia',
        },
        default => '',
    };
    $compactClass = $compact ? ' badge-sifat-compact' : '';

    return '<span class="badge-sifat' . $variant . $compactClass . '">' . e($label) . '</span>';
}

function disposisiIndicator(int $count): string
{
    $count = min(max($count, 0), 5);

    return '<span aria-label="Disposisi ' . $count . ' dari 5 tahap">' . $count . '/5</span>';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrf(): void
{
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $knownToken = (string) ($_SESSION['csrf_token'] ?? '');

    if ($knownToken === '' || !hash_equals($knownToken, $token)) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Silakan muat ulang halaman.');
    }
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Metode permintaan tidak diizinkan.');
    }
}

function redirect(string $location, array $query = []): never
{
    header('Location: ' . url($location, $query), true, 303);
    exit;
}

/** URL aset statis dengan versi otomatis dari waktu ubah file, supaya cache browser tidak menyajikan script/CSS lama. */
function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $file = APP_ROOT . '/public/assets/' . $relative;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url('assets/' . $relative) . '?v=' . $version;
}

function url(string $path = '', array $query = []): string
{
    $relativePath = trim($path, '/');
    $url = APP_BASE_URL . ($relativePath === '' ? '/' : '/' . $relativePath);
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function requestPath(): string
{
    $path = $_SERVER['APP_ROUTE_PATH'] ?? '/';

    return is_string($path) ? $path : '/';
}

function render(string $template, array $viewData = []): void
{
    extract($viewData, EXTR_SKIP);
    require APP_ROOT . '/app/Views/' . $template . '.php';
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}