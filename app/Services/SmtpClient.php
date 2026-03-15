<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpClient
{
    private $socket = null;

    public function send(array $config, string $toEmail, string $toName, string $subject, string $body): void
    {
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 587);
        $encryption = (string) ($config['encryption'] ?? 'tls');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $fromEmail = (string) ($config['from_email'] ?? '');
        $fromName = (string) ($config['from_name'] ?? 'CyberBlog');

        if ($host === '' || $fromEmail === '') {
            throw new RuntimeException('SMTP host and from email are required.');
        }

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $this->socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
        if (!$this->socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($this->socket, 15);
        $this->expect([220]);
        $this->command('EHLO cyberblog.local', [250]);

        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to enable TLS for SMTP.');
            }
            $this->command('EHLO cyberblog.local', [250]);
        }

        if ($username !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($username), [334]);
            $this->command(base64_encode($password), [235]);
        }

        $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
        $this->command('DATA', [354]);

        $headers = [
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: ' . $this->formatAddress($toEmail, $toName ?: $toEmail),
            'Subject: ' . $subject,
            'Date: ' . gmdate('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
        $this->command($message, [250]);
        $this->command('QUIT', [221]);
        fclose($this->socket);
        $this->socket = null;
    }

    private function command(string $command, array $expectedCodes): void
    {
        fwrite($this->socket, $command . "\r\n");
        $this->expect($expectedCodes);
    }

    private function expect(array $expectedCodes): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }
    }

    private function formatAddress(string $email, string $name): string
    {
        $safeName = addcslashes($name, '"');
        return '"' . $safeName . '" <' . $email . '>';
    }
}
