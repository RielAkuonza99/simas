<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\UserModel;
use App\Security\AuditLogger;
use App\Services\UserAdminService;
use DomainException;

final class UserController
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $pdo = Connection::get();
        $notice = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        render('users/index', [
            'pageTitle' => 'Pengelolaan Pengguna',
            'users' => (new UserModel($pdo))->all(),
            'notice' => $notice,
        ]);
    }

    public function edit(): void
    {
        AuthMiddleware::requireAdmin();
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        $user = $id === false ? null : (new UserModel(Connection::get()))->find((int) $id);
        if (isset($_GET['id']) && $user === null) {
            http_response_code(404);
            exit('Pengguna tidak ditemukan.');
        }

        render('users/form', [
            'pageTitle' => $user === null ? 'Tambah Pengguna' : 'Edit Pengguna',
            'userRecord' => $user,
            'errors' => [],
            'formData' => $user ?? ['username' => '', 'role' => 'pelayanan', 'is_active' => 1],
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();

        $pdo = Connection::get();
        $actorId = (int) currentUser()['id'];
        $input = $_POST;
        if (isset($_GET['id']) && !isset($input['id'])) {
            $input['id'] = $_GET['id'];
        }
        try {
            $result = (new UserAdminService(
                $pdo,
                new UserModel($pdo),
                new AuditLogger($pdo)
            ))->save($input, $actorId);
        } catch (DomainException $error) {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $user = $id === false ? null : (new UserModel($pdo))->find((int) $id);
            render('users/form', [
                'pageTitle' => $user === null ? 'Tambah Pengguna' : 'Edit Pengguna',
                'userRecord' => $user,
                'errors' => [$error->getMessage()],
                'formData' => array_merge($user ?? [], $_POST),
            ]);
            return;
        }
        if ($result['errors'] !== []) {
            $id = $result['id'];
            $user = $id === null ? null : (new UserModel($pdo))->find($id);
            render('users/form', [
                'pageTitle' => $user === null ? 'Tambah Pengguna' : 'Edit Pengguna',
                'userRecord' => $user,
                'errors' => $result['errors'],
                'formData' => array_merge($user ?? [], $_POST),
            ]);
            return;
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Data pengguna berhasil disimpan.'];
        redirect('pengguna');
    }

    public function delete(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();

        $id = filter_var($_GET['id'] ?? $_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id === false || (int) $id < 1) {
            http_response_code(400);
            exit('Pengguna yang dipilih tidak valid.');
        }

        $pdo = Connection::get();
        try {
            (new UserAdminService(
                $pdo,
                new UserModel($pdo),
                new AuditLogger($pdo)
            ))->delete((int) $id, (int) currentUser()['id']);
        } catch (DomainException $error) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error->getMessage()];
            redirect('pengguna');
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Akun pengguna berhasil dihapus dan sesinya dicabut.'];
        redirect('pengguna');
    }
}
