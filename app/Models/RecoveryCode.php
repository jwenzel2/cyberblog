<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RecoveryCode
{
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM recovery_codes WHERE user_id = :user_id ORDER BY id ASC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function replaceForUser(int $userId, array $plainCodes): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM recovery_codes WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);

        $insert = $pdo->prepare(
            'INSERT INTO recovery_codes (user_id, code_hash, used_at, created_at) VALUES (:user_id, :code_hash, NULL, :created_at)'
        );

        foreach ($plainCodes as $plainCode) {
            $insert->execute([
                'user_id' => $userId,
                'code_hash' => password_hash($plainCode, PASSWORD_DEFAULT),
                'created_at' => now(),
            ]);
        }

        $pdo->commit();
    }

    public static function consume(int $userId, string $plainCode): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM recovery_codes WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        foreach ($stmt->fetchAll() as $code) {
            if (!password_verify($plainCode, $code['code_hash'])) {
                continue;
            }

            $update = Database::connection()->prepare('UPDATE recovery_codes SET used_at = :used_at WHERE id = :id');
            $update->execute(['id' => $code['id'], 'used_at' => now()]);
            return true;
        }

        return false;
    }
}
