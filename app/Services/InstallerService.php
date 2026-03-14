<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use PDOException;
use Throwable;

final class InstallerService
{
    public function checks(): array
    {
        $storageReady = $this->ensureStorageDirectories();

        return [
            'PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'pdo_mysql extension' => extension_loaded('pdo_mysql'),
            'openssl extension' => extension_loaded('openssl'),
            'mbstring extension' => extension_loaded('mbstring'),
            'json extension' => extension_loaded('json'),
            'fileinfo extension' => extension_loaded('fileinfo'),
            'phar extension' => extension_loaded('phar'),
            'storage directories ready' => $storageReady === [],
        ];
    }

    public function handle(array $input, array $files): array
    {
        $errors = [];

        if ($input === [] && $files === []) {
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($contentLength > 0) {
                return [null, [
                    'The installer form submission was received as an empty request.',
                    'This usually means PHP rejected the multipart POST before parsing it.',
                    'Check php.ini settings for post_max_size and upload_max_filesize, then retry.',
                ]];
            }

            return [null, [
                'The installer did not receive any form fields.',
                'Check that the request is reaching PHP correctly and that the web server allows POST requests to installer.php.',
            ]];
        }

        foreach ($this->checks() as $label => $passed) {
            if (!$passed) {
                $errors[] = "{$label} is required.";
            }
        }

        if ($errors) {
            return [null, $errors];
        }

        $config = [
            'app_url' => rtrim(trim((string) ($input['app_url'] ?? '')), '/'),
            'rp_id' => trim((string) ($input['rp_id'] ?? '')),
            'host' => trim((string) ($input['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($input['db_port'] ?? '3306')),
            'database' => trim((string) ($input['db_database'] ?? '')),
            'username' => trim((string) ($input['db_username'] ?? '')),
            'password' => (string) ($input['db_password'] ?? ''),
        ];

        $adminEmail = trim((string) ($input['admin_email'] ?? ''));
        $adminDisplayName = trim((string) ($input['admin_display_name'] ?? 'Admin'));

        if ($config['rp_id'] === '' && $config['app_url'] !== '') {
            $config['rp_id'] = parse_url($config['app_url'], PHP_URL_HOST) ?: '';
        }

        $fieldErrors = [];
        if ($config['app_url'] === '') {
            $fieldErrors[] = 'App URL is required.';
        }
        if ($config['rp_id'] === '') {
            $fieldErrors[] = 'RP ID is required.';
        }
        if ($config['database'] === '') {
            $fieldErrors[] = 'Database name is required.';
        }
        if ($config['username'] === '') {
            $fieldErrors[] = 'Database username is required.';
        }
        if ($adminEmail === '') {
            $fieldErrors[] = 'Admin email is required.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $fieldErrors[] = 'Admin email must be a valid email address.';
        }

        $storageErrors = $this->ensureStorageDirectories();
        if ($storageErrors) {
            $fieldErrors = array_merge($fieldErrors, $storageErrors);
        }

        if ($fieldErrors) {
            return [null, $fieldErrors];
        }

        try {
            $pdo = Database::tryServerConnection($config);
            $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $config['database']));
            $pdo->exec(sprintf('USE `%s`', $config['database']));
            $schema = file_get_contents(app_path('database/schema.sql')) ?: '';
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
                $pdo->exec($statement);
            }
            Database::reconnect($pdo);
        } catch (PDOException $e) {
            return [null, [
                'Database initialization failed: ' . $e->getMessage(),
                sprintf(
                    'Attempted connection with host=%s port=%s database=%s username=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['username']
                ),
            ]];
        }

        $this->writeEnv($config, $adminEmail);

        $admin = User::findByEmail($adminEmail);
        if (!$admin) {
            $adminId = User::create($adminEmail, $adminDisplayName);
            $admin = User::find($adminId);
        }

        Auth::login((int) $admin['id']);

        if (($files['wordpress_archive']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $path = app_path('storage/tmp/' . basename((string) $files['wordpress_archive']['name']));
                move_uploaded_file($files['wordpress_archive']['tmp_name'], $path);
                (new WordPressImporter())->importArchive($path);
            } catch (Throwable $e) {
                $errors[] = 'WordPress import failed: ' . $e->getMessage();
            }
        }

        return [$errors ? 'Installed with warnings. Review the import report.' : 'Installed successfully. Register a passkey and save your recovery codes.', $errors];
    }

    private function writeEnv(array $config, string $adminEmail): void
    {
        $content = implode("\n", [
            'APP_INSTALLED=1',
            'APP_URL=' . $config['app_url'],
            'WEBAUTHN_RP_ID=' . $config['rp_id'],
            'DB_HOST=' . $config['host'],
            'DB_PORT=' . $config['port'],
            'DB_DATABASE=' . $config['database'],
            'DB_USERNAME=' . $config['username'],
            'DB_PASSWORD=' . $config['password'],
            'ADMIN_EMAIL=' . $adminEmail,
            '',
        ]);

        file_put_contents(app_path('.env'), $content);
        \App\Core\Env::load(app_path('.env'));
    }

    private function ensureStorageDirectories(): array
    {
        $errors = [];

        foreach (['storage', 'storage/tmp', 'storage/media', 'storage/logs', 'storage/cache'] as $dir) {
            $fullPath = app_path($dir);
            if (!is_dir($fullPath) && !mkdir($fullPath, 0775, true) && !is_dir($fullPath)) {
                $errors[] = 'Failed to create directory: ' . $dir;
                continue;
            }

            if (!is_writable($fullPath)) {
                $errors[] = 'Directory is not writable: ' . $dir;
            }
        }

        return $errors;
    }
}
