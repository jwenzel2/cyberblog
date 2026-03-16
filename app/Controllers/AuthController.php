<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PasskeyCredential;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\LoginNotificationService;
use App\Services\TotpService;
use App\Services\WebAuthnService;
use RuntimeException;

final class AuthController
{
    public function showLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Login',
            'error' => Session::flash('error'),
            'status' => Session::flash('status'),
            'oldEmail' => (string) Session::get('login.email', ''),
        ]);
    }

    public function loginPassword(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        Session::put('login.email', $email);

        $user = User::findByEmail($email);
        if ($user) {
            $this->abortIfLocked($user, '/login');
        }

        if (!$user || !User::verifyPassword($user, $password)) {
            $this->handleFailedAttempt($user, '/login', 'Invalid email or password.');
        }

        if (!empty($user['totp_enabled'])) {
            Auth::beginPendingLogin((int) $user['id']);
            Response::redirect('/login/mfa');
        }

        $this->finalizeLogin($user, 'password');
    }

    public function showMfa(): void
    {
        $user = Auth::pendingUser();
        if (!$user) {
            Session::flash('error', 'Your login session expired. Try again.');
            Response::redirect('/login');
        }

        View::render('auth/mfa', [
            'title' => 'MFA Verification',
            'user' => $user,
            'error' => Session::flash('error'),
        ]);
    }

    public function verifyMfa(): void
    {
        $user = Auth::pendingUser();
        if (!$user || empty($user['totp_secret'])) {
            Session::flash('error', 'Your login session expired. Try again.');
            Response::redirect('/login');
        }

        $this->abortIfLocked($user, '/login');

        $code = trim((string) ($_POST['totp_code'] ?? ''));
        if (!(new TotpService())->verifyCode((string) $user['totp_secret'], $code)) {
            $this->handleFailedAttempt($user, '/login/mfa', 'Invalid authentication code.');
        }

        $this->finalizeLogin($user, 'password + totp');
    }

    public function passkeyOptions(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        Session::put('login.email', $email);
        $user = User::findByEmail($email);
        if (!$user) {
            Logger::warning('Passkey login requested for an unknown account.', [
                'email' => $email,
                'ip' => request_ip(),
            ]);
            Response::json(['error' => 'Unknown account.'], 422);
        }

        if (User::isAdminLocked($user)) {
            Response::json(['error' => 'This account is locked until an administrator unlocks it.'], 423);
        }
        if (User::isTemporarilyLocked($user)) {
            Response::json(['error' => 'This account is temporarily locked until ' . $user['lock_until'] . ' UTC.'], 423);
        }

        $credentials = PasskeyCredential::forUser((int) $user['id']);
        if ($credentials === []) {
            $this->recordFailedAttempt($user);
            Response::json(['error' => 'No passkeys are registered for this account.'], 422);
        }

        $options = (new WebAuthnService())->authenticationOptions($credentials);
        Response::json($options);
    }

    public function passkeyVerify(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        $credentialId = (string) ($payload['id'] ?? '');
        $credential = PasskeyCredential::findByCredentialId($credentialId);
        if (!$credential) {
            $email = (string) Session::get('login.email', '');
            $user = $email !== '' ? User::findByEmail($email) : null;
            if ($user) {
                $this->recordFailedAttempt($user);
            } else {
                Logger::warning('Passkey verification failed for an unknown credential.', [
                    'credential_id' => $credentialId,
                    'email' => $email,
                    'ip' => request_ip(),
                ]);
            }
            Response::json(['error' => 'Unknown passkey.'], 422);
        }

        try {
            $user = User::find((int) $credential['user_id']);
            if (!$user) {
                Response::json(['error' => 'Unknown account.'], 422);
            }
            if (User::isAdminLocked($user)) {
                Response::json(['error' => 'This account is locked until an administrator unlocks it.'], 423);
            }
            if (User::isTemporarilyLocked($user)) {
                Response::json(['error' => 'This account is temporarily locked until ' . $user['lock_until'] . ' UTC.'], 423);
            }

            (new WebAuthnService())->verifyAuthentication($payload, $credential);
            User::clearLoginFailures((int) $user['id']);
            Auth::login((int) $credential['user_id']);
            (new LoginNotificationService())->sendLoginNotice($user, 'passkey', request_ip(), request_user_agent());
            Response::json(['redirect' => '/admin']);
        } catch (RuntimeException $e) {
            $user = User::find((int) $credential['user_id']);
            if ($user) {
                $this->recordFailedAttempt($user);
            }
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function showRecovery(): void
    {
        View::render('auth/recovery', [
            'title' => 'Recovery Login',
            'error' => Session::flash('error'),
            'status' => Session::flash('status'),
            'oldEmail' => (string) Session::get('recovery.email', ''),
        ]);
    }

    public function recoveryVerify(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $code = trim((string) ($_POST['recovery_code'] ?? ''));
        Session::put('recovery.email', $email);

        $user = User::findByEmail($email);
        if (!$user) {
            Logger::warning('Recovery login attempted for an unknown account.', [
                'email' => $email,
                'ip' => request_ip(),
            ]);
            Session::flash('error', 'Unknown account.');
            Response::redirect('/login');
        }

        $this->abortIfLocked($user, '/login/recovery');

        if (!$code || !RecoveryCode::consume((int) $user['id'], $code)) {
            $this->handleFailedAttempt($user, '/login/recovery', 'Recovery code invalid or already used.');
        }

        User::clearLoginFailures((int) $user['id']);
        Auth::login((int) $user['id']);
        (new LoginNotificationService())->sendLoginNotice($user, 'recovery code', request_ip(), request_user_agent());
        Session::flash('status', 'Recovery code accepted. Update your security settings now.');
        Response::redirect('/admin/security');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    private function finalizeLogin(array $user, string $method): void
    {
        User::clearLoginFailures((int) $user['id']);
        Auth::login((int) $user['id']);
        (new LoginNotificationService())->sendLoginNotice($user, $method, request_ip(), request_user_agent());
        Response::redirect('/admin');
    }

    private function abortIfLocked(array $user, string $redirect): void
    {
        if (User::isAdminLocked($user)) {
            Logger::warning('Login blocked because the account requires administrator unlock.', [
                'user_id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'ip' => request_ip(),
                'redirect' => $redirect,
            ]);
            Session::flash('error', 'This account is locked until an administrator unlocks it.');
            Session::flash('status', 'If you need help, use the support form.');
            Response::redirect($redirect);
        }

        if (User::isTemporarilyLocked($user)) {
            Logger::warning('Login blocked because the account is temporarily locked.', [
                'user_id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'ip' => request_ip(),
                'lock_until' => (string) ($user['lock_until'] ?? ''),
                'redirect' => $redirect,
            ]);
            Session::flash('error', 'This account is temporarily locked until ' . $user['lock_until'] . ' UTC.');
            Response::redirect($redirect);
        }
    }

    private function handleFailedAttempt(?array $user, string $redirect, string $defaultMessage): void
    {
        if ($user) {
            $result = $this->recordFailedAttempt($user);
            Session::flash('error', match ($result['state']) {
                'temporary_lock' => 'Too many failed attempts. This account is locked until ' . ($result['lock_until'] ?? '') . ' UTC.',
                'admin_locked' => 'This account is locked until an administrator unlocks it.',
                default => $defaultMessage,
            });
        } else {
            Logger::warning('Failed login attempt for an unknown account.', [
                'email' => (string) Session::get('login.email', ''),
                'ip' => request_ip(),
                'redirect' => $redirect,
                'reason' => $defaultMessage,
            ]);
            Session::flash('error', $defaultMessage);
        }

        Response::redirect($redirect);
    }

    private function recordFailedAttempt(array $user): array
    {
        $result = User::recordLoginFailure((int) $user['id']);
        Logger::warning('Failed login attempt recorded.', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'ip' => request_ip(),
            'attempts' => (int) ($result['attempts'] ?? 0),
            'state' => (string) ($result['state'] ?? 'failed'),
            'lock_until' => (string) ($result['lock_until'] ?? ''),
        ]);
        $notifier = new LoginNotificationService();

        if (($result['state'] ?? '') === 'temporary_lock' && (int) ($result['attempts'] ?? 0) === 4) {
            $notifier->sendTemporaryLockNotice($user, (string) ($result['lock_until'] ?? ''));
        }

        if (($result['state'] ?? '') === 'admin_locked' && (int) ($result['attempts'] ?? 0) === 7) {
            $notifier->sendAdminLockNotice($user);
        }

        if (($result['state'] ?? '') !== 'failed') {
            Session::forget('pending_login_user_id');
        }

        return $result;
    }
}
