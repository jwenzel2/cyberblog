<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\User;

final class LoginNotificationService
{
    public function sendLoginNotice(array $user, string $method, string $ip, string $userAgent): void
    {
        $body = implode("\n", [
            'A login to your CyberBlog account was detected.',
            '',
            'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
            'IP address: ' . $ip,
            'Browser: ' . $userAgent,
            'Method: ' . $method,
            '',
            'If this was not you, contact an administrator immediately.',
        ]);

        (new EmailService())->send((string) $user['email'], 'CyberBlog login notice', $body, (string) $user['display_name']);
    }

    public function sendTemporaryLockNotice(array $user, string $lockUntil): void
    {
        $contactUrl = rtrim((string) Env::get('APP_URL', ''), '/') . '/support/contact?email=' . urlencode((string) $user['email']);
        $body = implode("\n", [
            'Your CyberBlog account has been temporarily locked after repeated failed login attempts.',
            '',
            'Locked until (UTC): ' . $lockUntil,
            'Account: ' . $user['email'],
            '',
            'If this was not you, review your credentials and try again after the lockout period.',
            'Support form: ' . $contactUrl,
        ]);

        (new EmailService())->send((string) $user['email'], 'CyberBlog temporary lockout', $body, (string) $user['display_name']);
    }

    public function sendAdminLockNotice(array $user): void
    {
        $contactUrl = rtrim((string) Env::get('APP_URL', ''), '/') . '/support/contact?email=' . urlencode((string) $user['email']);
        $body = implode("\n", [
            'Your CyberBlog account has been locked and now requires administrator intervention.',
            '',
            'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
            'Account: ' . $user['email'],
            '',
            'Use the support form to contact an administrator:',
            $contactUrl,
        ]);

        (new EmailService())->send((string) $user['email'], 'CyberBlog account locked', $body, (string) $user['display_name']);
    }

    public function sendSupportRequest(string $fromEmail, string $message): void
    {
        $emailService = new EmailService();
        foreach (User::admins() as $admin) {
            $body = implode("\n", [
                'A locked-out user submitted a support request.',
                '',
                'User email: ' . $fromEmail,
                'Time (UTC): ' . gmdate('Y-m-d H:i:s'),
                '',
                'Message:',
                $message,
            ]);
            $emailService->send((string) $admin['email'], 'CyberBlog support request', $body, (string) $admin['display_name']);
        }
    }
}
