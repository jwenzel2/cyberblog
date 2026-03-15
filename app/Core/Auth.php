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
        Session::regenerate();
        Session::put('user_id', $userId);
        Session::forget('pending_login_user_id');
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireRole(array|string $roles, bool $allowBootstrap = false): array
    {
        $user = self::user();
        if (!$user) {
            Response::redirect('/login');
        }

        if (!$allowBootstrap && (int) $user['must_setup_auth'] === 1) {
            Response::redirect('/admin/security/bootstrap');
        }

        $allowedRoles = (array) $roles;
        if (!User::hasRole($user, ...$allowedRoles)) {
            Response::abort(403, 'Forbidden');
        }

        return $user;
    }

    public static function requireAdmin(bool $allowBootstrap = false): array
    {
        return self::requireRole(User::ROLE_ADMIN, $allowBootstrap);
    }

    public static function requireEditorOrAdmin(bool $allowBootstrap = false): array
    {
        return self::requireRole([User::ROLE_ADMIN, User::ROLE_EDITOR], $allowBootstrap);
    }

    public static function requireAuthenticated(bool $allowBootstrap = false): array
    {
        return self::requireRole([User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR], $allowBootstrap);
    }

    public static function beginPendingLogin(int $userId): void
    {
        Session::put('pending_login_user_id', $userId);
    }

    public static function pendingUser(): ?array
    {
        $id = Session::get('pending_login_user_id');
        return $id ? User::find((int) $id) : null;
    }
}
