<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;
use App\Models\PasskeyCredential;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\WebAuthnService;
use RuntimeException;

final class AuthController
{
    public function showLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Login',
            'admin' => User::firstAdmin(),
            'error' => \App\Core\Session::flash('error'),
        ]);
    }

    public function passkeyOptions(): void
    {
        $admin = User::firstAdmin();
        if (!$admin) {
            Response::json(['error' => 'No admin account is provisioned.'], 422);
        }

        $options = (new WebAuthnService())->authenticationOptions(PasskeyCredential::forUser((int) $admin['id']));
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

    public function recoveryVerify(): void
    {
        $admin = User::firstAdmin();
        if (!$admin) {
            \App\Core\Session::flash('error', 'No admin account is configured.');
            Response::redirect('/login');
        }

        $code = trim((string) ($_POST['recovery_code'] ?? ''));
        if (!$code || !RecoveryCode::consume((int) $admin['id'], $code)) {
            \App\Core\Session::flash('error', 'Recovery code invalid or already used.');
            Response::redirect('/login');
        }

        Auth::login((int) $admin['id']);
        Response::redirect('/admin/security');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }
}
