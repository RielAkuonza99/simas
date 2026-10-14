<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\UserModel;
use App\Security\AuditLogger;
use App\Security\LoginRateLimiter;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $auth;
    private LoginRateLimiter $rateLimiter;

    public function __construct()
    {
        $pdo = Connection::get();
        $this->auth = new AuthService(new UserModel($pdo), new AuditLogger($pdo));
        $this->rateLimiter = new LoginRateLimiter();
    }

    public function login(): void
    {
        if (AuthMiddleware::resumeDeviceSession()) {
            redirect('dashboard');
        }

        $pageTitle = 'Login Sistem';
        $error = '';
        $username = trim((string) ($_POST['username'] ?? ''));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            requirePost();
            validateCsrf();

            $remoteAddress = AuthMiddleware::clientIp() ?? 'unknown';
            $identities = ['ip:' . $remoteAddress];
            if ($username !== '' && strlen($username) <= 50) {
                $normalizedUsername = strtolower($username);
                $identities[] = 'account-ip:' . $normalizedUsername . ':' . $remoteAddress;
                $identities[] = 'account:' . $normalizedUsername;
            }
            try {
                foreach ($identities as $identity) {
                    if ($this->rateLimiter->isBlocked($identity)) {
                        http_response_code(429);
                        $error = 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat.';
                        break;
                    }
                }
            } catch (\Throwable $exception) {
                error_log('Login rate limiter unavailable: ' . $exception->getMessage());
                http_response_code(503);
                $error = 'Login sementara tidak tersedia. Silakan coba lagi nanti.';
            }

            if ($error === '' && ($username === '' || strlen($username) > 50 || (string) ($_POST['password'] ?? '') === '')) {
                $error = 'Username dan password wajib diisi.';
            } elseif ($error === '') {
                $password = (string) $_POST['password'];
                if ($this->auth->authenticate($username, $password)) {
                    try {
                        foreach ($identities as $identity) {
                            $this->rateLimiter->clear($identity);
                        }
                    } catch (\Throwable $exception) {
                        error_log('Login rate limiter could not clear successful attempt: ' . $exception->getMessage());
                    }
                    redirect('dashboard');
                }

                try {
                    foreach ($identities as $identity) {
                        $this->rateLimiter->recordFailure($identity);
                    }
                } catch (\Throwable $exception) {
                    error_log('Login rate limiter unavailable: ' . $exception->getMessage());
                    http_response_code(503);
                    $error = 'Login sementara tidak tersedia. Silakan coba lagi nanti.';
                }
                if ($error === '') {
                    $error = 'Username atau password salah.';
                }
            }
        }

        render('auth/login', [
            'pageTitle' => $pageTitle,
            'error' => $error,
            'username' => $username,
            'loginRequired' => ($_GET['status'] ?? '') === 'login_required',
        ]);
    }

    public function logout(): void
    {
        AuthMiddleware::requireLogin();
        \requirePost();
        \validateCsrf();
        $this->auth->logout();
        redirect('');
    }
}