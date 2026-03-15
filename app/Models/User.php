<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_AUTHOR = 'author';

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM users ORDER BY created_at DESC, id DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function firstAdmin(): ?array
    {
        $stmt = Database::connection()->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public static function create(string $email, string $displayName): int
    {
        return self::createFromArray([
            'email' => $email,
            'display_name' => $displayName,
            'role' => self::ROLE_ADMIN,
            'password_hash' => null,
            'totp_secret' => null,
            'totp_enabled' => 0,
        ]);
    }

    public static function createFromArray(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (email, display_name, role, password_hash, totp_secret, totp_enabled, failed_login_attempts, lock_until, admin_unlock_required, must_setup_auth, created_at, updated_at)
             VALUES (:email, :display_name, :role, :password_hash, :totp_secret, :totp_enabled, 0, NULL, 0, :must_setup_auth, :created_at, :updated_at)'
        );
        $stmt->execute([
            'email' => mb_strtolower(trim((string) $data['email'])),
            'display_name' => trim((string) $data['display_name']),
            'role' => self::normalizeRole((string) ($data['role'] ?? self::ROLE_AUTHOR)),
            'password_hash' => $data['password_hash'] ?? null,
            'totp_secret' => $data['totp_secret'] ?? null,
            'totp_enabled' => !empty($data['totp_enabled']) ? 1 : 0,
            'must_setup_auth' => isset($data['must_setup_auth']) ? (int) (bool) $data['must_setup_auth'] : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateProfile(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET email = :email, display_name = :display_name, role = :role, must_setup_auth = :must_setup_auth, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'email' => mb_strtolower(trim((string) $data['email'])),
            'display_name' => trim((string) $data['display_name']),
            'role' => self::normalizeRole((string) ($data['role'] ?? self::ROLE_AUTHOR)),
            'must_setup_auth' => !empty($data['must_setup_auth']) ? 1 : 0,
            'updated_at' => now(),
        ]);
    }

    public static function setPassword(int $id, ?string $password): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => $password !== null && $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'updated_at' => now(),
        ]);
    }

    public static function setTotpSecret(int $id, ?string $secret, bool $enabled = false): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET totp_secret = :totp_secret, totp_enabled = :totp_enabled, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'totp_secret' => $secret,
            'totp_enabled' => $enabled ? 1 : 0,
            'updated_at' => now(),
        ]);
    }

    public static function setTotpEnabled(int $id, bool $enabled): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET totp_enabled = :totp_enabled, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'totp_enabled' => $enabled ? 1 : 0,
            'updated_at' => now(),
        ]);
    }

    public static function markAuthBootstrapComplete(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET must_setup_auth = 0, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'updated_at' => now(),
        ]);
    }

    public static function requireAuthSetup(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET must_setup_auth = 1, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'updated_at' => now(),
        ]);
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return !empty($user['password_hash']) && password_verify($password, (string) $user['password_hash']);
    }

    public static function hasRole(array $user, string ...$roles): bool
    {
        return in_array((string) $user['role'], $roles, true);
    }

    public static function admins(): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE role = :role ORDER BY id ASC");
        $stmt->execute(['role' => self::ROLE_ADMIN]);
        return $stmt->fetchAll();
    }

    public static function isTemporarilyLocked(array $user): bool
    {
        return !empty($user['lock_until']) && strtotime((string) $user['lock_until']) > time();
    }

    public static function isAdminLocked(array $user): bool
    {
        return !empty($user['admin_unlock_required']);
    }

    public static function recordLoginFailure(int $id): array
    {
        $user = self::find($id);
        if (!$user) {
            return ['state' => 'unknown'];
        }

        $attempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;
        $lockUntil = null;
        $adminUnlockRequired = 0;
        $state = 'failed';

        if ($attempts >= 7) {
            $adminUnlockRequired = 1;
            $state = 'admin_locked';
        } elseif ($attempts >= 4) {
            $lockUntil = gmdate('Y-m-d H:i:s', time() + (15 * 60));
            $state = 'temporary_lock';
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET failed_login_attempts = :failed_login_attempts, lock_until = :lock_until, admin_unlock_required = :admin_unlock_required, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'failed_login_attempts' => $attempts,
            'lock_until' => $lockUntil,
            'admin_unlock_required' => $adminUnlockRequired,
            'updated_at' => now(),
        ]);

        return [
            'state' => $state,
            'attempts' => $attempts,
            'lock_until' => $lockUntil,
        ];
    }

    public static function clearLoginFailures(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET failed_login_attempts = 0, lock_until = NULL, admin_unlock_required = 0, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'updated_at' => now(),
        ]);
    }

    public static function unlock(int $id): void
    {
        self::clearLoginFailures($id);
    }

    public static function canManagePosts(array $user): bool
    {
        return self::hasRole($user, self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_AUTHOR);
    }

    private static function normalizeRole(string $role): string
    {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_AUTHOR], true)
            ? $role
            : self::ROLE_AUTHOR;
    }
}
