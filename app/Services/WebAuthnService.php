<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Session;
use App\Models\PasskeyCredential;
use RuntimeException;

final class WebAuthnService
{
    public function registrationOptions(array $user, array $existingCredentials = []): array
    {
        $challenge = base64url_encode(random_bytes(32));
        Session::put('webauthn.register.challenge', $challenge);

        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => 'CyberBlog',
                'id' => Env::get('WEBAUTHN_RP_ID', parse_url((string) Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
            ],
            'user' => [
                'id' => base64url_encode((string) $user['id']),
                'name' => $user['email'],
                'displayName' => $user['display_name'],
            ],
            'pubKeyCredParams' => [
                ['alg' => -7, 'type' => 'public-key'],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => array_map(static function (array $credential): array {
                return ['id' => $credential['credential_id'], 'type' => 'public-key'];
            }, $existingCredentials),
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
        ];
    }

    public function verifyRegistration(array $payload, array $user): array
    {
        $clientData = json_decode(base64url_decode($payload['response']['clientDataJSON'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $challenge = Session::get('webauthn.register.challenge');
        if (($clientData['type'] ?? '') !== 'webauthn.create' || ($clientData['challenge'] ?? '') !== $challenge) {
            throw new RuntimeException('Invalid registration challenge.');
        }

        $origin = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($origin && !str_starts_with((string) ($clientData['origin'] ?? ''), $origin)) {
            throw new RuntimeException('Invalid registration origin.');
        }

        $attestationObject = base64url_decode($payload['response']['attestationObject'] ?? '');
        $decoder = new CborDecoder();
        $decoded = $decoder->decode($attestationObject);
        $authData = $decoded['authData'] ?? null;
        if (!$authData) {
            throw new RuntimeException('Missing authenticator data.');
        }

        $parsed = $this->parseAuthData($authData, true);
        return [
            'user_id' => (int) $user['id'],
            'credential_id' => base64url_encode($parsed['credential_id']),
            'label' => trim((string) ($payload['label'] ?? 'Passkey')),
            'public_key_pem' => $this->coseToPem($parsed['cose_key']),
            'transports' => isset($payload['transports']) ? implode(',', (array) $payload['transports']) : null,
            'sign_count' => $parsed['sign_count'],
            'aaguid' => bin2hex($parsed['aaguid']),
        ];
    }

    public function authenticationOptions(array $credentials): array
    {
        $challenge = base64url_encode(random_bytes(32));
        Session::put('webauthn.login.challenge', $challenge);

        return [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => Env::get('WEBAUTHN_RP_ID', parse_url((string) Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
            'allowCredentials' => array_map(static function (array $credential): array {
                return ['id' => $credential['credential_id'], 'type' => 'public-key'];
            }, $credentials),
            'userVerification' => 'preferred',
        ];
    }

    public function verifyAuthentication(array $payload, array $credential): bool
    {
        $clientDataJson = base64url_decode($payload['response']['clientDataJSON'] ?? '');
        $clientData = json_decode($clientDataJson, true, 512, JSON_THROW_ON_ERROR);
        if (($clientData['type'] ?? '') !== 'webauthn.get' || ($clientData['challenge'] ?? '') !== Session::get('webauthn.login.challenge')) {
            throw new RuntimeException('Invalid authentication challenge.');
        }

        $origin = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($origin && !str_starts_with((string) ($clientData['origin'] ?? ''), $origin)) {
            throw new RuntimeException('Invalid authentication origin.');
        }

        $authenticatorData = base64url_decode($payload['response']['authenticatorData'] ?? '');
        $signature = base64url_decode($payload['response']['signature'] ?? '');
        $parsed = $this->parseAuthData($authenticatorData, false);

        $expectedRpIdHash = hash('sha256', (string) Env::get('WEBAUTHN_RP_ID', 'localhost'), true);
        if (!hash_equals($expectedRpIdHash, $parsed['rp_id_hash'])) {
            throw new RuntimeException('Unexpected RP ID.');
        }

        $signedData = $authenticatorData . hash('sha256', $clientDataJson, true);
        $verify = openssl_verify($signedData, $signature, $credential['public_key_pem'], OPENSSL_ALGO_SHA256);
        if ($verify !== 1) {
            throw new RuntimeException('Passkey signature verification failed.');
        }

        if ($parsed['sign_count'] > (int) $credential['sign_count']) {
            PasskeyCredential::updateSignCount((int) $credential['id'], $parsed['sign_count']);
        }

        return true;
    }

    private function parseAuthData(string $data, bool $attested): array
    {
        $offset = 0;
        $rpIdHash = substr($data, $offset, 32);
        $offset += 32;
        $flags = ord($data[$offset++]);
        $signCount = unpack('N', substr($data, $offset, 4))[1];
        $offset += 4;

        $parsed = [
            'rp_id_hash' => $rpIdHash,
            'flags' => $flags,
            'sign_count' => $signCount,
        ];

        if ($attested) {
            $parsed['aaguid'] = substr($data, $offset, 16);
            $offset += 16;
            $credentialIdLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
            $parsed['credential_id'] = substr($data, $offset, $credentialIdLength);
            $offset += $credentialIdLength;
            $parsed['cose_key'] = substr($data, $offset);
        }

        return $parsed;
    }

    private function coseToPem(string $coseKey): string
    {
        $decoder = new CborDecoder();
        $key = $decoder->decode($coseKey);
        $x = $key[-2] ?? null;
        $y = $key[-3] ?? null;
        if (!$x || !$y) {
            throw new RuntimeException('Unsupported COSE key.');
        }

        $der = hex2bin(
            '3059301306072A8648CE3D020106082A8648CE3D03010703420004' .
            bin2hex($x) .
            bin2hex($y)
        );

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }
}
