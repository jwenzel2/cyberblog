<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PasskeyCredential
{
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM passkeys WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByCredentialId(string $credentialId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM passkeys WHERE credential_id = :credential_id LIMIT 1');
        $stmt->execute(['credential_id' => $credentialId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO passkeys (user_id, credential_id, label, public_key_pem, transports, sign_count, aaguid, created_at, updated_at)
             VALUES (:user_id, :credential_id, :label, :public_key_pem, :transports, :sign_count, :aaguid, :created_at, :updated_at)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'credential_id' => $data['credential_id'],
            'label' => $data['label'],
            'public_key_pem' => $data['public_key_pem'],
            'transports' => $data['transports'] ?? null,
            'sign_count' => $data['sign_count'] ?? 0,
            'aaguid' => $data['aaguid'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateSignCount(int $id, int $signCount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE passkeys SET sign_count = :sign_count, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'sign_count' => $signCount,
            'updated_at' => now(),
        ]);
    }

    public static function deleteForUser(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM passkeys WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
