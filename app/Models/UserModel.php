<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use DomainException;

final class UserModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, password, role, is_active FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, role, is_active FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, username, role, is_active, created_at, updated_at FROM users ORDER BY username, id'
        );

        return $statement->fetchAll();
    }

    public function countActiveAdministrators(?int $exceptId = null): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1";
        $parameters = [];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = :username';
        $parameters = ['username' => $username];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        string $username,
        string $passwordHash,
        string $role,
        bool $active,
        ?int $actorId = null
    ): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE")->fetchAll();
            $statement = $this->pdo->prepare(
                'INSERT INTO users (username, password, role, is_active)
                 VALUES (:username, :password, :role, :is_active)'
            );
            $statement->execute([
                'username' => $username,
                'password' => $passwordHash,
                'role' => $role,
                'is_active' => (int) $active,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function update(
        int $id,
        string $username,
        string $role,
        bool $active,
        ?string $passwordHash,
        ?int $sessionActorId = null,
        ?int $exceptSessionId = null
    ): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $admins = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE")->fetchAll();
            $current = $this->pdo->prepare('SELECT role, is_active FROM users WHERE id = :id FOR UPDATE');
            $current->execute(['id' => $id]);
            $existing = $current->fetch();
            if (!$existing) {
                throw new DomainException('Pengguna tidak ditemukan.');
            }
            if ($existing['role'] === 'admin'
                && (int) $existing['is_active'] === 1
                && ($role !== 'admin' || !$active)
                && count(array_filter($admins, static fn (array $admin): bool => (int) $admin['id'] !== $id)) === 0
            ) {
                throw new DomainException('Administrator aktif terakhir tidak dapat dinonaktifkan atau diturunkan rolenya.');
            }

            $sql = 'UPDATE users SET username = :username, role = :role, is_active = :is_active';
            $parameters = [
                'username' => $username,
                'role' => $role,
                'is_active' => (int) $active,
                'id' => $id,
            ];
            if ($passwordHash !== null) {
                $sql .= ', password = :password';
                $parameters['password'] = $passwordHash;
            }
            $sql .= ' WHERE id = :id';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

            if ((!$active || $passwordHash !== null) && $sessionActorId !== null) {
                $revoke = $this->pdo->prepare(
                    'UPDATE auth_sessions
                     SET revoked_at = UTC_TIMESTAMP(), revoked_by = :actor_id, revoke_reason = :reason
                     WHERE user_id = :user_id AND revoked_at IS NULL'
                    . ($exceptSessionId === null ? '' : ' AND id <> :except_session_id')
                );
                $revokeParameters = [
                    'actor_id' => $sessionActorId,
                    'reason' => !$active ? 'account_disabled' : 'password_changed',
                    'user_id' => $id,
                ];
                if ($exceptSessionId !== null) {
                    $revokeParameters['except_session_id'] = $exceptSessionId;
                }
                $revoke->execute($revokeParameters);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $id, int $actorId): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $activeAdmins = $this->pdo->query(
                "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE"
            )->fetchAll();
            $statement = $this->pdo->prepare('SELECT role, is_active FROM users WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $user = $statement->fetch();
            if (!$user) {
                throw new DomainException('Pengguna tidak ditemukan.');
            }
            if ($user['role'] === 'admin' && (int) $user['is_active'] === 1) {
                $otherAdmins = array_filter(
                    $activeAdmins,
                    static fn (array $admin): bool => (int) $admin['id'] !== $id
                );
                if ($otherAdmins === []) {
                    throw new DomainException('Akun administrator aktif terakhir tidak dapat dihapus.');
                }
            }

            $revoke = $this->pdo->prepare(
                'UPDATE auth_sessions
                 SET revoked_at = UTC_TIMESTAMP(), revoked_by = :actor_id, revoke_reason = :reason
                 WHERE user_id = :user_id AND revoked_at IS NULL'
            );
            $revoke->execute(['actor_id' => $actorId, 'reason' => 'account_deleted', 'user_id' => $id]);
            $delete = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $delete->execute(['id' => $id]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}