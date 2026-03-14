<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

\App\Core\Env::load(app_path('.env'));
\App\Core\Session::start();

foreach (['storage', 'storage/tmp', 'storage/media', 'storage/logs', 'storage/cache'] as $dir) {
    $full = app_path($dir);
    if (!is_dir($full)) {
        mkdir($full, 0775, true);
    }
}
