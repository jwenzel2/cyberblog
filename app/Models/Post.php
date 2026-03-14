<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Post
{
    public static function recentPublished(): array
    {
        $sql = "SELECT p.*, m.public_url AS featured_image
                FROM posts p
                LEFT JOIN media m ON m.id = p.featured_media_id
                WHERE p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= UTC_TIMESTAMP())
                ORDER BY COALESCE(p.published_at, p.created_at) DESC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function allForAdmin(): array
    {
        $sql = "SELECT p.*, m.public_url AS featured_image
                FROM posts p
                LEFT JOIN media m ON m.id = p.featured_media_id
                ORDER BY p.updated_at DESC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $post = $stmt->fetch();
        if (!$post) {
            return null;
        }
        $post['category_ids'] = self::categoryIds((int) $id);
        return $post;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, m.public_url AS featured_image
             FROM posts p
             LEFT JOIN media m ON m.id = p.featured_media_id
             WHERE p.slug = :slug AND p.status = 'published'
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $post = $stmt->fetch();
        if (!$post) {
            return null;
        }
        $post['categories'] = self::categories((int) $post['id']);
        return $post;
    }

    public static function forCategory(int $categoryId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, m.public_url AS featured_image
             FROM posts p
             INNER JOIN category_post cp ON cp.post_id = p.id
             LEFT JOIN media m ON m.id = p.featured_media_id
             WHERE cp.category_id = :category_id AND p.status = 'published'
             ORDER BY COALESCE(p.published_at, p.created_at) DESC"
        );
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll();
    }

    public static function save(array $data, ?int $id = null): int
    {
        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE posts
                 SET title = :title, slug = :slug, excerpt = :excerpt, body_html = :body_html, status = :status,
                     published_at = :published_at, featured_media_id = :featured_media_id, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'excerpt' => $data['excerpt'],
                'body_html' => $data['body_html'],
                'status' => $data['status'],
                'published_at' => $data['published_at'] ?: null,
                'featured_media_id' => $data['featured_media_id'] ?: null,
                'updated_at' => now(),
            ]);
            return $id;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO posts (title, slug, excerpt, body_html, status, published_at, featured_media_id, legacy_wp_id, created_at, updated_at)
             VALUES (:title, :slug, :excerpt, :body_html, :status, :published_at, :featured_media_id, :legacy_wp_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body_html' => $data['body_html'],
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?: null,
            'featured_media_id' => $data['featured_media_id'] ?: null,
            'legacy_wp_id' => $data['legacy_wp_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function syncCategories(int $postId, array $categoryIds): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM category_post WHERE post_id = :post_id');
        $delete->execute(['post_id' => $postId]);
        $insert = $pdo->prepare('INSERT INTO category_post (post_id, category_id) VALUES (:post_id, :category_id)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            if ($categoryId > 0) {
                $insert->execute(['post_id' => $postId, 'category_id' => $categoryId]);
            }
        }
        $pdo->commit();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function findByLegacyWpId(int $legacyWpId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM posts WHERE legacy_wp_id = :legacy_wp_id LIMIT 1');
        $stmt->execute(['legacy_wp_id' => $legacyWpId]);
        return $stmt->fetch() ?: null;
    }

    public static function categories(int $postId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.* FROM categories c INNER JOIN category_post cp ON cp.category_id = c.id WHERE cp.post_id = :post_id ORDER BY c.name ASC'
        );
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }

    public static function categoryIds(int $postId): array
    {
        return array_map(
            static fn(array $row): int => (int) $row['category_id'],
            Database::connection()->query('SELECT category_id FROM category_post WHERE post_id = ' . (int) $postId)->fetchAll()
        );
    }
}
