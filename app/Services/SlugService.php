<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SlugService
{
    public static function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item';
    }

    public static function unique(string $table, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (self::exists($table, $slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private static function exists(string $table, string $slug, ?int $ignoreId): bool
    {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        if ($ignoreId) {
            $sql .= ' AND id != :id';
        }

        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $params = ['slug' => $slug];
        if ($ignoreId) {
            $params['id'] = $ignoreId;
        }
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }
}
