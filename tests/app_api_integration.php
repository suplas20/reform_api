<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Support\ApplicationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertAppApi(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed>|null $body */
function appRequest(
    App\Http\Application $application,
    string $method,
    string $path,
    ?array $body = null,
    ?string $accessToken = null,
    array $headers = [],
): Response {
    $server = ['CONTENT_TYPE' => 'application/json', ...$headers];

    if ($accessToken !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $accessToken;
    }

    return $application->handle(Request::create(
        $path,
        $method,
        server: $server,
        content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
    ));
}

/** @return array<string, mixed> */
function responseJson(Response $response): array
{
    return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
}

putenv('AUTH_ACCESS_TTL_SECONDS=900');
putenv('AUTH_REFRESH_TTL_SECONDS=3600');
putenv('APP_BASE_URL=http://127.0.0.1:8080');
putenv('UPLOAD_MAX_IMAGE_BYTES=5242880');
putenv('AD_TRACKING_SECRET=integration-test-secret');
putenv('APP_MINIMUM_VERSION=1.0.0');
putenv('APP_LATEST_VERSION=1.1.0');

$config = require dirname(__DIR__) . '/config/database.php';
$connection = (new ConnectionFactory($config))->connection();
$now = gmdate('Y-m-d H:i:s');
$future = gmdate('Y-m-d H:i:s', time() + 3600);
$accessToken = bin2hex(random_bytes(32));
$refreshToken = bin2hex(random_bytes(48));
$logoutAccessToken = bin2hex(random_bytes(32));
$logoutRefreshToken = bin2hex(random_bytes(48));
$userId = 0;
$businessId = null;

try {
    $insertUser = $connection->prepare(
        'INSERT INTO users (name, email, status, created_at, updated_at) VALUES (:name, :email, :status, :created_at, :updated_at)',
    );
    $insertUser->execute([
        'name' => 'API 통합 테스트',
        'email' => 'app-api-' . bin2hex(random_bytes(8)) . '@example.test',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $userId = (int) $connection->lastInsertId();

    $insertSession = $connection->prepare(
        <<<'SQL'
            INSERT INTO auth_sessions
                (user_id, device_id, access_token_hash, refresh_token_hash,
                 access_expires_at, refresh_expires_at, created_at)
            VALUES
                (:user_id, :device_id, :access_token_hash, :refresh_token_hash,
                 :access_expires_at, :refresh_expires_at, :created_at)
            SQL,
    );

    foreach ([
        ['device-main', $accessToken, $refreshToken],
        ['device-logout', $logoutAccessToken, $logoutRefreshToken],
    ] as [$deviceId, $access, $refresh]) {
        $insertSession->execute([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'access_token_hash' => hash('sha256', $access),
            'refresh_token_hash' => hash('sha256', $refresh),
            'access_expires_at' => $future,
            'refresh_expires_at' => $future,
            'created_at' => $now,
        ]);
    }

    $application = ApplicationFactory::create(dirname(__DIR__));

    $protectedWithoutToken = appRequest($application, 'GET', '/api/v1/me');
    assertAppApi($protectedWithoutToken->getStatusCode() === 401, 'Protected API must reject missing bearer tokens.');

    foreach ([
        ['GET', '/api/v1/me', null, 200],
        ['PATCH', '/api/v1/me', ['nickname' => '통합테스트', 'avatarMediaId' => null], 200],
        ['GET', '/api/v1/me/activity-counts', null, 200],
        ['GET', '/api/v1/me/favorites', null, 200],
        ['GET', '/api/v1/me/reviews', null, 200],
        ['GET', '/api/v1/me/inquiries', null, 200],
        ['GET', '/api/v1/me/recent-businesses', null, 200],
        ['DELETE', '/api/v1/me/recent-businesses', null, 204],
        ['GET', '/api/v1/me/notifications', null, 200],
        ['PATCH', '/api/v1/me/notifications/read-all', null, 204],
        ['GET', '/api/v1/me/settings', null, 200],
        ['PATCH', '/api/v1/me/settings', ['marketingConsent' => true], 200],
        ['PUT', '/api/v1/me/devices/integration-device', [
            'platform' => 'android',
            'pushToken' => 'integration-push-token',
            'appVersion' => '1.0.0',
            'locale' => 'ko-KR',
            'timezone' => 'Asia/Seoul',
        ], 204],
        ['DELETE', '/api/v1/me/devices/integration-device', null, 204],
        ['POST', '/api/v1/media/presigned-uploads', ['files' => [[
            'fileName' => 'fixture.jpg',
            'contentType' => 'image/jpeg',
            'size' => 4,
            'purpose' => 'review',
        ]]], 201],
    ] as [$method, $path, $body, $expectedStatus]) {
        $response = appRequest($application, $method, $path, $body, $accessToken);
        assertAppApi(
            $response->getStatusCode() === $expectedStatus,
            sprintf('%s %s returned %d instead of %d.', $method, $path, $response->getStatusCode(), $expectedStatus),
        );
    }

    foreach ([
        ['/api/v1/me/reviews?page=1&perPage=1', $accessToken],
        ['/api/v1/me/favorites?page=1&perPage=1', $accessToken],
        ['/api/v1/me/inquiries?page=1&perPage=1', $accessToken],
        ['/api/v1/me/recent-businesses?page=1&perPage=1', $accessToken],
        ['/api/v1/me/notifications?page=1&perPage=1', $accessToken],
        ['/api/v1/help/faqs?page=1&perPage=1', null],
    ] as [$path, $token]) {
        $response = appRequest($application, 'GET', $path, accessToken: $token);
        $payload = responseJson($response);
        assertAppApi($response->getStatusCode() === 200, sprintf('Paginated API %s must return 200.', $path));
        assertAppApi(($payload['meta']['page'] ?? null) === 1, sprintf('Paginated API %s must return meta.page.', $path));
        assertAppApi(($payload['meta']['perPage'] ?? null) === 1, sprintf('Paginated API %s must return meta.perPage.', $path));
        assertAppApi(isset($payload['meta']['total']), sprintf('Paginated API %s must return meta.total.', $path));
        assertAppApi(isset($payload['meta']['totalPages']), sprintf('Paginated API %s must return meta.totalPages.', $path));
    }

    $appConfig = appRequest($application, 'GET', '/api/v1/app-config?platform=android&appVersion=1.0.0');
    assertAppApi($appConfig->getStatusCode() === 200, 'App configuration API must use configured versions.');

    $socialLogin = appRequest($application, 'POST', '/api/v1/auth/social-login', [
        'provider' => 'kakao',
        'authorizationCode' => 'provider-code',
        'redirectUri' => 'reform://auth/callback',
        'device' => ['deviceId' => 'integration-device'],
    ]);
    assertAppApi($socialLogin->getStatusCode() === 503, 'Unconfigured social verification must fail closed.');

    $logout = appRequest($application, 'POST', '/api/v1/auth/logout', [
        'refreshToken' => $logoutRefreshToken,
        'deviceId' => 'device-logout',
    ], $logoutAccessToken);
    assertAppApi($logout->getStatusCode() === 204, 'Logout must revoke the matching app session.');

    $businessStatement = $connection->prepare(
        <<<'SQL'
            SELECT id FROM businesses
            WHERE status = :status AND deleted_at IS NULL
              AND published_at IS NOT NULL AND published_at <= :now
            ORDER BY id ASC LIMIT 1
            SQL,
    );
    $businessStatement->execute(['status' => 'ACTIVE', 'now' => $now]);
    $businessValue = $businessStatement->fetchColumn();

    if ($businessValue !== false) {
        $businessId = (int) $businessValue;

        foreach ([
            ['PUT', '/api/v1/me/favorites/' . $businessId, null, 204],
            ['PUT', '/api/v1/me/recent-businesses/' . $businessId, null, 204],
            ['POST', '/api/v1/businesses/' . $businessId . '/inquiries', [
                'type' => 'quote',
                'preferredDateType' => 'afterConsultation',
                'message' => '통합 테스트 문의입니다.',
                'mediaIds' => [],
            ], 201],
        ] as [$method, $path, $body, $expectedStatus]) {
            $response = appRequest($application, $method, $path, $body, $accessToken);
            assertAppApi($response->getStatusCode() === $expectedStatus, sprintf('%s %s integration write failed.', $method, $path));
        }

        $reviewPath = '/api/v1/businesses/' . $businessId . '/reviews';
        $reviewBody = ['rating' => 5, 'content' => '멱등성 통합 테스트', 'tags' => ['테스트'], 'mediaIds' => []];
        $idempotencyHeader = ['HTTP_IDEMPOTENCY_KEY' => 'integration-' . bin2hex(random_bytes(8))];
        $firstReview = appRequest($application, 'POST', $reviewPath, $reviewBody, $accessToken, $idempotencyHeader);
        $secondReview = appRequest($application, 'POST', $reviewPath, $reviewBody, $accessToken, $idempotencyHeader);
        assertAppApi($firstReview->getStatusCode() === 201 && $secondReview->getStatusCode() === 201, 'Idempotent review writes must succeed.');
        assertAppApi(
            responseJson($firstReview)['data']['id'] === responseJson($secondReview)['data']['id'],
            'Idempotent review retry must return the original review.',
        );

        $deleteFavorite = appRequest($application, 'DELETE', '/api/v1/me/favorites/' . $businessId, null, $accessToken);
        assertAppApi($deleteFavorite->getStatusCode() === 204, 'Favorite deletion must be idempotent.');
    }

    $refresh = appRequest($application, 'POST', '/api/v1/auth/refresh', [
        'refreshToken' => $refreshToken,
        'deviceId' => 'device-main',
    ]);
    assertAppApi($refresh->getStatusCode() === 200, 'Refresh token rotation must succeed.');
} finally {
    if ($userId > 0) {
        $deleteUser = $connection->prepare('DELETE FROM users WHERE id = :id');
        $deleteUser->execute(['id' => $userId]);
    }

    if ($businessId !== null) {
        $restoreStats = $connection->prepare(
            <<<'SQL'
                UPDATE business_reputation_stats
                SET review_count = (SELECT COUNT(*) FROM reviews WHERE business_id = :reviews_business_id AND status = :review_status AND deleted_at IS NULL),
                    rating_avg = (SELECT AVG(rating) FROM reviews WHERE business_id = :rating_business_id AND status = :rating_status AND deleted_at IS NULL),
                    favorite_count = (SELECT COUNT(*) FROM user_favorites WHERE business_id = :favorites_business_id),
                    updated_at = :updated_at
                WHERE business_id = :business_id
                SQL,
        );
        $restoreStats->execute([
            'reviews_business_id' => $businessId,
            'review_status' => 'ACTIVE',
            'rating_business_id' => $businessId,
            'rating_status' => 'ACTIVE',
            'favorites_business_id' => $businessId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'business_id' => $businessId,
        ]);
    }
}

fwrite(STDOUT, "App API integration tests passed.\n");
