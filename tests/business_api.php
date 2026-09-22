<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Controller\Api\V1\EventController;
use App\Controller\Api\V1\ReferenceController;
use App\Controller\Api\V1\SearchController;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BusinessReadRepository;
use App\Service\BusinessService;
use App\Service\EventRecorder;
use App\Service\ReferenceDataProvider;
use App\Service\SearchDataProvider;
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
    /** @var array<string, mixed> */
    public array $lastCriteria = [];

    public function countPublic(string $publishedAt, array $criteria = []): int
    {
        $this->validatePublishedAt($publishedAt);
        $this->lastCriteria = $criteria;

        return 2;
    }

    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
        array $criteria = [],
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

    public function activeCategoryServiceCombinationExists(int $categoryId, int $serviceId): bool
    {
        return !($categoryId === 1 && $serviceId === 999);
    }

    public function findPublicSummaryRelations(array $businessIds): array
    {
        return [
            'locations' => [[
                'business_id' => 123,
                'region_id' => 10,
                'road_address' => '서울 강남구 테스트로 1',
                'latitude' => '37.5000000',
                'longitude' => '127.0300000',
                'is_primary' => 1,
                'id' => 1,
            ]],
            'categories' => [[
                'business_id' => 123,
                'category_id' => 1,
                'is_primary' => 1,
                'sort_order' => 1,
            ]],
            'services' => [['business_id' => 123, 'service_id' => 10]],
            'brands' => [['business_id' => 123, 'brand_id' => 100]],
            'verifiedBusinessIds' => [123],
        ];
    }

    public function findPublicByIds(array $businessIds, string $publishedAt): array
    {
        return $this->findPublicPage($publishedAt, 100, 0);
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

    public function findPublicDetailRelations(int $businessId): array
    {
        return [
            'primaryLocation' => [
                'region_id' => 10,
                'region_name' => '강남구',
                'postal_code' => '00000',
                'road_address' => '서울 강남구 테스트로 1',
                'jibun_address' => null,
                'address_detail' => '2층',
                'latitude' => '37.5000000',
                'longitude' => '127.0300000',
            ],
            'hours' => [
                [
                    'day_of_week' => 1,
                    'sequence' => 1,
                    'open_time' => '10:00:00',
                    'close_time' => '13:00:00',
                    'is_closed' => 0,
                ],
                [
                    'day_of_week' => 1,
                    'sequence' => 2,
                    'open_time' => '14:00:00',
                    'close_time' => '19:00:00',
                    'is_closed' => 0,
                ],
                [
                    'day_of_week' => 7,
                    'sequence' => 1,
                    'open_time' => null,
                    'close_time' => null,
                    'is_closed' => 1,
                ],
            ],
            'categories' => [['id' => 1, 'name' => '가방', 'is_primary' => 1]],
            'offerings' => [[
                'id' => 1001,
                'category_id' => 1,
                'category_name' => '가방',
                'service_id' => 10,
                'service_name' => '염색',
                'title' => null,
                'description' => null,
                'price_min' => null,
                'price_max' => null,
                'price_note' => '상담',
                'duration_min_days' => 1,
                'duration_max_days' => 3,
                'is_primary' => 1,
            ]],
            'brands' => [['id' => 100, 'name' => 'Chanel', 'slug' => 'chanel']],
            'links' => [[
                'link_type' => 'WEBSITE',
                'label' => '홈페이지',
                'url' => 'https://example.com',
            ]],
            'verificationTypes' => ['BUSINESS_REGISTRATION', 'PHONE'],
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
                'fetched_at' => '2026-09-16 00:30:00',
            ],
            [
                'business_id' => 123,
                'provider' => 'NAVER',
                'external_url' => 'https://example.com/naver-place',
                'rating' => null,
                'review_count' => null,
                'fetched_at' => null,
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
$referenceController = new ReferenceController(
    new class implements ReferenceDataProvider {
        public function getCategories(?int $parentId, ?int $level): array
        {
            return [];
        }

        public function getServices(?int $categoryId, ?int $limit): array
        {
            return [];
        }

        public function getRegions(?int $parentId, ?int $level, ?string $keyword): array
        {
            return [];
        }

        public function getBrands(?string $keyword, int $page, int $perPage): array
        {
            return ['items' => [], 'total' => 0, 'totalPages' => 0];
        }
    },
);
$eventController = new EventController(
    new class implements EventRecorder {
        public function record(
            ?int $userId,
            ?string $sessionKey,
            string $eventName,
            ?string $entityType,
            ?int $entityId,
            ?array $metadata,
        ): void {
        }
    },
);
$searchController = new SearchController(
    new class implements SearchDataProvider {
        public function getSuggestions(string $keyword, int $limit): array
        {
            return ['businesses' => [], 'services' => [], 'brands' => []];
        }
    },
);
$routes = new RouteCollection();
$registerRoutes = require dirname(__DIR__) . '/routes/api.php';
$registerRoutes(
    $routes,
    $referenceController,
    $controller,
    $eventController,
    $searchController,
    $apiDocumentationController,
);

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
assertBusinessApiValue(1, $listPayload['data'][0]['categoryId'], 'App category summary is invalid.');
assertBusinessApiValue([10], $listPayload['data'][0]['serviceIds'], 'App service summary is invalid.');
assertBusinessApiValue('서울 강남구 테스트로 1', $listPayload['data'][0]['address'], 'App address summary is invalid.');
assertBusinessApiValue(true, $listPayload['data'][0]['verified'], 'App verification summary is invalid.');
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

$filteredResponse = $application->handle(
    Request::create(
        '/api/v1/businesses?keyword=%EA%B0%80%EC%A3%BD&categoryId=1&serviceId=10'
        . '&regionId=2&brandId=3&lat=37.5&lng=127.03&radiusKm=5'
        . '&verifiedOnly=true&sort=distance',
        'GET',
    ),
);
assertBusinessApiValue(200, $filteredResponse->getStatusCode(), 'Filtered Business list is invalid.');
assertBusinessApiValue('가죽', $repository->lastCriteria['keyword'], 'Keyword filter is invalid.');
assertBusinessApiValue(1, $repository->lastCriteria['categoryId'], 'Category filter is invalid.');
assertBusinessApiValue(10, $repository->lastCriteria['serviceId'], 'Service filter is invalid.');
assertBusinessApiValue(37.5, $repository->lastCriteria['lat'], 'Latitude filter is invalid.');
assertBusinessApiValue(127.03, $repository->lastCriteria['lng'], 'Longitude filter is invalid.');
assertBusinessApiValue(5.0, $repository->lastCriteria['radiusKm'], 'Radius filter is invalid.');
assertBusinessApiValue(true, $repository->lastCriteria['verifiedOnly'], 'Verification filter is invalid.');
assertBusinessApiValue('distance', $repository->lastCriteria['sort'], 'Sort filter is invalid.');

$appAliasResponse = $application->handle(
    Request::create(
        '/api/v1/businesses?query=%EA%B0%80%EC%A3%BD&latitude=37.5&longitude=127.03&limit=1',
        'GET',
    ),
);
$appAliasPayload = json_decode($appAliasResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertBusinessApiValue(200, $appAliasResponse->getStatusCode(), 'App query aliases must be accepted.');
assertBusinessApiValue('가죽', $repository->lastCriteria['keyword'], 'Query alias is invalid.');
assertBusinessApiValue(37.5, $repository->lastCriteria['lat'], 'Latitude alias is invalid.');
assertBusinessApiValue(127.03, $repository->lastCriteria['lng'], 'Longitude alias is invalid.');
assertBusinessApiValue(1, $appAliasPayload['meta']['perPage'], 'Limit alias is invalid.');

foreach ([
    '/api/v1/businesses?page=0',
    '/api/v1/businesses?perPage=101',
    '/api/v1/businesses?page[]=1',
    '/api/v1/businesses?lat=37.5',
    '/api/v1/businesses?radiusKm=5',
    '/api/v1/businesses?sort=distance',
    '/api/v1/businesses?sort=unknown',
    '/api/v1/businesses?verifiedOnly=maybe',
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
    assertBusinessApiValue('VALIDATION_ERROR', $invalidPaginationPayload['error']['code'], 'Pagination error code is invalid.');
}

$invalidCombinationResponse = $application->handle(
    Request::create('/api/v1/businesses?categoryId=1&serviceId=999', 'GET'),
);
$invalidCombinationPayload = json_decode(
    $invalidCombinationResponse->getContent(),
    true,
    flags: JSON_THROW_ON_ERROR,
);
assertBusinessApiValue(422, $invalidCombinationResponse->getStatusCode(), 'Invalid filter combination must return 422.');
assertBusinessApiValue(
    'FILTER_COMBINATION_INVALID',
    $invalidCombinationPayload['error']['code'],
    'Filter combination error code is invalid.',
);

$successResponse = $application->handle(
    Request::create('/api/v1/businesses/123', 'GET'),
);
$successPayload = json_decode($successResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertBusinessApiValue(200, $successResponse->getStatusCode(), 'Business detail must return HTTP 200.');
assertBusinessApiValue(true, $successPayload['success'], 'Business detail success contract is invalid.');
assertBusinessApiValue(123, $successPayload['data']['id'], 'Business ID is invalid.');
assertBusinessApiValue('ABC 가죽공방', $successPayload['data']['name'], 'Business name is invalid.');
assertBusinessApiValue(true, $successPayload['data']['supportModes']['shipping'], 'Shipping support is invalid.');
assertBusinessApiValue(false, $successPayload['data']['supportModes']['visit'], 'Visit support is invalid.');
assertBusinessApiValue(true, $successPayload['data']['verification']['isVerified'], 'Verification is invalid.');
assertBusinessApiValue('강남구', $successPayload['data']['primaryLocation']['regionName'], 'Location is invalid.');
assertBusinessApiValue(2, count($successPayload['data']['hours'][0]['periods']), 'Business hour periods are invalid.');
assertBusinessApiValue(true, $successPayload['data']['hours'][1]['isClosed'], 'Closed Business hour is invalid.');
assertBusinessApiValue('가방', $successPayload['data']['categories'][0]['name'], 'Category is invalid.');
assertBusinessApiValue('염색', $successPayload['data']['offerings'][0]['serviceName'], 'Offering is invalid.');
assertBusinessApiValue('Chanel', $successPayload['data']['brands'][0]['name'], 'Brand is invalid.');
assertBusinessApiValue([], $successPayload['data']['images'], 'Images must remain deferred.');
assertBusinessApiValue(4.8, $successPayload['data']['reputation']['internal']['rating'], 'Internal rating is invalid.');
assertBusinessApiValue(24, $successPayload['data']['reputation']['internal']['reviewCount'], 'Review count is invalid.');
assertBusinessApiValue('GOOGLE', $successPayload['data']['reputation']['external'][0]['provider'], 'Google reputation is missing.');
assertBusinessApiValue(4.6, $successPayload['data']['reputation']['external'][0]['rating'], 'Google rating is invalid.');
assertBusinessApiValue('2026-09-16T00:30:00Z', $successPayload['data']['reputation']['external'][0]['fetchedAt'], 'External fetched time is invalid.');
assertBusinessApiValue(null, $successPayload['data']['reputation']['external'][1]['rating'], 'Naver rating must be null.');
assertBusinessApiValue('WEBSITE', $successPayload['data']['links'][0]['type'], 'Business link is invalid.');
assertBusinessApiValue(18, $successPayload['data']['stats']['workCaseCount'], 'Work Case count is invalid.');
assertBusinessApiValue(false, $successPayload['data']['isFavorite'], 'Anonymous favorite state is invalid.');

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
assertBusinessApiValue('VALIDATION_ERROR', $invalidIdPayload['error']['code'], 'Validation error code is invalid.');

$methodResponse = $application->handle(
    Request::create('/api/v1/businesses/123', 'POST'),
);

assertBusinessApiValue(405, $methodResponse->getStatusCode(), 'Invalid method must return HTTP 405.');
assertBusinessApiValue('GET', $methodResponse->headers->get('Allow'), 'Business Allow header is invalid.');

fwrite(STDOUT, "Business API tests passed.\n");
