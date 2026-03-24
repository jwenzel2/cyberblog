<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LoginSession
{
    public static function create(int $userId, string $sessionId, string $ip, string $userAgent): array
    {
        $token = bin2hex(random_bytes(32));
        $createdAt = now();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));

        $stmt = Database::connection()->prepare(
            'INSERT INTO login_sessions (user_id, session_id, revocation_token, ip_address, user_agent, created_at, expires_at, revoked_at)
             VALUES (:user_id, :session_id, :revocation_token, :ip_address, :user_agent, :created_at, :expires_at, NULL)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'revocation_token' => $token,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => (int) Database::connection()->lastInsertId(),
            'session_id' => $sessionId,
            'revocation_token' => $token,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ];
    }

    public static function findActive(int $id, int $userId, string $sessionId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM login_sessions
             WHERE id = :id AND user_id = :user_id AND session_id = :session_id
               AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'session_id' => $sessionId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM login_sessions WHERE revocation_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public static function revoke(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE login_sessions
             SET revoked_at = COALESCE(revoked_at, :revoked_at)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'revoked_at' => now(),
        ]);
    }
}
