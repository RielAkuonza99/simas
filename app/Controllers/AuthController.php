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
            $identities = ['ip:' . $remoteAddress, 'account-ip:' . strtolower($username) . ':' . $remoteAddress];
            foreach ($identities as $identity) {
                if ($this->rateLimiter->isBlocked($identity)) {
                    http_response_code(429);
                    $error = 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat.';
                    break;
                }
            }

            if ($error === '' && ($username === '' || strlen($username) > 50 || (string) ($_POST['password'] ?? '') === '')) {
                $error = 'Username dan password wajib diisi.';
            } elseif ($error === '') {
                $password = (string) $_POST['password'];
                if ($this->auth->authenticate($username, $password)) {
                    foreach ($identities as $identity) {
                        $this->rateLimiter->clear($identity);
                    }
                    redirect('dashboard');
                }

                foreach ($identities as $identity) {
                    $this->rateLimiter->recordFailure($identity);
                }
                $error = 'Username atau password salah.';
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