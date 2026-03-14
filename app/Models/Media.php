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
