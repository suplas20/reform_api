<?php

declare(strict_types=1);

use App\Auth\GoogleSocialIdentityVerifier;
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

function checkGoogle(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function rejectGoogle(callable $operation, string $class): void
{
    try { $operation(); }
    catch (Throwable $exception) {
        checkGoogle($exception instanceof $class, 'Unexpected rejection: ' . $exception::class);
        return;
    }
    throw new RuntimeException('Expected authentication rejection.');
}

function encodeGoogle(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
checkGoogle($privateKey !== false, 'Test RSA generation failed.');
$details = openssl_pkey_get_details($privateKey);
$jwk = ['kid' => 'fixture-key', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => encodeGoogle($details['rsa']['n']), 'e' => encodeGoogle($details['rsa']['e'])];
$claims = ['iss' => 'https://accounts.google.com', 'aud' => 'web-client-id', 'sub' => '123456789', 'iat' => time() - 10, 'exp' => time() + 600, 'name' => 'Google회원', 'email' => 'user@example.test', 'email_verified' => true];
$header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'fixture-key'];
$sign = static function (array $claims, array $header) use ($privateKey): string {
    $message = encodeGoogle(json_encode($header)) . '.' . encodeGoogle(json_encode($claims));
    openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    return $message . '.' . encodeGoogle($signature);
};
$calls = 0;
$transport = static function (string $url) use (&$calls, $jwk): array {
    checkGoogle($url === 'https://www.googleapis.com/oauth2/v3/certs', 'Untrusted signing-key URL.');
    $calls++;
    return ['keys' => [$jwk]];
};
$cache = sys_get_temp_dir() . '/reform-google-test-' . bin2hex(random_bytes(8)) . '.json';
try {
    $verifier = new GoogleSocialIdentityVerifier(['web-client-id'], $cache, $transport, ['android-client-id', 'ios-client-id', 'web-client-id']);
    $token = $sign($claims, $header);
    checkGoogle($verifier->verify('GOOGLE', null, $token, '') === ['providerUserId' => '123456789', 'email' => 'user@example.test', 'name' => 'Google회원'], 'Signed identity mapping failed.');
    $verifier->verify('GOOGLE', null, $token, '');
    checkGoogle($calls === 1, 'Public signing keys must be cached.');
    $rotatedJwk = array_replace($jwk, ['kid' => 'rotated-key']);
    $rotationCalls = 0;
    $rotationTransport = static function (string $url) use (&$rotationCalls, $jwk, $rotatedJwk): array {
        $rotationCalls++;
        return ['keys' => [$jwk, $rotatedJwk]];
    };
    $rotated = new GoogleSocialIdentityVerifier(['web-client-id'], $cache, $rotationTransport);
    checkGoogle($rotated->verify('GOOGLE', null, $sign($claims, array_replace($header, ['kid' => 'rotated-key'])), '')['providerUserId'] === '123456789', 'Rotated signing key failed.');
    checkGoogle($rotationCalls === 1, 'Unknown kid must refresh valid cached keys.');
    foreach (['accounts.google.com', 'https://accounts.google.com'] as $issuer) {
        checkGoogle($verifier->verify('GOOGLE', null, $sign(array_replace($claims, ['iss' => $issuer]), $header), '')['providerUserId'] === '123456789', 'Supported issuer rejected.');
    }
    $withoutTyp = $header; unset($withoutTyp['typ']);
    checkGoogle($verifier->verify('GOOGLE', null, $sign($claims, $withoutTyp), '')['providerUserId'] === '123456789', 'Optional typ rejected.');
    foreach (['android-client-id', 'ios-client-id', 'web-client-id'] as $azp) {
        checkGoogle($verifier->verify('GOOGLE', null, $sign(array_replace($claims, ['azp' => $azp]), $header), '')['providerUserId'] === '123456789', 'Allowed authorized party rejected.');
    }
    rejectGoogle(fn () => $verifier->verify('GOOGLE', null, $sign(array_replace($claims, ['azp' => 'attacker-client']), $header), ''), UnauthorizedException::class);
    foreach ([false, 'true', null] as $emailVerified) {
        checkGoogle($verifier->verify('GOOGLE', null, $sign(array_replace($claims, ['email_verified' => $emailVerified]), $header), '')['email'] === null, 'Unverified email trusted.');
    }
    $minimal = $claims; unset($minimal['email'], $minimal['name']);
    checkGoogle($verifier->verify('GOOGLE', null, $sign($minimal, $header), '') === ['providerUserId' => '123456789', 'email' => null, 'name' => null], 'Optional consent fields must be optional.');
    foreach ([['iss' => 'https://attacker.test'], ['aud' => 'android-client-id'], ['exp' => time() - 1], ['exp' => '9999999999'], ['iat' => time() + 300], ['sub' => ''], ['sub' => ['invalid']], ['nbf' => time() + 300]] as $changes) {
        rejectGoogle(fn () => $verifier->verify('GOOGLE', null, $sign(array_replace($claims, $changes), $header), ''), UnauthorizedException::class);
    }
    foreach ([['alg' => 'none'], ['alg' => 'HS256'], ['kid' => 'unknown-key'], ['typ' => 'other'], ['jku' => 'https://attacker.test', 'kid' => 'attacker-key'], ['crit' => ['b64']]] as $changes) {
        rejectGoogle(fn () => $verifier->verify('GOOGLE', null, $sign($claims, array_replace($header, $changes)), ''), UnauthorizedException::class);
    }
    $parts = explode('.', $token);
    $parts[1] = encodeGoogle(json_encode(array_replace($claims, ['sub' => '999999'])));
    rejectGoogle(fn () => $verifier->verify('GOOGLE', null, implode('.', $parts), ''), UnauthorizedException::class);
    foreach (['', 'a.b.c', $token . '.extra', 'not-a-jwt', str_repeat('a', 10001)] as $invalid) {
        rejectGoogle(fn () => $verifier->verify('GOOGLE', null, $invalid, ''), UnauthorizedException::class);
    }
    rejectGoogle(fn () => $verifier->verify('GOOGLE', 'code', $token, ''), ValidationException::class);
    rejectGoogle(fn () => $verifier->verify('GOOGLE', null, null, ''), ValidationException::class);
    rejectGoogle(fn () => (new GoogleSocialIdentityVerifier([], null, $transport))->verify('GOOGLE', null, $token, ''), ServiceUnavailableException::class);
    $offline = static fn (string $url): array => throw new ServiceUnavailableException('GOOGLE_UNAVAILABLE', 'offline');
    // Current cached keys allow verification during a transient network failure.
    checkGoogle((new GoogleSocialIdentityVerifier(['web-client-id'], $cache, $offline))->verify('GOOGLE', null, $token, '')['providerUserId'] === '123456789', 'Valid cache was ignored.');
    file_put_contents($cache, json_encode(['expiresAt' => time() - 1, 'jwks' => ['keys' => [$jwk]]]));
    rejectGoogle(fn () => (new GoogleSocialIdentityVerifier(['web-client-id'], $cache, $offline))->verify('GOOGLE', null, $token, ''), ServiceUnavailableException::class);
    rejectGoogle(fn () => (new GoogleSocialIdentityVerifier(['web-client-id'], null, static fn (): array => ['bad' => []]))->verify('GOOGLE', null, $token, ''), ServiceUnavailableException::class);
    // Unwritable cache is optional and must not prevent verification.
    checkGoogle((new GoogleSocialIdentityVerifier(['web-client-id'], $cache . '/missing/cache.json', $transport))->verify('GOOGLE', null, $token, '')['providerUserId'] === '123456789', 'Cache write failure broke login.');
    $router = new ProviderSocialIdentityVerifier(['GOOGLE' => $verifier, 'NAVER' => new NaverSocialIdentityVerifier('', '', [], null)]);
    rejectGoogle(fn () => $router->verify('APPLE', null, $token, ''), ServiceUnavailableException::class);
    rejectGoogle(fn () => $router->verify('NAVER', 'code', null, '', 'state'), ServiceUnavailableException::class);

    // A malformed ID token reaches provider verification without redirectUri;
    // neither repository nor transaction is accessed for rejected credentials.
    $auth = new AuthService(
        (new ReflectionClass(AuthRepository::class))->newInstanceWithoutConstructor(),
        $router,
        (new ReflectionClass(TransactionManager::class))->newInstanceWithoutConstructor(),
        0, 0,
    );
    $controller = new AuthController($auth);
    $body = ['provider' => 'google', 'idToken' => 'invalid-token', 'device' => ['deviceId' => 'test-device']];
    $request = static fn (array $body): Request => Request::create('/api/v1/auth/social-login', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    rejectGoogle(fn () => $controller->socialLogin($request($body)), UnauthorizedException::class);
    foreach ([['authorizationCode' => 'code', 'idToken' => 'token'], ['authorizationCode' => 'code'], ['idToken' => null], ['idToken' => ['bad']]] as $changes) {
        rejectGoogle(fn () => $controller->socialLogin($request(array_replace($body, $changes))), ValidationException::class);
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
    checkGoogle($services['auth']['access_ttl_seconds'] === 0 && $services['auth']['refresh_ttl_seconds'] === 0, 'Production inherited a local development expiry policy.');
} finally {
    foreach (['APP_ENV' => $environment, 'AUTH_ACCESS_TTL_SECONDS' => $accessTtl, 'AUTH_REFRESH_TTL_SECONDS' => $refreshTtl] as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}

echo "Google login tests passed.\n";
