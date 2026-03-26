<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Post
{
    public static function recentPublished(int $page = 1, int $perPage = 10): array
    {
        return self::paginate(
            "SELECT p.*, m.public_url AS featured_image, m.alt_text AS featured_image_alt
                FROM posts p
                LEFT JOIN media m ON m.id = p.featured_media_id
                WHERE p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= UTC_TIMESTAMP())
                ORDER BY COALESCE(p.published_at, p.created_at) DESC",
            [],
            $page,
            $perPage
        );
    }

    public static function allForAdmin(array $user, int $page = 1, int $perPage = 10): array
    {
        $sql = "SELECT p.*, m.public_url AS featured_image, u.display_name AS author_name
                FROM posts p
                LEFT JOIN media m ON m.id = p.featured_media_id
                LEFT JOIN users u ON u.id = p.author_id";
        $params = [];

        if ((string) $user['role'] === User::ROLE_AUTHOR) {
            $sql .= ' WHERE p.author_id = :author_id';
            $params['author_id'] = (int) $user['id'];
        }

        $sql .= ' ORDER BY p.updated_at DESC';

        return self::paginate($sql, $params, $page, $perPage);
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
            "SELECT p.*, m.public_url AS featured_image, m.alt_text AS featured_image_alt
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

    public static function forCategory(int $categoryId, int $page = 1, int $perPage = 10): array
    {
        return self::paginate(
            "SELECT p.*, m.public_url AS featured_image
             FROM posts p
             INNER JOIN category_post cp ON cp.post_id = p.id
             LEFT JOIN media m ON m.id = p.featured_media_id
             WHERE cp.category_id = :category_id AND p.status = 'published'
             ORDER BY COALESCE(p.published_at, p.created_at) DESC",
            ['category_id' => $categoryId],
            $page,
            $perPage
        );
    }

    public static function publishedForSitemap(): array
    {
        $stmt = Database::connection()->query(
            "SELECT slug, updated_at, created_at, published_at
             FROM posts
             WHERE status = 'published' AND (published_at IS NULL OR published_at <= UTC_TIMESTAMP())
             ORDER BY COALESCE(updated_at, published_at, created_at) DESC"
        );

        return $stmt->fetchAll();
    }

    public static function save(array $data, ?int $id = null): int
    {
        $publishedAt = self::resolvePublishedAt($data, $id);

        if ($id) {
            $stmt = Database::connection()->prepare(
                'UPDATE posts
                 SET title = :title, slug = :slug, excerpt = :excerpt, body_html = :body_html, status = :status,
                     published_at = :published_at, featured_media_id = :featured_media_id, author_id = :author_id, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'excerpt' => $data['excerpt'],
                'body_html' => $data['body_html'],
                'status' => $data['status'],
                'published_at' => $publishedAt,
                'featured_media_id' => $data['featured_media_id'] ?: null,
                'author_id' => $data['author_id'] ?: null,
                'updated_at' => now(),
            ]);
            return $id;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO posts (title, slug, excerpt, body_html, status, published_at, featured_media_id, author_id, legacy_wp_id, created_at, updated_at)
             VALUES (:title, :slug, :excerpt, :body_html, :status, :published_at, :featured_media_id, :author_id, :legacy_wp_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body_html' => $data['body_html'],
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'featured_media_id' => $data['featured_media_id'] ?: null,
            'author_id' => $data['author_id'] ?: null,
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

    public static function canEdit(array $post, array $user): bool
    {
        if (User::hasRole($user, User::ROLE_ADMIN, User::ROLE_EDITOR)) {
            return true;
        }

        return (string) $user['role'] === User::ROLE_AUTHOR && (int) ($post['author_id'] ?? 0) === (int) $user['id'];
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

    private static function paginate(string $sql, array $params, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS paginated_rows';
        $countStmt = Database::connection()->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $stmt = Database::connection()->prepare($sql . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private static function resolvePublishedAt(array $data, ?int $id = null): ?string
    {
        $submittedPublishedAt = trim((string) ($data['published_at'] ?? ''));
        if ($submittedPublishedAt !== '') {
            return $submittedPublishedAt;
        }

        if (($data['status'] ?? 'draft') !== 'published') {
            return null;
        }

        if ($id !== null) {
            $existing = self::find($id);
            if (!empty($existing['published_at'])) {
                return (string) $existing['published_at'];
            }
        }

        return now();
    }
}
