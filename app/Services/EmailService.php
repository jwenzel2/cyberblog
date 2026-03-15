<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Preference;
use Throwable;

final class EmailService
{
    public function enabled(): bool
    {
        return Preference::get('smtp_enabled', '0') === '1';
    }

    public function send(string $toEmail, string $subject, string $body, string $toName = ''): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $config = [
            'host' => Preference::get('smtp_host', ''),
            'port' => Preference::get('smtp_port', '587'),
            'username' => Preference::get('smtp_username', ''),
            'password' => Preference::get('smtp_password', ''),
            'encryption' => Preference::get('smtp_encryption', 'tls'),
            'from_email' => Preference::get('smtp_from_email', ''),
            'from_name' => Preference::get('smtp_from_name', 'CyberBlog'),
        ];

        try {
            (new SmtpClient())->send($config, $toEmail, $toName, $subject, $body);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
