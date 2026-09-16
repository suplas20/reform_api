<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BusinessReadRepository;
use App\Service\BusinessService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertBusinessApiValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            sprintf(
                "%s\nExpected: %s\nActual: %s\n",
                $message,
                var_export($expected, true),
                var_export($actual, true),
            ),
        );
        exit(1);
    }
}

$repository = new class implements BusinessReadRepository {
    public function countPublic(string $publishedAt): int
    {
        $this->validatePublishedAt($publishedAt);

        return 2;
    }

    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
    ): array {
        $this->validatePublishedAt($publishedAt);

        return array_slice([
            [
                'id' => 123,
                'name' => 'ABC 가죽공방',
                'slug' => 'abc-leather',
                'short_description' => '가죽 제품을 복원합니다.',
                'supports_shipping' => 1,
                'supports_visit' => 0,
                'supports_pickup' => 1,
                'published_at' => '2026-09-15 00:00:00',
                'rating_avg' => '4.80',
                'review_count' => 24,
                'favorite_count' => 52,
                'work_case_count' => 18,
            ],
            [
                'id' => 124,
                'name' => 'XYZ 수선실',
                'slug' => 'xyz-repair',
                'short_description' => null,
                'supports_shipping' => 0,
                'supports_visit' => 1,
                'supports_pickup' => 0,
                'published_at' => '2026-09-14 00:00:00',
                'rating_avg' => null,
                'review_count' => 0,
                'favorite_count' => 0,
                'work_case_count' => 0,
            ],
        ], $offset, $limit);
    }

    public function findPublicDetailById(int $id, string $publishedAt): ?array
    {
        $this->validatePublishedAt($publishedAt);

        if ($id !== 123) {
            return null;
        }

        return [
            'id' => 123,
            'name' => 'ABC 가죽공방',
            'slug' => 'abc-leather',
            'short_description' => '가죽 제품을 복원합니다.',
            'description' => null,
            'phone' => '02-1234-5678',
            'supports_shipping' => 1,
            'supports_visit' => 0,
            'supports_pickup' => 1,
            'published_at' => '2026-09-15 00:00:00',
            'rating_avg' => '4.80',
            'review_count' => 24,
            'favorite_count' => 52,
            'work_case_count' => 18,
        ];
    }

    public function findActiveExternalReputationByBusinessIds(array $businessIds): array
    {
        if (!in_array(123, $businessIds, true)) {
            return [];
        }

        return [
            [
                'business_id' => 123,
                'provider' => 'GOOGLE',
                'external_url' => 'https://example.com/google-place',
                'rating' => '4.60',
                'review_count' => 128,
            ],
            [
                'business_id' => 123,
                'provider' => 'NAVER',
                'external_url' => 'https://example.com/naver-place',
                'rating' => null,
                'review_count' => null,
            ],
        ];
    }

    private function validatePublishedAt(string $publishedAt): void
    {
        if (DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $publishedAt) === false) {
            throw new RuntimeException('Published-at filter must use a valid UTC datetime.');
        }
    }
};

$controller = new BusinessController(new BusinessService($repository));
$apiDocumentationController = new ApiDocumentationController(
    dirname(__DIR__) . '/openapi/openapi.json',
    dirname(__DIR__) . '/vendor/swagger-api/swagger-ui/dist',
);
$routes = new RouteCollection();
$registerRoutes = require dirname(__DIR__) . '/routes/api.php';
$registerRoutes($routes, $controller, $apiDocumentationController);

$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(
    new UrlMatcher($routes, new RequestContext()),
    new ExceptionHandler($logger),
);

