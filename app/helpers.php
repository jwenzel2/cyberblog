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

function app_timezone(): string
{
    $fallback = date_default_timezone_get() ?: 'UTC';
    $configured = \App\Models\Preference::get('site_timezone', $fallback) ?: $fallback;
    return in_array($configured, timezone_identifiers_list(), true) ? $configured : $fallback;
}

function app_url(string $path = '/'): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    $base = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $base = $scheme . '://' . $host;
        }
    }

    return $base !== '' ? $base . $normalizedPath : $normalizedPath;
}

function current_url(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
    return app_url($path . ($query !== '' ? '?' . $query : ''));
}

function format_app_datetime(?string $datetime, string $format = 'Y-m-d H:i:s'): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '';
    }

    try {
        $utc = new DateTimeZone('UTC');
        $timezone = new DateTimeZone(app_timezone());
        $value = new DateTimeImmutable($datetime, $utc);
        return $value->setTimezone($timezone)->format($format);
    } catch (Throwable) {
        return $datetime;
    }
}

function seo_excerpt(?string $text, int $limit = 160): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    if ($plain === '') {
        return '';
    }

    if (mb_strlen($plain) <= $limit) {
        return $plain;
    }

    return rtrim(mb_substr($plain, 0, max(0, $limit - 1))) . '…';
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
