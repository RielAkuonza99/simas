<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

function requireLogin(): void
{
    App\Middleware\AuthMiddleware::requireLogin();
}

function redirectIfLoggedIn(string $redirect = 'dashboard'): void
{
    if (App\Middleware\AuthMiddleware::resumeDeviceSession()) {
        redirect($redirect);
    }
}

function logoutUser(): void
{
    $pdo = App\Database\Connection::get();
    (new App\Services\AuthService(
        new App\Models\UserModel($pdo),
        new App\Security\AuditLogger($pdo)
    ))->logout();
}
