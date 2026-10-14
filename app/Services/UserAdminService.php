<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Security\AuditLogger;
use DomainException;
use PDO;
use RuntimeException;

final class UserAdminService
{
    public function __construct(
        private PDO $pdo,
        private UserModel $users,
        private AuditLogger $audit
    ) {
    }

    public function save(array $input, int $actorId): array
    {
        $rawId = $input['id'] ?? null;
        $id = $rawId === null || $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT);
        $username = is_string($input['username'] ?? null) ? trim($input['username']) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $role = is_string($input['role'] ?? null) ? $input['role'] : (array_key_exists('role', $input) ? '' : 'pelayanan');
        $errors = [];
        if ($id === false) {
            return ['errors' => ['ID pengguna tidak valid.'], 'id' => null];
        }
        $id = $id === null ? null : (int) $id;
        $existing = $id === null ? null : $this->users->find($id);
        $active = $existing === null ? true : (int) $existing['is_active'] === 1;

        if ($id !== null && $existing === null) {
            return ['errors' => ['Akun yang dipilih tidak ditemukan.'], 'id' => null];
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username harus 3 sampai 50 karakter dan hanya boleh memakai huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif ($this->users->usernameExists($username, $id)) {
            $errors[] = 'Username sudah digunakan.';
        }
        if (!in_array($role, ['admin', 'pelayanan'], true)) {
            $errors[] = 'Role pengguna tidak valid.';
        }
        if ($id === null && strlen($password) < 12) {
            $errors[] = 'Password awal minimal 12 karakter.';
        } elseif ($password !== '' && strlen($password) < 12) {
            $errors[] = 'Password baru minimal 12 karakter.';
        }
        if ($id === $actorId && (!$active || $role !== 'admin')) {
            $errors[] = 'Akun administrator yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan rolenya.';
        }
        if ($existing !== null
            && $existing['role'] === 'admin'
            && (int) $existing['is_active'] === 1
            && (!$active || $role !== 'admin')
            && $this->users->countActiveAdministrators($id) === 0
        ) {
            $errors[] = 'Administrator aktif terakhir tidak dapat dinonaktifkan atau diturunkan rolenya.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => $id];
        }

        $passwordHash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Password tidak dapat diproses dengan aman.');
        }
        $this->pdo->beginTransaction();
        try {
            if ($existing === null) {
                $savedId = $this->users->create($username, (string) $passwordHash, $role, $active, $actorId);
                $this->audit->record('USER_CREATED', 'users', $savedId, $actorId, null, [
                    'username' => $username,
                    'role' => $role,
                    'is_active' => $active,
                ]);
                $this->pdo->commit();

                return ['errors' => [], 'id' => $savedId];
            }

            $changedFields = [];
            if ($existing['username'] !== $username) {
                $changedFields[] = 'username';
            }
            if ($existing['role'] !== $role) {
                $changedFields[] = 'role';
            }
            if ((int) $existing['is_active'] !== (int) $active) {
                $changedFields[] = 'is_active';
            }
            if ($passwordHash !== null) {
                $changedFields[] = 'password';
            }

            $currentSessionId = $id === $actorId ? (int) ($_SESSION['auth_session_id'] ?? 0) : null;
            $this->users->update($id, $username, $role, $active, $passwordHash, $actorId, $currentSessionId);
            if ($id === $actorId) {
                $_SESSION['user']['username'] = $username;
            }
            $this->audit->record('USER_UPDATED', 'users', $id, $actorId, null, [
                'changed_fields' => $changedFields,
            ]);
            $this->pdo->commit();

            return ['errors' => [], 'id' => $id];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new DomainException('Akun administrator yang sedang digunakan tidak dapat dihapus.');
        }
        $user = $this->users->find($id);
        if ($user === null) {
            throw new DomainException('Pengguna tidak ditemukan.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->users->delete($id, $actorId);
            $this->audit->record('USER_DELETED', 'users', $id, $actorId, null, [
                'username' => $user['username'],
            ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
