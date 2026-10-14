<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

final class FaqController
{
    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $role = (string) (currentUser()['role'] ?? '');
        render('faq/index', [
            'pageTitle' => 'Panduan Penggunaan',
            'role' => $role,
        ]);
    }
}
