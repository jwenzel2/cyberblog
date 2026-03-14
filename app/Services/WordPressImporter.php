<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Category;
use App\Models\ImportRun;
use App\Models\Post;
use RuntimeException;
use Throwable;

final class WordPressImporter
{
    public function importArchive(string $archivePath): array
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException('Archive not found.');
        }

        $checksum = hash_file('sha256', $archivePath);
        $existingImport = ImportRun::findByChecksum($checksum);
        if ($existingImport) {
            $existingCounts = (int) $existingImport['imported_posts'] + (int) $existingImport['imported_media'] + (int) $existingImport['imported_categories'];
            if (($existingImport['status'] ?? '') === 'completed' && $existingCounts > 0) {
                return [
                    'posts' => (int) $existingImport['imported_posts'],
                    'media' => (int) $existingImport['imported_media'],
                    'categories' => (int) $existingImport['imported_categories'],
                    'skipped' => true,
                ];
            }

            $importId = (int) $existingImport['id'];
            ImportRun::update($importId, [
                'status' => 'pending',
                'imported_posts' => 0,
                'imported_media' => 0,
                'imported_categories' => 0,
                'warnings' => json_encode(['Retrying import for an archive with a previous zero-count or failed import.'], JSON_PRETTY_PRINT),
            ]);
        } else {
            $importId = ImportRun::create(basename($archivePath), $checksum);
        }
        $warnings = [];

        try {
            $extractDir = $this->extractArchive($archivePath);
            $sqlPath = $this->findSqlDump($extractDir);
            $prefix = $this->detectPrefix($sqlPath);
            $tables = $this->parseNeededTables($sqlPath, $prefix);
            $summary = $this->persistImport($extractDir, $tables, $warnings);

            ImportRun::update($importId, [
                'status' => 'completed',
                'imported_posts' => $summary['posts'],
                'imported_media' => $summary['media'],
                'imported_categories' => $summary['categories'],
                'warnings' => $warnings ? json_encode($warnings, JSON_PRETTY_PRINT) : null,
            ]);

            return $summary;
        } catch (Throwable $e) {
            $warnings[] = $e->getMessage();
            ImportRun::update($importId, [
                'status' => 'failed',
                'warnings' => json_encode($warnings, JSON_PRETTY_PRINT),
            ]);
            throw $e;
        }
    }

    private function extractArchive(string $archivePath): string
    {
        $workDir = app_path('storage/tmp/import-' . bin2hex(random_bytes(6)));
        mkdir($workDir, 0775, true);

        $tarPath = preg_replace('/\.gz$/', '', $archivePath) ?: $archivePath . '.tar';
        if (!is_file($tarPath)) {
            $phar = new \PharData($archivePath);
            $phar->decompress();
        }

        $tar = new \PharData($tarPath);
        $tar->extractTo($workDir, null, true);
        return $workDir;
    }

    private function findSqlDump(string $dir): string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
                return $file->getPathname();
            }
        }
        throw new RuntimeException('No SQL dump found in the archive.');
    }

    private function detectPrefix(string $sqlPath): string
    {
        $handle = fopen($sqlPath, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to open SQL dump.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/CREATE TABLE `([^`]+posts)`/i', $line, $matches)) {
                    return preg_replace('/posts$/', '', $matches[1]) ?: 'wp_';
                }
            }
        } finally {
            fclose($handle);
        }

        throw new RuntimeException('Could not determine the WordPress table prefix.');
    }

    private function parseNeededTables(string $sqlPath, string $prefix): array
    {
        $tables = ['posts', 'terms', 'term_taxonomy', 'term_relationships', 'postmeta'];
        $parsed = array_fill_keys($tables, []);
        $targetTables = array_combine(
            array_map(static fn(string $table): string => $prefix . $table, $tables),
            $tables
        );

        $handle = fopen($sqlPath, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to open SQL dump.');
        }

        $statement = '';
        $currentTable = null;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($currentTable === null) {
                    if (!preg_match('/^INSERT INTO `([^`]+)`/i', $line, $matches)) {
                        continue;
                    }

                    $fullTableName = $matches[1];
                    if (!isset($targetTables[$fullTableName])) {
                        continue;
                    }

                    $currentTable = $targetTables[$fullTableName];
                    $statement = $line;
                } else {
                    $statement .= $line;
                }

                if (!$this->statementComplete($statement)) {
                    continue;
                }

                $match = [];
                if (preg_match('/^INSERT INTO `([^`]+)`(?: \(([^)]+)\))?\s+VALUES\s*(.+);$/is', trim($statement), $match)) {
                    $columns = !empty($match[2])
                        ? array_map(static fn(string $column): string => trim($column, " `"), explode(',', $match[2]))
                        : $this->defaultColumnsForTable($currentTable);

                    foreach ($this->splitTuples($match[3]) as $tuple) {
                        $values = $this->parseTuple($tuple);
                        if (count($values) !== count($columns)) {
                            continue;
                        }
                        $parsed[$currentTable][] = array_combine($columns, $values);
                    }
                }

                $statement = '';
                $currentTable = null;
            }
        } finally {
            fclose($handle);
        }

        return $parsed;
    }

    private function defaultColumnsForTable(string $table): array
    {
        return match ($table) {
            'posts' => [
                'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt',
                'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged',
                'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid',
                'menu_order', 'post_type', 'post_mime_type', 'comment_count',
            ],
            'terms' => ['term_id', 'name', 'slug', 'term_group'],
            'term_taxonomy' => ['term_taxonomy_id', 'term_id', 'taxonomy', 'description', 'parent', 'count'],
            'term_relationships' => ['object_id', 'term_taxonomy_id', 'term_order'],
            'postmeta' => ['meta_id', 'post_id', 'meta_key', 'meta_value'],
            default => throw new RuntimeException('No default column map exists for table: ' . $table),
        };
    }

    private function statementComplete(string $statement): bool
    {
        $inString = false;
        $escaped = false;
        $length = strlen($statement);

        for ($i = 0; $i < $length; $i++) {
            $char = $statement[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
            }
        }

        return !$inString && str_ends_with(rtrim($statement), ';');
    }

    private function splitTuples(string $values): array
    {
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = 0, $len = strlen($values); $i < $len; $i++) {
            $char = $values[$i];
            $buffer .= $char;

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = trim($buffer, ", \r\n\t");
                    $buffer = '';
                }
            }
        }

        return $tuples;
    }

    private function parseTuple(string $tuple): array
    {
        $tuple = trim($tuple);
        $tuple = trim($tuple, '()');
        $values = [];
        $buffer = '';
        $inString = false;
        $escaped = false;

        for ($i = 0, $len = strlen($tuple); $i < $len; $i++) {
            $char = $tuple[$i];

            if ($inString) {
                if ($escaped) {
                    $buffer .= match ($char) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $char,
                    };
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === "'") {
                    $inString = false;
                    continue;
                }

                $buffer .= $char;
                continue;
            }

            if ($char === "'") {
                $inString = true;
                continue;
            }

            if ($char === ',') {
                $values[] = $this->normalizeValue($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $values[] = $this->normalizeValue($buffer);
        return $values;
    }

    private function normalizeValue(string $value): mixed
    {
        $value = trim($value);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if (is_numeric($value) && !preg_match('/^0[0-9]+/', $value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        return $value;
    }

    private function persistImport(string $extractDir, array $tables, array &$warnings): array
    {
        $terms = [];
        foreach ($tables['terms'] as $row) {
            $terms[(int) $row['term_id']] = $row;
        }

        $termTaxonomy = [];
        foreach ($tables['term_taxonomy'] as $row) {
            $termTaxonomy[(int) $row['term_taxonomy_id']] = $row;
        }

        $relationships = [];
        foreach ($tables['term_relationships'] as $row) {
            $relationships[(int) $row['object_id']][] = (int) $row['term_taxonomy_id'];
        }

        $meta = [];
        foreach ($tables['postmeta'] as $row) {
            $meta[(int) $row['post_id']][$row['meta_key']][] = $row['meta_value'];
        }

        $categoryMapByTaxonomy = [];
        $categoryMapByTerm = [];
        foreach ($tables['term_taxonomy'] as $taxonomy) {
            if (($taxonomy['taxonomy'] ?? '') !== 'category') {
                continue;
            }
            $termId = (int) $taxonomy['term_id'];
            $term = $terms[$termId] ?? null;
            if (!$term) {
                continue;
            }
            $slug = SlugService::unique('categories', SlugService::slugify((string) $term['slug']));
            $categoryId = Category::create([
                'name' => $term['name'],
                'slug' => $slug,
                'description' => $taxonomy['description'] ?? null,
                'parent_id' => null,
                'legacy_wp_term_id' => $termId,
            ]);
            $categoryMapByTaxonomy[(int) $taxonomy['term_taxonomy_id']] = $categoryId;
            $categoryMapByTerm[$termId] = $categoryId;
        }

        foreach ($tables['term_taxonomy'] as $taxonomy) {
            if (($taxonomy['taxonomy'] ?? '') !== 'category' || empty($taxonomy['parent'])) {
                continue;
            }
            $currentId = $categoryMapByTaxonomy[(int) $taxonomy['term_taxonomy_id']] ?? null;
            $parentId = $categoryMapByTerm[(int) $taxonomy['parent']] ?? null;
            if ($currentId && $parentId) {
                $stmt = Database::connection()->prepare('UPDATE categories SET parent_id = :parent_id, updated_at = :updated_at WHERE id = :id');
                $stmt->execute(['parent_id' => $parentId, 'updated_at' => now(), 'id' => $currentId]);
            }
        }

        $mediaStorage = new MediaStorage();
        $attachmentMap = [];
        $mediaCount = 0;
        foreach ($tables['posts'] as $row) {
            if (($row['post_type'] ?? '') !== 'attachment') {
                continue;
            }

            $relative = $meta[(int) $row['ID']]['_wp_attached_file'][0] ?? null;
            if (!$relative) {
                $warnings[] = 'Attachment #' . $row['ID'] . ' missing _wp_attached_file.';
                continue;
            }

            $source = $this->findImportedFile($extractDir, (string) $relative);
            if (!$source) {
                $warnings[] = 'Attachment file not found for ' . $relative;
                continue;
            }

            $media = $mediaStorage->storeFile($source, basename((string) $relative), (string) $row['guid']);
            if ($media) {
                $attachmentMap[(int) $row['ID']] = $media;
                $mediaCount++;
            }
        }

        $postCount = 0;
        foreach ($tables['posts'] as $row) {
            if (($row['post_type'] ?? '') !== 'post' || Post::findByLegacyWpId((int) $row['ID'])) {
                continue;
            }

            $slug = SlugService::unique('posts', SlugService::slugify((string) ($row['post_name'] ?: $row['post_title'])));
            $body = HtmlSanitizer::clean((string) ($row['post_content'] ?? ''));
            $body = $this->rewriteBodyUrls($body, $attachmentMap, (string) ($row['guid'] ?? ''));
            $featuredMeta = $meta[(int) $row['ID']]['_thumbnail_id'][0] ?? null;
            $featuredMediaId = $featuredMeta && isset($attachmentMap[(int) $featuredMeta]) ? $attachmentMap[(int) $featuredMeta]['id'] : null;

            $postId = Post::save([
                'title' => (string) $row['post_title'],
                'slug' => $slug,
                'excerpt' => (string) ($row['post_excerpt'] ?? ''),
                'body_html' => $body,
                'status' => in_array(($row['post_status'] ?? ''), ['publish', 'future'], true) ? 'published' : 'draft',
                'published_at' => $row['post_date_gmt'] ?: null,
                'featured_media_id' => $featuredMediaId,
                'legacy_wp_id' => (int) $row['ID'],
            ]);

            $assigned = [];
            foreach ($relationships[(int) $row['ID']] ?? [] as $termTaxonomyId) {
                if (isset($categoryMapByTaxonomy[$termTaxonomyId])) {
                    $assigned[] = $categoryMapByTaxonomy[$termTaxonomyId];
                }
            }
            Post::syncCategories($postId, $assigned);
            $postCount++;
        }

        return [
            'posts' => $postCount,
            'media' => $mediaCount,
            'categories' => count($categoryMapByTaxonomy),
        ];
    }

    private function findImportedFile(string $extractDir, string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        $candidate = $extractDir . '/wp-content/uploads/' . $relativePath;
        if (is_file($candidate)) {
            return $candidate;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with(str_replace('\\', '/', $file->getPathname()), $relativePath)) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function rewriteBodyUrls(string $html, array $attachmentMap, string $legacyGuid): string
    {
        foreach ($attachmentMap as $media) {
            $legacy = $media['legacy_source'] ?? null;
            if ($legacy) {
                $html = str_replace($legacy, $media['public_url'], $html);
            }
        }

        $html = str_replace('/wp-content/uploads/', '/media/', $html);
        return $html;
    }
}
