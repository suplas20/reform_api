<?php

declare(strict_types=1);

use App\Auth\NaverSocialIdentityVerifier;
use App\Controller\Api\V1\AuthController;
use App\Exception\ServiceUnavailableException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;

require (getenv('REFORM_TEST_ROOT') ?: dirname(__DIR__)) . '/vendor/autoload.php';
if (getenv('REFORM_TEST_ROOT')) {
    spl_autoload_register(static function (string $class): void {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (str_starts_with($class, 'App\\') && is_file($path)) {
            require $path;
        }
    }, true, true);
}

function checkNaver(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejectNaver(callable $operation, string $class): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        checkNaver($exception instanceof $class, 'Unexpected exception: ' . $exception::class);
        return;
    }
    throw new RuntimeException('Expected authentication rejection.');
}

$uri = 'https://api.example.test/api/v1/auth/naver/callback';
$state = 'login-state-12345678901234567890';
$calls = [];
$token = ['access_token' => 'naver-access-token'];
$profile = ['resultcode' => '00', 'response' => ['id' => 'naver-user-id', 'email' => 'user@example.test', 'name' => '네이버회원']];
$transport = static function (string $url, string $method, array $headers, ?string $body) use (&$calls, &$token, &$profile): array {
    $calls[] = compact('url', 'method', 'headers', 'body');
    return $method === 'POST' ? $token : $profile;
};
$verifier = new NaverSocialIdentityVerifier('client-id', 'secret-value', [$uri], $transport);
$identity = $verifier->verify('NAVER', 'authorization-code', null, $uri, $state);
checkNaver($identity === ['providerUserId' => 'naver-user-id', 'email' => 'user@example.test', 'name' => '네이버회원'], 'Profile mapping failed.');
parse_str($calls[0]['body'], $body);
checkNaver($calls[0]['url'] === 'https://nid.naver.com/oauth2.0/token', 'Wrong token endpoint.');
checkNaver($body['client_secret'] === 'secret-value' && $body['state'] === $state && $body['redirect_uri'] === $uri, 'Code exchange must include server secret, state and registered callback.');
checkNaver($calls[1]['headers']['Authorization'] === 'Bearer naver-access-token', 'Profile must be fetched using the provider token.');

$profile = ['resultcode' => '00', 'response' => ['id' => 'only-id']];
checkNaver($verifier->verify('NAVER', 'code', null, $uri, $state) === ['providerUserId' => 'only-id', 'email' => null, 'name' => null], 'Optional profile data must remain optional.');
$count = count($calls);
foreach ([[$uri, null], [$uri, 'short'], ['https://attacker.test/callback', $state]] as [$callback, $nonce]) {
    rejectNaver(fn () => $verifier->verify('NAVER', 'code', null, $callback, $nonce), ValidationException::class);
}
rejectNaver(fn () => $verifier->verify('NAVER', null, 'untrusted-id-token', $uri, $state), ValidationException::class);
checkNaver(count($calls) === $count, 'Invalid input must not call NAVER.');
rejectNaver(fn () => $verifier->verify('KAKAO', 'code', null, $uri), ServiceUnavailableException::class);
foreach (['', 'client-id'] as $secret) {
    rejectNaver(fn () => (new NaverSocialIdentityVerifier('client-id', $secret, [$uri], $transport))->verify('NAVER', 'code', null, $uri, $state), ServiceUnavailableException::class);
}
$token = ['error' => 'invalid_grant'];
$before = count($calls);
rejectNaver(fn () => $verifier->verify('NAVER', 'code', null, $uri, $state), UnauthorizedException::class);
checkNaver(count($calls) === $before + 1, 'Rejected token must not fetch a profile.');
$token = ['access_token' => 'token'];
foreach ([['resultcode' => '00', 'response' => []], ['resultcode' => 'error'], ['resultcode' => '00', 'response' => ['id' => ['bad']]]] as $invalidProfile) {
    $profile = $invalidProfile;
    rejectNaver(fn () => $verifier->verify('NAVER', 'code', null, $uri, $state), UnauthorizedException::class);
}

// The callback relays authentication results without needing a database session.
$controller = (new ReflectionClass(AuthController::class))->newInstanceWithoutConstructor();
$callback = $controller->naverCallback(Request::create('/api/v1/auth/naver/callback', 'GET', ['code' => 'code&value', 'state' => $state, 'returnUrl' => 'https://attacker.test']));
$location = $callback->headers->get('Location');
checkNaver(str_starts_with($location, 'reform://auth/naver-callback?'), 'Callback must always use the fixed app destination.');
parse_str(parse_url($location, PHP_URL_QUERY), $query);
checkNaver($query === ['state' => $state, 'code' => 'code&value'], 'Only encoded code/state may be relayed.');
checkNaver(str_contains($callback->headers->get('Cache-Control'), 'no-store'), 'Callback must not be cached.');
$declined = $controller->naverCallback(Request::create('/api/v1/auth/naver/callback', 'GET', ['error' => 'access_denied', 'state' => $state]));
checkNaver(str_contains($declined->headers->get('Location'), 'error=access_denied'), 'Cancellation must return to the app.');
foreach ([[], ['state' => 'short', 'code' => 'code'], ['state' => $state, 'code' => ['invalid']]] as $query) {
    rejectNaver(fn () => $controller->naverCallback(Request::create('/api/v1/auth/naver/callback', 'GET', $query)), ValidationException::class);
}

echo "NAVER login tests passed.\n";
