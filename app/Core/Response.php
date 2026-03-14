<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function abort(int $status, string $message): never
    {
        http_response_code($status);
        echo $message;
        exit;
    }
}
