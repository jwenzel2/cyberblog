<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', app_debug() ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception): void {
            self::log($exception);

            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');

            $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
            $line = (int) $exception->getLine();

            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CyberBlog Error</title>';
            echo '<style>body{font-family:Consolas,monospace;background:#08111f;color:#d9e5f7;padding:32px}';
            echo '.card{max-width:980px;margin:0 auto;background:#0f1b2e;border:1px solid #1f3352;border-radius:16px;padding:24px}';
            echo '.muted{color:#8fa9c7}pre{white-space:pre-wrap;background:#06101d;border:1px solid #28446e;padding:16px;border-radius:10px;overflow:auto}</style></head><body>';
            echo '<div class="card"><h1>Application Error</h1>';
            echo '<p>CyberBlog hit an unhandled exception.</p>';
            echo '<p><strong>Message:</strong> ' . $message . '</p>';

            if (app_debug()) {
                echo '<p><strong>Location:</strong> ' . $file . ':' . $line . '</p>';
                echo '<pre>' . htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo '<p class="muted">Check storage/logs/app.log for the full stack trace.</p>';
            }

            echo '</div></body></html>';
            exit;
        });
    }

    private static function log(Throwable $exception): void
    {
        Logger::exception($exception);
    }
}
