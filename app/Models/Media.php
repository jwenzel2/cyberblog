<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Media
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM media ORDER BY created_at DESC')->fetchAll();
    }

    public static function images(string $sort = 'newest', int $limit = 12, int $offset = 0): array
    {
        $order = match ($sort) {
            'oldest' => 'created_at ASC',
            'name_asc' => 'original_name ASC',
            'name_desc' => 'original_name DESC',
            default => 'created_at DESC',
        };

        $stmt = Database::connection()->prepare(
            "SELECT * FROM media WHERE mime_type LIKE 'image/%' ORDER BY {$order} LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', max(1, min(60, $limit)), \PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function imageCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM media WHERE mime_type LIKE 'image/%'")->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByChecksum(string $checksum): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM media WHERE checksum = :checksum LIMIT 1');
        $stmt->execute(['checksum' => $checksum]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO media (original_name, storage_path, public_url, mime_type, alt_text, checksum, legacy_source, created_at)
             VALUES (:original_name, :storage_path, :public_url, :mime_type, :alt_text, :checksum, :legacy_source, :created_at)'
        );
        $stmt->execute([
            'original_name' => $data['original_name'],
            'storage_path' => $data['storage_path'],
            'public_url' => $data['public_url'],
            'mime_type' => $data['mime_type'],
            'alt_text' => $data['alt_text'] ?? null,
            'checksum' => $data['checksum'],
            'legacy_source' => $data['legacy_source'] ?? null,
            'created_at' => now(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
