<?php

declare(strict_types=1);

use App\Auth\KakaoSocialIdentityVerifier;
use App\Auth\NaverSocialIdentityVerifier;
use App\Auth\ProviderSocialIdentityVerifier;
use App\Auth\SocialIdentityVerifier;
use App\Controller\Api\V1\AuthController;
use App\Database\TransactionManager;
use App\Exception\ServiceUnavailableException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Repository\AuthRepository;
use App\Service\AuthService;
use Symfony\Component\HttpFoundation\Request;

require (getenv('REFORM_TEST_ROOT') ?: dirname(__DIR__)) . '/vendor/autoload.php';
if (getenv('REFORM_TEST_ROOT')) {
    spl_autoload_register(static function (string $class): void {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (str_starts_with($class, 'App\\') && is_file($path)) require $path;
    }, true, true);
}

function checkKakao(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function rejectKakao(callable $operation, string $class): void
{
    try { $operation(); }
    catch (Throwable $exception) {
        checkKakao($exception instanceof $class, 'Unexpected rejection: ' . $exception::class);
        return;
    }
    throw new RuntimeException('Expected authentication rejection.');
}

function encodeKakao(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
checkKakao($privateKey !== false, 'Test RSA generation failed.');
$details = openssl_pkey_get_details($privateKey);
$jwk = ['kid' => 'fixture-key', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => encodeKakao($details['rsa']['n']), 'e' => encodeKakao($details['rsa']['e'])];
$claims = ['iss' => 'https://kauth.kakao.com', 'aud' => 'native-app-key', 'sub' => '123456789', 'iat' => time() - 10, 'exp' => time() + 600, 'nickname' => '카카오회원', 'email' => 'user@example.test'];
$header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'fixture-key'];
$sign = static function (array $claims, array $header) use ($privateKey): string {
    $message = encodeKakao(json_encode($header)) . '.' . encodeKakao(json_encode($claims));
    openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    return $message . '.' . encodeKakao($signature);
};
$calls = 0;
$transport = static function (string $url) use (&$calls, $jwk): array {
    checkKakao($url === 'https://kauth.kakao.com/.well-known/jwks.json', 'Untrusted signing-key URL.');
    $calls++;
    return ['keys' => [$jwk]];
};
$cache = sys_get_temp_dir() . '/reform-kakao-test-' . bin2hex(random_bytes(8)) . '.json';
try {
    $verifier = new KakaoSocialIdentityVerifier(['native-app-key'], $cache, $transport);
    $token = $sign($claims, $header);
    checkKakao($verifier->verify('KAKAO', null, $token, '') === ['providerUserId' => '123456789', 'email' => 'user@example.test', 'name' => '카카오회원'], 'Signed identity mapping failed.');
    $verifier->verify('KAKAO', null, $token, '');
    checkKakao($calls === 1, 'Public signing keys must be cached.');
    $rotatedJwk = array_replace($jwk, ['kid' => 'rotated-key']);
    $rotationCalls = 0;
    $rotationTransport = static function (string $url) use (&$rotationCalls, $jwk, $rotatedJwk): array {
        $rotationCalls++;
        return ['keys' => [$jwk, $rotatedJwk]];
    };
    $rotated = new KakaoSocialIdentityVerifier(['native-app-key'], $cache, $rotationTransport);
    checkKakao($rotated->verify('KAKAO', null, $sign($claims, array_replace($header, ['kid' => 'rotated-key'])), '')['providerUserId'] === '123456789', 'Rotated signing key failed.');
    checkKakao($rotationCalls === 1, 'Unknown kid must refresh valid cached keys.');
    $minimal = $claims; unset($minimal['email'], $minimal['nickname']);
    checkKakao($verifier->verify('KAKAO', null, $sign($minimal, $header), '') === ['providerUserId' => '123456789', 'email' => null, 'name' => null], 'Optional consent fields must be optional.');
    foreach ([['iss' => 'https://attacker.test'], ['aud' => 'rest-api-key'], ['exp' => time() - 1], ['exp' => '9999999999'], ['iat' => time() + 300], ['sub' => ''], ['sub' => ['invalid']], ['nbf' => time() + 300]] as $changes) {
        rejectKakao(fn () => $verifier->verify('KAKAO', null, $sign(array_replace($claims, $changes), $header), ''), UnauthorizedException::class);
    }
    foreach ([['alg' => 'none'], ['alg' => 'HS256'], ['kid' => 'unknown-key'], ['typ' => 'other'], ['jku' => 'https://attacker.test', 'kid' => 'attacker-key'], ['crit' => ['b64']]] as $changes) {
        rejectKakao(fn () => $verifier->verify('KAKAO', null, $sign($claims, array_replace($header, $changes)), ''), UnauthorizedException::class);
    }
    $parts = explode('.', $token);
    $parts[1] = encodeKakao(json_encode(array_replace($claims, ['sub' => '999999'])));
    rejectKakao(fn () => $verifier->verify('KAKAO', null, implode('.', $parts), ''), UnauthorizedException::class);
    foreach (['', 'a.b.c', $token . '.extra', 'not-a-jwt', str_repeat('a', 10001)] as $invalid) {
        rejectKakao(fn () => $verifier->verify('KAKAO', null, $invalid, ''), UnauthorizedException::class);
    }
    rejectKakao(fn () => $verifier->verify('KAKAO', 'code', $token, ''), ValidationException::class);
    rejectKakao(fn () => $verifier->verify('KAKAO', null, null, ''), ValidationException::class);
    rejectKakao(fn () => (new KakaoSocialIdentityVerifier([], null, $transport))->verify('KAKAO', null, $token, ''), ServiceUnavailableException::class);
    $offline = static fn (string $url): array => throw new ServiceUnavailableException('KAKAO_UNAVAILABLE', 'offline');
    // Current cached keys allow verification during a transient network failure.
    checkKakao((new KakaoSocialIdentityVerifier(['native-app-key'], $cache, $offline))->verify('KAKAO', null, $token, '')['providerUserId'] === '123456789', 'Valid cache was ignored.');
    file_put_contents($cache, json_encode(['expiresAt' => time() - 1, 'jwks' => ['keys' => [$jwk]]]));
    rejectKakao(fn () => (new KakaoSocialIdentityVerifier(['native-app-key'], $cache, $offline))->verify('KAKAO', null, $token, ''), ServiceUnavailableException::class);
    rejectKakao(fn () => (new KakaoSocialIdentityVerifier(['native-app-key'], null, static fn (): array => ['bad' => []]))->verify('KAKAO', null, $token, ''), ServiceUnavailableException::class);
    // Unwritable cache is optional and must not prevent verification.
    checkKakao((new KakaoSocialIdentityVerifier(['native-app-key'], $cache . '/missing/cache.json', $transport))->verify('KAKAO', null, $token, '')['providerUserId'] === '123456789', 'Cache write failure broke login.');
    $router = new ProviderSocialIdentityVerifier(['KAKAO' => $verifier, 'NAVER' => new NaverSocialIdentityVerifier('', '', [], null)]);
    rejectKakao(fn () => $router->verify('GOOGLE', null, $token, ''), ServiceUnavailableException::class);
    rejectKakao(fn () => $router->verify('NAVER', 'code', null, '', 'state'), ServiceUnavailableException::class);

    // A malformed ID token reaches provider verification without redirectUri;
    // neither repository nor transaction is accessed for rejected credentials.
    $auth = new AuthService(
        (new ReflectionClass(AuthRepository::class))->newInstanceWithoutConstructor(),
        $router,
        (new ReflectionClass(TransactionManager::class))->newInstanceWithoutConstructor(),
        0, 0,
    );
    $controller = new AuthController($auth);
    $body = ['provider' => 'kakao', 'idToken' => 'invalid-token', 'device' => ['deviceId' => 'test-device']];
    $request = static fn (array $body): Request => Request::create('/api/v1/auth/social-login', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    rejectKakao(fn () => $controller->socialLogin($request($body)), UnauthorizedException::class);
    foreach ([['authorizationCode' => 'code', 'idToken' => 'token'], ['authorizationCode' => 'code'], ['idToken' => null], ['idToken' => ['bad']]] as $changes) {
        rejectKakao(fn () => $controller->socialLogin($request(array_replace($body, $changes))), ValidationException::class);
    }
} finally {
    if (is_file($cache)) unlink($cache);
}

// Deployment must never inherit the local development token expiry file.
$environment = getenv('APP_ENV');
$accessTtl = getenv('AUTH_ACCESS_TTL_SECONDS');
$refreshTtl = getenv('AUTH_REFRESH_TTL_SECONDS');
try {
    putenv('APP_ENV=production');
    putenv('AUTH_ACCESS_TTL_SECONDS');
    putenv('AUTH_REFRESH_TTL_SECONDS');
    $services = require dirname(__DIR__) . '/config/services.php';
    checkKakao($services['auth']['access_ttl_seconds'] === 0 && $services['auth']['refresh_ttl_seconds'] === 0, 'Production inherited a local development expiry policy.');
} finally {
    foreach (['APP_ENV' => $environment, 'AUTH_ACCESS_TTL_SECONDS' => $accessTtl, 'AUTH_REFRESH_TTL_SECONDS' => $refreshTtl] as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}

echo "Kakao login tests passed.\n";
