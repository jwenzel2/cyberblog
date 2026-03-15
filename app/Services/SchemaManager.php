<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SchemaManager
{
    public static function migrate(): void
    {
        $pdo = Database::connection();

        self::addColumnIfMissing($pdo, 'users', 'password_hash', 'VARCHAR(255) NULL AFTER `role`');
        self::addColumnIfMissing($pdo, 'users', 'totp_secret', 'VARCHAR(64) NULL AFTER `password_hash`');
        self::addColumnIfMissing($pdo, 'users', 'totp_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`');

        self::addColumnIfMissing($pdo, 'posts', 'author_id', 'INT UNSIGNED NULL AFTER `featured_media_id`');
        self::addForeignKeyIfMissing(
            $pdo,
            'posts',
            'fk_posts_author',
            'ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `preferences` (
                `key` VARCHAR(120) PRIMARY KEY,
                `value` TEXT NULL,
                `updated_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $stmt = $pdo->prepare('UPDATE posts SET author_id = :user_id WHERE author_id IS NULL');
        $stmt->execute(['user_id' => self::defaultAdminId($pdo)]);
        $pdo->exec("UPDATE posts SET excerpt = '' WHERE excerpt IS NOT NULL AND excerpt != ''");

        PreferenceService::seedDefaults();
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        }
    }

    private static function addForeignKeyIfMissing(PDO $pdo, string $table, string $constraint, string $sql): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'constraint_name' => $constraint,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    private static function defaultAdminId(PDO $pdo): int
    {
        $id = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $id ? (int) $id : 0;
    }
}
