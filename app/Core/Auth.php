<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        return $id ? User::find((int) $id) : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(int $userId): void
    {
        Session::put('user_id', $userId);
    }

    public static function logout(): void
    {
        Session::forget('user_id');
    }

    public static function requireAdmin(bool $allowBootstrap = false): array
    {
        $user = self::user();
        if (!$user) {
            Response::redirect('/login');
        }

        if (!$allowBootstrap && (int) $user['must_setup_auth'] === 1) {
            Response::redirect('/admin/security/bootstrap');
        }

        return $user;
    }
}
