<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class LoginNotificationService
{
    public function sendLoginNotice(array $user, string $method, string $ip, string $userAgent, string $revocationToken): void
    {
        $timezone = app_timezone();
        $revokeUrl = app_url('/login/session/revoke/' . urlencode($revocationToken));
        $body = implode("\n", [
            'A login to your CyberBlog account was detected.',
            '',
            'Time (' . $timezone . '): ' . format_app_datetime(now()),
            'IP address: ' . $ip,
            'Browser: ' . $userAgent,
            'Method: ' . $method,
            '',
            "If this was not you, use the link below to invalidate this session key:",
            "It's not me: " . $revokeUrl,
        ]);

        (new EmailService())->send((string) $user['email'], 'CyberBlog login notice', $body, (string) $user['display_name']);
    }

    public function sendTemporaryLockNotice(array $user, string $lockUntil): void
    {
        $timezone = app_timezone();
        $lines = [
            'Your CyberBlog account has been temporarily locked after repeated failed login attempts.',
            '',
            'Locked until (' . $timezone . '): ' . format_app_datetime($lockUntil),
            'Account: ' . $user['email'],
            '',
            'If this was not you, review your credentials and try again after the lockout period.',
        ];
        if (support_contact_enabled()) {
            $lines[] = 'Support form: ' . app_url('/support/contact?email=' . urlencode((string) $user['email']));
        }
        $body = implode("\n", $lines);

        (new EmailService())->send((string) $user['email'], 'CyberBlog temporary lockout', $body, (string) $user['display_name']);
    }

    public function sendAdminLockNotice(array $user): void
    {
        $timezone = app_timezone();
        $lines = [
            'Your CyberBlog account has been locked and now requires administrator intervention.',
            '',
            'Time (' . $timezone . '): ' . format_app_datetime(now()),
            'Account: ' . $user['email'],
        ];
        if (support_contact_enabled()) {
            $lines[] = '';
            $lines[] = 'Use the support form to contact an administrator:';
            $lines[] = app_url('/support/contact?email=' . urlencode((string) $user['email']));
        }
        $body = implode("\n", $lines);

        (new EmailService())->send((string) $user['email'], 'CyberBlog account locked', $body, (string) $user['display_name']);
    }

    public function sendSupportRequest(string $fromEmail, string $message): void
    {
        $emailService = new EmailService();
        $timezone = app_timezone();
        foreach (User::admins() as $admin) {
            $body = implode("\n", [
                'A locked-out user submitted a support request.',
                '',
                'User email: ' . $fromEmail,
                'Time (' . $timezone . '): ' . format_app_datetime(now()),
                'IP address: ' . request_ip(),
                'Browser: ' . request_user_agent(),
                '',
                'Message:',
                $message,
            ]);
            $emailService->send((string) $admin['email'], 'CyberBlog support request', $body, (string) $admin['display_name']);
        }
    }
}
