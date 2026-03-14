<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
    }

    public static function tree(): array
    {
        $all = self::all();
        $byParent = [];
        foreach ($all as $category) {
            $byParent[(int) ($category['parent_id'] ?? 0)][] = $category;
        }
        return self::buildTree($byParent, 0);
    }

    private static function buildTree(array $byParent, int $parentId): array
    {
        $branch = [];
        foreach ($byParent[$parentId] ?? [] as $item) {
            $item['children'] = self::buildTree($byParent, (int) $item['id']);
            $branch[] = $item;
        }
        return $branch;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO categories (name, slug, description, parent_id, legacy_wp_term_id, created_at, updated_at)
             VALUES (:name, :slug, :description, :parent_id, :legacy_wp_term_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?: null,
            'legacy_wp_term_id' => $data['legacy_wp_term_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
