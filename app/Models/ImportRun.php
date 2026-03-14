<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ImportRun
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM imports ORDER BY created_at DESC')->fetchAll();
    }

    public static function findByChecksum(string $checksum): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM imports WHERE archive_checksum = :checksum LIMIT 1');
        $stmt->execute(['checksum' => $checksum]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $archiveName, string $checksum): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO imports (archive_name, archive_checksum, status, created_at, updated_at)
             VALUES (:archive_name, :archive_checksum, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            'archive_name' => $archiveName,
            'archive_checksum' => $checksum,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE imports
             SET status = :status, imported_posts = :imported_posts, imported_media = :imported_media,
                 imported_categories = :imported_categories, warnings = :warnings, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $data['status'],
            'imported_posts' => $data['imported_posts'] ?? 0,
            'imported_media' => $data['imported_media'] ?? 0,
            'imported_categories' => $data['imported_categories'] ?? 0,
            'warnings' => $data['warnings'] ?? null,
            'updated_at' => now(),
        ]);
    }
}
