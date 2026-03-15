<?php

declare(strict_types=1);

function app_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, '\\/') : $base;
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $padding = 4 - (strlen($data) % 4);
    if ($padding < 4) {
        $data .= str_repeat('=', $padding);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function env_bool(string $value, bool $default = false): bool
{
    $normalized = strtolower(trim($value));
    return match ($normalized) {
        '1', 'true', 'yes', 'on' => true,
        '0', 'false', 'no', 'off' => false,
        default => $default,
    };
}

function app_debug(): bool
{
    return env_bool((string) \App\Core\Env::get('APP_DEBUG', '1'), true);
}

function request_ip(): string
{
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        if ($parts !== []) {
            return $parts[0];
        }
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function request_user_agent(): string
{
    return trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser'));
}
