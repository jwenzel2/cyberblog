<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Preference
{
    public static function all(): array
    {
        $rows = Database::connection()->query('SELECT * FROM preferences ORDER BY `key` ASC')->fetchAll();
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['key']] = $row['value'];
        }
        return $mapped;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::connection()->prepare('SELECT `value` FROM preferences WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public static function set(string $key, ?string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO preferences (`key`, `value`, updated_at)
             VALUES (:key, :value, :updated_at)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'updated_at' => now(),
        ]);
    }

    public static function setIfMissing(string $key, ?string $value): void
    {
        if (self::get($key) === null) {
            self::set($key, $value);
        }
    }
}
