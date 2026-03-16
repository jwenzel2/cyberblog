<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        self::write('error', $exception->getMessage(), $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $logDir = app_path('storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $entry = sprintf(
            "[%s] %s: %s %s\n",
            now(),
            strtoupper($level),
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        @file_put_contents($logDir . '/app.log', $entry, FILE_APPEND);
    }
}
