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
use App\Repository\EventLogStore;
use App\Service\BusinessService;
use App\Service\EventService;
use App\Service\ReferenceDataProvider;
use App\Service\SearchDataProvider;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertEventApi(mixed $expected, mixed $actual, string $message): void
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

$eventStore = new class implements EventLogStore {
    /** @var list<array<string, mixed>> */
    public array $events = [];

    public function create(
        ?int $userId,
        ?string $sessionKey,
        string $eventName,
        ?string $entityType,
        ?int $entityId,
        ?string $metadata,
        string $createdAt,
    ): void {
        $this->events[] = [
            'userId' => $userId,
            'sessionKey' => $sessionKey,
            'eventName' => $eventName,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'metadata' => $metadata,
            'createdAt' => $createdAt,
        ];
    }
};
$eventController = new EventController(new EventService($eventStore));
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
$documentationController = new ApiDocumentationController(
    dirname(__DIR__) . '/openapi/openapi.json',
    dirname(__DIR__) . '/vendor/swagger-api/swagger-ui/dist',
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

$validPayload = [
    'sessionKey' => 'app-session-uuid',
    'eventName' => 'BUSINESS_VIEW',
    'entityType' => 'BUSINESS',
    'entityId' => 123,
    'metadata' => [
        'source' => 'search_result',
        'position' => 3,
    ],
];
$validResponse = $application->handle(
    Request::create(
        '/api/v1/events',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode($validPayload, JSON_THROW_ON_ERROR),
    ),
);

assertEventApi(204, $validResponse->getStatusCode(), 'Event create status is invalid.');
assertEventApi('', $validResponse->getContent(), 'Event create must not return a body.');
assertEventApi(1, count($eventStore->events), 'Event must be stored once.');
assertEventApi(null, $eventStore->events[0]['userId'], 'Anonymous Event user ID must be null.');
assertEventApi('app-session-uuid', $eventStore->events[0]['sessionKey'], 'Session key is invalid.');
assertEventApi('BUSINESS_VIEW', $eventStore->events[0]['eventName'], 'Event name is invalid.');
assertEventApi('BUSINESS', $eventStore->events[0]['entityType'], 'Entity type is invalid.');
assertEventApi(123, $eventStore->events[0]['entityId'], 'Entity ID is invalid.');
assertEventApi(
    '{"source":"search_result","position":3}',
    $eventStore->events[0]['metadata'],
    'Metadata JSON is invalid.',
);

if (
    !is_string($eventStore->events[0]['createdAt'])
    || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $eventStore->events[0]['createdAt']) !== 1
) {
    throw new RuntimeException('Event createdAt must be a UTC database datetime.');
}

$invalidRequests = [
    Request::create(
        '/api/v1/events',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: '{"eventName":',
    ),
    Request::create('/api/v1/events', 'POST', content: '{}'),
];

foreach ($invalidRequests as $invalidRequest) {
    $response = $application->handle($invalidRequest);
    $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    assertEventApi(400, $response->getStatusCode(), 'Malformed Event request must return HTTP 400.');
    assertEventApi('BAD_REQUEST', $payload['error']['code'], 'Malformed Event error code is invalid.');
}

foreach ([
    ['eventName' => 'UNKNOWN_EVENT'],
    ['eventName' => 'BUSINESS_VIEW', 'entityId' => '123'],
    ['eventName' => 'BUSINESS_VIEW', 'entityType' => 'business'],
    ['eventName' => 'BUSINESS_VIEW', 'metadata' => ['access_token' => 'secret']],
    ['eventName' => 'BUSINESS_VIEW', 'metadata' => ['nested' => ['apiKey' => 'secret']]],
    ['eventName' => 'BUSINESS_VIEW', 'metadata' => ['first', 'second']],
    ['eventName' => 'BUSINESS_VIEW', 'unexpected' => true],
] as $invalidPayload) {
    $response = $application->handle(
        Request::create(
            '/api/v1/events',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($invalidPayload, JSON_THROW_ON_ERROR),
        ),
    );
    $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    assertEventApi(422, $response->getStatusCode(), 'Invalid Event must return HTTP 422.');
    assertEventApi('VALIDATION_ERROR', $payload['error']['code'], 'Event validation code is invalid.');
}

assertEventApi(1, count($eventStore->events), 'Invalid Events must not be stored.');

echo "Event API tests passed.\n";
