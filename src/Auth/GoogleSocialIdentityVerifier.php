<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exception\ServiceUnavailableException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use Closure;

/** Native SDK ID tokens: RS256 signature and claims checked before account lookup. */
final class GoogleSocialIdentityVerifier implements SocialIdentityVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private readonly Closure $transport;

    /** @param list<string> $webClientIds @param list<string> $authorizedClientIds */
    public function __construct(
        private readonly array $webClientIds,
        private readonly ?string $cachePath = null,
        ?Closure $transport = null,
        private readonly array $authorizedClientIds = [],
    ) {
        $this->transport = $transport ?? self::requestKeys(...);
    }

    public function verify(
        string $provider,
        ?string $authorizationCode,
        ?string $idToken,
        string $redirectUri,
        ?string $state = null,
    ): array {
        if ($provider !== 'GOOGLE') {
            return (new UnavailableSocialIdentityVerifier())->verify($provider, $authorizationCode, $idToken, $redirectUri, $state);
        }
        if ($this->webClientIds === [] || !function_exists('openssl_verify')) {
            throw new ServiceUnavailableException('GOOGLE_NOT_CONFIGURED', 'Google 로그인 서버 설정을 확인해주세요.');
        }
        if ($authorizationCode !== null || $idToken === null || $state !== null) {
            throw new ValidationException('GOOGLE_CREDENTIAL_INVALID', 'Google ID 토큰으로 다시 로그인해주세요.');
        }
        $parts = explode('.', $idToken);
        if (count($parts) !== 3 || strlen($idToken) > 10000) $this->reject();
        $header = json_decode(self::decode($parts[0]), true);
        $claims = json_decode(self::decode($parts[1]), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? null) !== 'RS256'
            || (isset($header['typ']) && $header['typ'] !== 'JWT') || !is_string($header['kid'] ?? null)
            || $header['kid'] === '' || strlen($header['kid']) > 200
            || isset($header['crit']) || isset($header['b64'])) $this->reject();
        $now = time();
        if (!in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            || !is_string($claims['aud'] ?? null) || !in_array($claims['aud'], $this->webClientIds, true)
            || !is_int($claims['exp'] ?? null) || $claims['exp'] <= $now
            || !is_int($claims['iat'] ?? null) || $claims['iat'] <= 0 || $claims['iat'] > $now + 60
            || $claims['exp'] <= $claims['iat']
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now))
            || !is_string($claims['sub'] ?? null) || preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $claims['sub']) !== 1
            || (isset($claims['azp']) && (!is_string($claims['azp']) || !in_array($claims['azp'], $this->authorizedClientIds, true)))) $this->reject();

        $keys = $this->cachedKeys();
        $key = $keys === null ? null : self::findKey($keys, $header['kid']);
        if ($key === null) {
            // Refresh on unknown kid so key rotation does not wait for cache expiry.
            $keys = ($this->transport)(self::JWKS_URL);
            if (!is_array($keys['keys'] ?? null)) {
                throw new ServiceUnavailableException('GOOGLE_KEYS_INVALID', 'Google 인증 공개키를 확인할 수 없어요.');
            }
            $this->cacheKeys($keys);
            $key = self::findKey($keys, $header['kid']);
        }
        if ($key === null) $this->reject();
        $pem = self::publicKey($key);
        if (@openssl_verify($parts[0] . '.' . $parts[1], self::decode($parts[2]), $pem, OPENSSL_ALGO_SHA256) !== 1) $this->reject();
        $email = self::optionalText($claims['email'] ?? null, 254);
        return [
            'providerUserId' => $claims['sub'],
            'email' => ($claims['email_verified'] ?? false) === true && $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null,
            'name' => self::optionalText($claims['name'] ?? null, 100),
        ];
    }

    private function reject(): never
    {
        throw new UnauthorizedException('GOOGLE_ID_TOKEN_INVALID', 'Google 인증 정보가 올바르지 않아요. 다시 로그인해주세요.');
    }

    private static function decode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1 || strlen($value) % 4 === 1) {
            throw new UnauthorizedException('GOOGLE_ID_TOKEN_INVALID', 'Google 인증 정보를 확인할 수 없어요.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new UnauthorizedException('GOOGLE_ID_TOKEN_INVALID', 'Google 인증 정보를 확인할 수 없어요.');
        }
        return $decoded;
    }

    /** @param array<string, mixed> $keys @return array<string, mixed>|null */
    private static function findKey(array $keys, string $kid): ?array
    {
        foreach ($keys['keys'] ?? [] as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid && ($key['kty'] ?? null) === 'RSA'
                && ($key['alg'] ?? null) === 'RS256' && ($key['use'] ?? null) === 'sig'
                && is_string($key['n'] ?? null) && is_string($key['e'] ?? null)) return $key;
        }
        return null;
    }

    /** @param array<string, mixed> $key */
    private static function publicKey(array $key): string
    {
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\0");
            if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) $bytes = "\0" . $bytes;
            return self::der(0x02, $bytes);
        };
        $rsa = self::der(0x30, $integer(self::decode($key['n'])) . $integer(self::decode($key['e'])));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $spki = self::der(0x30, $algorithm . self::der(0x03, "\0" . $rsa));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);
        $encoded = $length < 128 ? chr($length) : ltrim(pack('N', $length), "\0");
        if ($length >= 128) $encoded = chr(0x80 | strlen($encoded)) . $encoded;
        return chr($tag) . $encoded . $value;
    }

    private static function optionalText(mixed $value, int $maximum): ?string
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= $maximum ? trim($value) : null;
    }

    /** @return array<string, mixed>|null */
    private function cachedKeys(): ?array
    {
        if ($this->cachePath === null) return null;
        $raw = @file_get_contents($this->cachePath, false, null, 0, 65537);
        $cached = is_string($raw) && strlen($raw) <= 65536 ? json_decode($raw, true) : null;
        return is_array($cached) && is_int($cached['expiresAt'] ?? null) && $cached['expiresAt'] > time()
            && is_array($cached['jwks']['keys'] ?? null) ? $cached['jwks'] : null;
    }

    /** @param array<string, mixed> $keys */
    private function cacheKeys(array $keys): void
    {
        if ($this->cachePath === null) return;
        $directory = dirname($this->cachePath);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return;
        // Cache contains only public signing keys; never provider tokens.
        $temporary = @tempnam($directory, 'google-');
        if ($temporary === false) return;
        if (@file_put_contents($temporary, json_encode(['expiresAt' => time() + 3600, 'jwks' => $keys])) !== false) {
            @rename($temporary, $this->cachePath);
        }
        if (is_file($temporary)) @unlink($temporary);
    }

    /** @return array<string, mixed> */
    private static function requestKeys(string $url): array
    {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'header' => 'Accept: application/json', 'timeout' => 8, 'ignore_errors' => true, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new ServiceUnavailableException('GOOGLE_UNAVAILABLE', 'Google 인증 서버에 연결할 수 없어요.');
        }
        try {
            $metadata = stream_get_meta_data($stream);
            $raw = stream_get_contents($stream, 65537);
        } finally {
            fclose($stream);
        }
        $status = $metadata['wrapper_data'][0] ?? '';
        $data = is_string($raw) && strlen($raw) <= 65536 ? json_decode($raw, true) : null;
        if (preg_match('/^HTTP\/\S+\s+200\b/', $status) !== 1 || !is_array($data)) {
            throw new ServiceUnavailableException('GOOGLE_UNAVAILABLE', 'Google 인증 공개키를 가져올 수 없어요.');
        }
        return $data;
    }
}
