<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Controller\Api\V1\EventController;
use App\Controller\Api\V1\ReferenceController;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BusinessReadRepository;
use App\Service\BusinessService;
use App\Service\EventRecorder;
use App\Service\ReferenceDataProvider;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertApiDocumentation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$controller = new ApiDocumentationController(
    dirname(__DIR__) . '/openapi/openapi.json',
    dirname(__DIR__) . '/vendor/swagger-api/swagger-ui/dist',
);
$businessController = new BusinessController(
    new BusinessService(
        new class implements BusinessReadRepository {
            public function countPublic(string $publishedAt): int
            {
                return 0;
            }

            public function findPublicPage(
                string $publishedAt,
                int $limit,
                int $offset,
            ): array {
                return [];
            }

            public function findPublicDetailById(int $id, string $publishedAt): ?array
            {
                return null;
            }

            public function findActiveExternalReputationByBusinessIds(array $businessIds): array
            {
                return [];
            }
        },
    ),
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

        public function getRegions(?int $parentId, ?int $level): array
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
$routes = new RouteCollection();
$registerRoutes = require dirname(__DIR__) . '/routes/api.php';
$registerRoutes(
    $routes,
    $referenceController,
    $businessController,
    $eventController,
    $controller,
);

$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(
    new UrlMatcher($routes, new RequestContext()),
    new ExceptionHandler($logger),
);

$indexResponse = $application->handle(Request::create('/api/docs', 'GET'));

assertApiDocumentation(
    $indexResponse->getStatusCode() === 200,
    'Swagger UI must return HTTP 200.',
);
assertApiDocumentation(
    str_contains((string) $indexResponse->getContent(), 'id="swagger-ui"'),
    'Swagger UI root element is missing.',
);
assertApiDocumentation(
    $indexResponse->headers->get('X-Frame-Options') === 'DENY',
    'Swagger UI security headers are missing.',
);

$specificationResponse = $controller->specification(
    Request::create('/api/docs/openapi.json', 'GET'),
);
$specification = json_decode(
    (string) $specificationResponse->getContent(),
    true,
    flags: JSON_THROW_ON_ERROR,
);

assertApiDocumentation(
    $specificationResponse->getStatusCode() === 200,
    'OpenAPI specification must return HTTP 200.',
);
assertApiDocumentation(
    $specification['openapi'] === '3.1.0',
    'OpenAPI specification version is invalid.',
);
assertApiDocumentation(
    isset($specification['paths']['/api/v1/businesses/{id}']['get']),
    'Business detail operation is missing from OpenAPI.',
);
assertApiDocumentation(
    isset($specification['paths']['/api/v1/businesses']['get']),
    'Business list operation is missing from OpenAPI.',
);
foreach ([
    '/api/v1/categories',
    '/api/v1/services',
    '/api/v1/regions',
    '/api/v1/brands',
] as $referencePath) {
    assertApiDocumentation(
        isset($specification['paths'][$referencePath]['get']),
        sprintf('Reference operation is missing from OpenAPI: %s', $referencePath),
    );
}
assertApiDocumentation(
    isset($specification['paths']['/api/v1/events']['post']),
    'Event create operation is missing from OpenAPI.',
);

$assetRequest = Request::create('/api/docs/assets/swagger-ui.css', 'GET');
$assetRequest->attributes->set('asset', 'swagger-ui.css');
$assetResponse = $controller->asset($assetRequest);

assertApiDocumentation(
    $assetResponse->getStatusCode() === 200,
    'Swagger UI stylesheet must return HTTP 200.',
);
assertApiDocumentation(
    str_contains((string) $assetResponse->getContent(), '.swagger-ui'),
    'Swagger UI stylesheet content is invalid.',
);

$initializerRequest = Request::create('/api/docs/assets/initializer.js', 'GET');
$initializerRequest->attributes->set('asset', 'initializer.js');
$initializerResponse = $controller->asset($initializerRequest);

assertApiDocumentation(
    str_contains((string) $initializerResponse->getContent(), '/api/docs/openapi.json'),
    'Swagger UI initializer does not reference the OpenAPI specification.',
);

fwrite(STDOUT, "API documentation tests passed.\n");
