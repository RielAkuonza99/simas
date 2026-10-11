<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ArchiveSyncController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\SecurityController;
use App\Controllers\SettingsController;
use App\Controllers\SuratController;
use App\Controllers\UserController;
use App\Routing\Router;

$router = new Router();
$auth = static fn (string $method): Closure => static fn () => (new AuthController())->{$method}();
$archiveSync = static fn (string $method): Closure => static fn () => (new ArchiveSyncController())->{$method}();
$dashboard = static fn () => (new DashboardController())->index();
$health = static fn () => (new HealthController())->index();
$surat = static fn (string $method): Closure => static fn () => (new SuratController())->{$method}();
$security = static fn (string $method): Closure => static fn () => (new SecurityController())->{$method}();
$settings = static fn (string $method): Closure => static fn () => (new SettingsController())->{$method}();
$users = static fn (string $method): Closure => static fn () => (new UserController())->{$method}();

$router->get('/', $auth('login'));
$router->get('/health', $health);
$router->get('/login', $auth('login'));
$router->post('/login', $auth('login'));
$router->post('/logout', $auth('logout'));
$router->get('/pengaturan', $settings('index'));
$router->get('/pengaturan/developer-tools/metrics', $settings('liveMetrics'));
$router->post('/pengaturan/developer-tools/logs/clear', $settings('clearAppLog'));
$router->get('/dashboard', $dashboard);
$router->get('/sinkronisasi', $archiveSync('index'));
$router->post('/sinkronisasi/push', $archiveSync('push'));
$router->post('/sinkronisasi/proses', $archiveSync('processNext'));
$router->post('/sinkronisasi/coba-lagi', $archiveSync('retry'));

$router->get('/surat', $surat('index'));
$router->get('/surat/tambah', $surat('create'));
$router->post('/surat/tambah', $surat('create'));
$router->get('/surat/{id}', $surat('show'));
$router->get('/surat/{id}/edit', $surat('edit'));
$router->post('/surat/{id}/edit', $surat('edit'));
$router->post('/surat/{id}/hapus', $surat('delete'));

$router->get('/aktivitas', $security('activity'));
$router->post('/sesi/{session_id}/cabut', $security('revokeSession'));

$router->get('/pengguna', $users('index'));
$router->get('/pengguna/tambah', $users('edit'));
$router->get('/pengguna/{id}/edit', $users('edit'));
$router->post('/pengguna', $users('save'));
$router->post('/pengguna/{id}', $users('save'));
$router->post('/pengguna/{id}/hapus', $users('delete'));

$legacyRoutes = [
    '/index.php' => ['GET' => $auth('login')],
    '/login.php' => ['GET' => $auth('login'), 'POST' => $auth('login')],
    '/logout.php' => ['POST' => $auth('logout')],
    '/dashboard.php' => ['GET' => $dashboard],
    '/surat-masuk.php' => ['GET' => $surat('index')],
    '/tambah.php' => ['GET' => $surat('create'), 'POST' => $surat('create')],
    '/detail.php' => ['GET' => $surat('show')],
    '/edit.php' => ['GET' => $surat('edit'), 'POST' => $surat('edit')],
    '/hapus.php' => ['POST' => $surat('delete')],
    '/aktivitas.php' => ['GET' => $security('activity')],
    '/sesi-cabut.php' => ['POST' => $security('revokeSession')],
    '/pengguna.php' => ['GET' => $users('index')],
    '/pengguna-edit.php' => ['GET' => $users('edit')],
    '/pengguna-simpan.php' => ['POST' => $users('save')],
    '/pengguna-hapus.php' => ['POST' => $users('delete')],
];

foreach ($legacyRoutes as $path => $methods) {
    foreach ($methods as $method => $handler) {
        if ($method === 'GET') {
            $router->get($path, $handler);
        } else {
            $router->post($path, $handler);
        }
    }
}

return $router;