$listResponse = $application->handle(
    Request::create('/api/v1/businesses', 'GET'),
);
$listPayload = json_decode($listResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(200, $listResponse->getStatusCode(), 'Business list must return HTTP 200.');
assertBusinessApiValue(2, count($listPayload['data']), 'Business list item count is invalid.');
assertBusinessApiValue(123, $listPayload['data'][0]['id'], 'First Business list item is invalid.');
assertBusinessApiValue(4.8, $listPayload['data'][0]['reputation']['internal']['rating'], 'List reputation is invalid.');
assertBusinessApiValue('GOOGLE', $listPayload['data'][0]['reputation']['external'][0]['provider'], 'List external reputation is invalid.');
assertBusinessApiValue(1, $listPayload['meta']['page'], 'Default page is invalid.');
assertBusinessApiValue(20, $listPayload['meta']['perPage'], 'Default perPage is invalid.');
assertBusinessApiValue(2, $listPayload['meta']['total'], 'Business total is invalid.');
assertBusinessApiValue(1, $listPayload['meta']['totalPages'], 'Business totalPages is invalid.');

$secondPageResponse = $application->handle(
    Request::create('/api/v1/businesses?page=2&perPage=1', 'GET'),
);
$secondPagePayload = json_decode($secondPageResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(124, $secondPagePayload['data'][0]['id'], 'Second Business page is invalid.');
assertBusinessApiValue(2, $secondPagePayload['meta']['page'], 'Requested page is invalid.');
assertBusinessApiValue(1, $secondPagePayload['meta']['perPage'], 'Requested perPage is invalid.');
assertBusinessApiValue(2, $secondPagePayload['meta']['totalPages'], 'Paginated totalPages is invalid.');

foreach ([
    '/api/v1/businesses?page=0',
    '/api/v1/businesses?perPage=101',
    '/api/v1/businesses?page[]=1',
] as $invalidPaginationPath) {
    $invalidPaginationResponse = $application->handle(
        Request::create($invalidPaginationPath, 'GET'),
    );
    $invalidPaginationPayload = json_decode(
        $invalidPaginationResponse->getContent(),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    assertBusinessApiValue(422, $invalidPaginationResponse->getStatusCode(), 'Invalid pagination must return HTTP 422.');
    assertBusinessApiValue('INVALID_PAGINATION', $invalidPaginationPayload['error']['code'], 'Pagination error code is invalid.');
}

$successResponse = $application->handle(
    Request::create('/api/v1/businesses/123', 'GET'),
);
$successPayload = json_decode($successResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(200, $successResponse->getStatusCode(), 'Business detail must return HTTP 200.');
assertBusinessApiValue(true, $successPayload['success'], 'Business detail success contract is invalid.');
assertBusinessApiValue(123, $successPayload['data']['id'], 'Business ID is invalid.');
assertBusinessApiValue('ABC 가죽공방', $successPayload['data']['name'], 'Business name is invalid.');
assertBusinessApiValue(true, $successPayload['data']['supports']['shipping'], 'Shipping support is invalid.');
assertBusinessApiValue(false, $successPayload['data']['supports']['visit'], 'Visit support is invalid.');
assertBusinessApiValue('2026-09-15T00:00:00Z', $successPayload['data']['publishedAt'], 'Published time is invalid.');
assertBusinessApiValue(4.8, $successPayload['data']['reputation']['internal']['rating'], 'Internal rating is invalid.');
assertBusinessApiValue(24, $successPayload['data']['reputation']['internal']['reviewCount'], 'Review count is invalid.');
assertBusinessApiValue('GOOGLE', $successPayload['data']['reputation']['external'][0]['provider'], 'Google reputation is missing.');
assertBusinessApiValue(4.6, $successPayload['data']['reputation']['external'][0]['rating'], 'Google rating is invalid.');
assertBusinessApiValue(null, $successPayload['data']['reputation']['external'][1]['rating'], 'Naver rating must be null.');

$notFoundResponse = $application->handle(
    Request::create('/api/v1/businesses/999', 'GET'),
);
$notFoundPayload = json_decode($notFoundResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(404, $notFoundResponse->getStatusCode(), 'Missing Business must return HTTP 404.');
assertBusinessApiValue('BUSINESS_NOT_FOUND', $notFoundPayload['error']['code'], 'Business error code is invalid.');

$invalidIdResponse = $application->handle(
    Request::create('/api/v1/businesses/0', 'GET'),
);
$invalidIdPayload = json_decode($invalidIdResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(422, $invalidIdResponse->getStatusCode(), 'Invalid Business ID must return HTTP 422.');
assertBusinessApiValue('INVALID_BUSINESS_ID', $invalidIdPayload['error']['code'], 'Validation error code is invalid.');

$methodResponse = $application->handle(
    Request::create('/api/v1/businesses/123', 'POST'),
);

assertBusinessApiValue(405, $methodResponse->getStatusCode(), 'Invalid method must return HTTP 405.');
assertBusinessApiValue('GET', $methodResponse->headers->get('Allow'), 'Business Allow header is invalid.');

fwrite(STDOUT, "Business API tests passed.\n");
