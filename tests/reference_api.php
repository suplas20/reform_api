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

final class FakeReferenceDataProvider implements ReferenceDataProvider
{
    /** @var array<string, list<mixed>> */
    public array $lastArguments = [];

    public function getCategories(?int $parentId, ?int $level): array
    {
        $this->lastArguments['categories'] = [$parentId, $level];

        return [[
            'id' => 1,
            'parentId' => null,
            'name' => '가방',
            'slug' => 'bag',
            'description' => null,
            'sortOrder' => 10,
        ]];
    }

    public function getServices(?int $categoryId, ?int $limit): array
    {
        $this->lastArguments['services'] = [$categoryId, $limit];

        return [[
            'id' => 10,
            'name' => '염색',
            'slug' => 'dye',
            'description' => '색상을 복원합니다.',
            'sortOrder' => 10,
        ]];
    }

    public function getRegions(?int $parentId, ?int $level, ?string $keyword): array
    {
        $this->lastArguments['regions'] = [$parentId, $level, $keyword];

        return [[
            'id' => 10,
            'parentId' => 1,
            'code' => '11680',
            'name' => '강남구',
            'slug' => 'gangnam',
            'level' => 2,
            'sortOrder' => 10,
        ]];
    }

    public function getBrands(?string $keyword, int $page, int $perPage): array
    {
        $this->lastArguments['brands'] = [$keyword, $page, $perPage];

        return [
            'items' => [[
                'id' => 100,
                'name' => 'Chanel',
                'slug' => 'chanel',
            ]],
            'total' => 21,
            'totalPages' => 2,
        ];
    }
}

function assertReferenceApi(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

$referenceData = new FakeReferenceDataProvider();
$referenceController = new ReferenceController($referenceData);
$businessController = new BusinessController(
    new BusinessService(
        new class implements BusinessReadRepository {
            public function countPublic(string $publishedAt, array $criteria = []): int
            {
                return 0;
            }

            public function findPublicPage(
                string $publishedAt,
                int $limit,
                int $offset,
                array $criteria = [],
            ): array {
                return [];
            }

            public function activeCategoryServiceCombinationExists(int $categoryId, int $serviceId): bool
            {
                return true;
            }

            public function findPublicSummaryRelations(array $businessIds): array
            {
                return ['locations' => [], 'categories' => [], 'services' => [], 'brands' => [], 'verifiedBusinessIds' => []];
            }

            public function findPublicByIds(array $businessIds, string $publishedAt): array
            {
                return [];
            }

            public function findPublicDetailById(int $id, string $publishedAt): ?array
            {
                return null;
            }

            public function findPublicDetailRelations(int $businessId): array
            {
                return [
                    'primaryLocation' => null,
                    'hours' => [],
                    'categories' => [],
                    'offerings' => [],
                    'brands' => [],
                    'links' => [],
                    'verificationTypes' => [],
                ];
            }

            public function findActiveExternalReputationByBusinessIds(array $businessIds): array
            {
                return [];
            }
        },
    ),
);
$documentationController = new ApiDocumentationController(
    dirname(__DIR__) . '/openapi/openapi.json',
    dirname(__DIR__) . '/vendor/swagger-api/swagger-ui/dist',
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
    $businessController,
    $eventController,
    $searchController,
    $documentationController,
);

$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(
    new UrlMatcher($routes, new RequestContext()),
    new ExceptionHandler($logger),
);

$categoriesResponse = $application->handle(
    Request::create('/api/v1/categories?parentId=1&level=2', 'GET'),
);
$categories = json_decode($categoriesResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertReferenceApi(200, $categoriesResponse->getStatusCode(), 'Categories status is invalid.');
assertReferenceApi([1, 2], $referenceData->lastArguments['categories'], 'Categories query is invalid.');
assertReferenceApi('가방', $categories['data'][0]['name'], 'Categories payload is invalid.');
assertReferenceApi(false, isset($categories['meta']), 'Categories must not be paginated.');

$servicesResponse = $application->handle(
    Request::create('/api/v1/services?categoryId=1&limit=4', 'GET'),
);
$services = json_decode($servicesResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertReferenceApi(200, $servicesResponse->getStatusCode(), 'Services status is invalid.');
assertReferenceApi([1, 4], $referenceData->lastArguments['services'], 'Services query is invalid.');
assertReferenceApi(10, $services['data'][0]['id'], 'Services payload is invalid.');

$regionsResponse = $application->handle(
    Request::create('/api/v1/regions?parentId=1&level=2&query=%EA%B0%95%EB%82%A8', 'GET'),
);
$regions = json_decode($regionsResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertReferenceApi(200, $regionsResponse->getStatusCode(), 'Regions status is invalid.');
assertReferenceApi([1, 2, '강남'], $referenceData->lastArguments['regions'], 'Regions query is invalid.');
assertReferenceApi('11680', $regions['data'][0]['code'], 'Regions payload is invalid.');

$brandsResponse = $application->handle(
    Request::create('/api/v1/brands?keyword=Chan&page=2&perPage=20', 'GET'),
);
$brands = json_decode($brandsResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertReferenceApi(200, $brandsResponse->getStatusCode(), 'Brands status is invalid.');
assertReferenceApi(['Chan', 2, 20], $referenceData->lastArguments['brands'], 'Brands query is invalid.');
assertReferenceApi('Chanel', $brands['data'][0]['name'], 'Brands payload is invalid.');
assertReferenceApi(21, $brands['meta']['total'], 'Brands total is invalid.');
assertReferenceApi(2, $brands['meta']['totalPages'], 'Brands totalPages is invalid.');

foreach ([
    '/api/v1/categories?parentId=0',
    '/api/v1/services?limit=0',
    '/api/v1/regions?level[]=1',
    '/api/v1/brands?page=0',
    '/api/v1/brands?keyword[]=bag',
] as $invalidPath) {
    $response = $application->handle(Request::create($invalidPath, 'GET'));
    $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    assertReferenceApi(422, $response->getStatusCode(), 'Invalid query must return HTTP 422.');
    assertReferenceApi(
        'VALIDATION_ERROR',
        $payload['error']['code'],
        'Invalid query error code is invalid.',
    );
}

echo "Reference API tests passed.\n";
