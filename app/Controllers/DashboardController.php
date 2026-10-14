<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\SuratModel;
use App\Security\AuditLogger;
use App\Services\SuratService;

final class DashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $pdo = Connection::get();
        $surat = new SuratService(new SuratModel($pdo), new AuditLogger($pdo));
        $summary = $surat->dashboard();
        foreach ($summary['terbaru'] as &$item) {
            $item['driveUrl'] = $surat->safeDriveUrl($item['link_drive'] ?? null);
        }
        unset($item);
        render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'total' => $summary['total'],
            'disposisi' => $summary['disposisi'],
            'belum' => $summary['total'] - $summary['disposisi'],
            'terbaru' => $summary['terbaru'],
            'isAdmin' => (currentUser()['role'] ?? '') === 'admin',
            'canCreate' => in_array(currentUser()['role'] ?? '', ['admin', 'pelayanan'], true),
        ]);
    }
}