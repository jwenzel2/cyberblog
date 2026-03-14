<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
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
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (email, display_name, role, must_setup_auth, created_at, updated_at)
             VALUES (:email, :display_name, :role, 1, :created_at, :updated_at)'
        );
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'display_name' => trim($displayName),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) Database::connection()->lastInsertId();
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
}
