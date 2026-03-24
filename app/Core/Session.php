<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const COOKIE_LIFETIME = 604800;

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.gc_maxlifetime', (string) self::COOKIE_LIFETIME);
            session_set_cookie_params([
                'lifetime' => self::COOKIE_LIFETIME,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function id(): string
    {
        return session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
    }
}
