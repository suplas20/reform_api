<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exception\ServiceUnavailableException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use Closure;

final class NaverSocialIdentityVerifier implements SocialIdentityVerifier
{
    private readonly Closure $transport;

    /** @param list<string> $redirectUris */
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly array $redirectUris,
        ?Closure $transport = null,
    ) {
        $this->transport = $transport ?? self::requestJson(...);
    }

    public function verify(
        string $provider,
        ?string $authorizationCode,
        ?string $idToken,
        string $redirectUri,
        ?string $state = null,
    ): array {
        if ($provider !== 'NAVER') {
            return (new UnavailableSocialIdentityVerifier())->verify($provider, $authorizationCode, $idToken, $redirectUri);
        }
        if ($this->clientId === '' || $this->clientSecret === '' || $this->clientId === $this->clientSecret || $this->redirectUris === []) {
            throw new ServiceUnavailableException('NAVER_NOT_CONFIGURED', '네이버 로그인 서버 설정을 확인해주세요.');
        }
        if ($authorizationCode === null || trim($authorizationCode) === '' || $idToken !== null
            || $state === null || preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $state) !== 1
            || !in_array($redirectUri, $this->redirectUris, true)) {
            throw new ValidationException('NAVER_CREDENTIAL_INVALID', '네이버 인증 정보와 Callback URL을 확인해주세요.');
        }

        $token = ($this->transport)(
            'https://nid.naver.com/oauth2.0/token',
            'POST',
            ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $authorizationCode,
                'state' => $state,
                'redirect_uri' => $redirectUri,
            ], '', '&', PHP_QUERY_RFC3986),
        );
        $accessToken = $token['access_token'] ?? null;
        if (isset($token['error']) || !is_string($accessToken) || $accessToken === ''
            || preg_match('/[\r\n]/', $accessToken) === 1) {
            throw new UnauthorizedException('NAVER_AUTH_FAILED', '네이버 인증에 실패했어요. 다시 로그인해주세요.');
        }
        $profile = ($this->transport)(
            'https://openapi.naver.com/v1/nid/me',
            'GET',
            ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $accessToken],
            null,
        );
        $response = $profile['response'] ?? null;
        $id = is_array($response) ? ($response['id'] ?? null) : null;
        if (($profile['resultcode'] ?? null) !== '00' || !is_string($id) || trim($id) === '' || strlen($id) > 100) {
            throw new UnauthorizedException('NAVER_PROFILE_INVALID', '네이버 회원정보를 확인할 수 없어요.');
        }
        $email = self::optionalText($response['email'] ?? null, 254);
        return [
            'providerUserId' => $id,
            'email' => $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null,
            'name' => self::optionalText($response['name'] ?? null, 100),
        ];
    }

    private static function optionalText(mixed $value, int $maximum): ?string
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= $maximum ? trim($value) : null;
    }

    /** @param array<string, string> $headers @return array<string, mixed> */
    private static function requestJson(string $url, string $method, array $headers, ?string $body): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => 8,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        // Do not emit PHP warnings containing provider URLs or credentials.
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new ServiceUnavailableException('NAVER_UNAVAILABLE', '네이버 인증 서버에 연결할 수 없어요.');
        }
        try {
            $metadata = stream_get_meta_data($stream);
            $raw = stream_get_contents($stream, 65537);
            $status = $metadata['wrapper_data'][0] ?? '';
        } finally {
            fclose($stream);
        }
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $status, $matches) !== 1 || (int) $matches[1] >= 500) {
            throw new ServiceUnavailableException('NAVER_UNAVAILABLE', '네이버 인증 서버가 응답하지 않아요.');
        }
        if ((int) $matches[1] !== 200) {
            throw new UnauthorizedException('NAVER_AUTH_FAILED', '네이버 인증에 실패했어요. 다시 로그인해주세요.');
        }
        if (!is_string($raw) || strlen($raw) > 65536) {
            throw new ServiceUnavailableException('NAVER_RESPONSE_INVALID', '네이버 인증 응답을 확인할 수 없어요.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new ServiceUnavailableException('NAVER_RESPONSE_INVALID', '네이버 인증 응답을 확인할 수 없어요.');
        }
        return $data;
    }
}
