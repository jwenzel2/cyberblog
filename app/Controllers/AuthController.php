<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\PasskeyCredential;
use App\Models\RecoveryCode;
use App\Models\User;
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
        if (!$user || !User::verifyPassword($user, $password)) {
            Session::flash('error', 'Invalid email or password.');
            Response::redirect('/login');
        }

        if (!empty($user['totp_enabled'])) {
            Auth::beginPendingLogin((int) $user['id']);
            Response::redirect('/login/mfa');
        }

        Auth::login((int) $user['id']);
        Response::redirect('/admin');
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

        $code = trim((string) ($_POST['totp_code'] ?? ''));
        if (!(new TotpService())->verifyCode((string) $user['totp_secret'], $code)) {
            Session::flash('error', 'Invalid authentication code.');
            Response::redirect('/login/mfa');
        }

        Auth::login((int) $user['id']);
        Response::redirect('/admin');
    }

    public function passkeyOptions(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        Session::put('login.email', $email);
        $user = User::findByEmail($email);
        if (!$user) {
            Response::json(['error' => 'Unknown account.'], 422);
        }

        $credentials = PasskeyCredential::forUser((int) $user['id']);
        if ($credentials === []) {
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
            Response::json(['error' => 'Unknown passkey.'], 422);
        }

        try {
            (new WebAuthnService())->verifyAuthentication($payload, $credential);
            Auth::login((int) $credential['user_id']);
            Response::json(['redirect' => '/admin']);
        } catch (RuntimeException $e) {
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
            Session::flash('error', 'Unknown account.');
            Response::redirect('/login');
        }

        if (!$code || !RecoveryCode::consume((int) $user['id'], $code)) {
            Session::flash('error', 'Recovery code invalid or already used.');
            Response::redirect('/login/recovery');
        }

        Auth::login((int) $user['id']);
        Session::flash('status', 'Recovery code accepted. Update your security settings now.');
        Response::redirect('/admin/security');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }
}
