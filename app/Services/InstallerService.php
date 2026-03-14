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
        return [
            'PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'pdo_mysql extension' => extension_loaded('pdo_mysql'),
            'openssl extension' => extension_loaded('openssl'),
            'mbstring extension' => extension_loaded('mbstring'),
            'json extension' => extension_loaded('json'),
            'fileinfo extension' => extension_loaded('fileinfo'),
            'phar extension' => extension_loaded('phar'),
            'storage writable' => is_writable(app_path('storage')) || is_writable(app_path()),
        ];
    }

    public function handle(array $input, array $files): array
    {
        $errors = [];
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

        if (!$config['app_url'] || !$config['rp_id'] || !$config['database'] || !$config['username'] || !$adminEmail) {
            return [null, ['All required fields must be completed.']];
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
            return [null, ['Database initialization failed: ' . $e->getMessage()]];
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
}
