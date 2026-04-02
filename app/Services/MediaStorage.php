<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;

final class MediaStorage
{
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff', 'tif',
    ];

    public function storeUpload(array $file, ?string $legacySource = null): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return $this->storeFile($file['tmp_name'], $file['name'], $legacySource);
    }

    public function storeFile(string $sourcePath, string $originalName, ?string $legacySource = null): ?array
    {
        if (!is_file($sourcePath)) {
            return null;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $checksum = hash_file('sha256', $sourcePath);
        $existing = Media::findByChecksum($checksum);
        if ($existing) {
            return $existing;
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = SlugService::slugify(pathinfo($originalName, PATHINFO_FILENAME));
        $dir = app_path('storage/media/' . gmdate('Y/m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $targetName = $safeName . '-' . substr($checksum, 0, 12) . ($ext ? '.' . strtolower($ext) : '');
        $targetPath = $dir . '/' . $targetName;

        if (!is_file($targetPath)) {
            copy($sourcePath, $targetPath);
        }

        $publicUrl = '/media/' . gmdate('Y/m') . '/' . $targetName;
        $mimeType = mime_content_type($targetPath) ?: 'application/octet-stream';

        $id = Media::create([
            'original_name' => $originalName,
            'storage_path' => $targetPath,
            'public_url' => $publicUrl,
            'mime_type' => $mimeType,
            'checksum' => $checksum,
            'legacy_source' => $legacySource,
        ]);

        return Media::find($id);
    }
}
