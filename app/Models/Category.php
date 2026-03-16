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
        self::assertValidParent(null, isset($data['parent_id']) ? (int) $data['parent_id'] : null);

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

    public static function update(int $id, array $data): void
    {
        self::assertValidParent($id, isset($data['parent_id']) ? (int) $data['parent_id'] : null);

        $stmt = Database::connection()->prepare(
            'UPDATE categories
             SET name = :name, slug = :slug, description = :description, parent_id = :parent_id, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?: null,
            'updated_at' => now(),
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function optionsWithDepth(): array
    {
        $options = [];
        $append = static function (array $items, int $depth = 0) use (&$append, &$options): void {
            foreach ($items as $item) {
                $item['depth'] = $depth;
                $options[] = $item;
                $append($item['children'] ?? [], $depth + 1);
            }
        };

        $append(self::tree());
        return $options;
    }

    private static function assertValidParent(?int $id, ?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = self::find($parentId);
        if (!$parent) {
            throw new \RuntimeException('Selected parent category does not exist.');
        }

        if ($id !== null && $id === $parentId) {
            throw new \RuntimeException('A category cannot be its own parent.');
        }

        $cursor = $parent;
        while ($cursor && !empty($cursor['parent_id'])) {
            if ($id !== null && (int) $cursor['parent_id'] === $id) {
                throw new \RuntimeException('Category parent relationship would create a cycle.');
            }
            $cursor = self::find((int) $cursor['parent_id']);
        }
    }
}
