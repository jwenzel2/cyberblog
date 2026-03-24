<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\LoginSession;
use App\Models\User;

final class Auth
{
    public static function user(): ?array
    {
        $id = (int) Session::get('user_id', 0);
        $loginSessionId = (int) Session::get('login_session_id', 0);
        $sessionId = Session::id();
        if ($id <= 0 || $loginSessionId <= 0 || $sessionId === '') {
            return null;
        }

        if (!LoginSession::findActive($loginSessionId, $id, $sessionId)) {
            self::logout();
            return null;
        }

        return User::find($id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(int $userId, string $ip, string $userAgent): array
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        Session::forget('pending_login_user_id');
        $loginSession = LoginSession::create($userId, Session::id(), $ip, $userAgent);
        Session::put('login_session_id', $loginSession['id']);
        return $loginSession;
    }

    public static function logout(): void
    {
        $loginSessionId = (int) Session::get('login_session_id', 0);
        if ($loginSessionId > 0) {
            LoginSession::revoke($loginSessionId);
        }
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
