<?php

declare(strict_types=1);

use App\Controller\Api\V1\SearchController;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BrandSuggestionRepository;
use App\Repository\BusinessSuggestionRepository;
use App\Repository\ServiceSuggestionRepository;
use App\Repository\SqlLikePattern;
use App\Service\SearchService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSearchApi(mixed $expected, mixed $actual, string $message): void
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

$businessRepository = new class implements BusinessSuggestionRepository {
    /** @var list<mixed> */
    public array $arguments = [];

    public function findPublicSuggestions(
        string $keyword,
        string $publishedAt,
        int $limit,
    ): array {
        $this->arguments = [$keyword, $limit];

        if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $publishedAt) !== 1) {
            throw new RuntimeException('Business suggestions must use a UTC publication time.');
        }

        return [['id' => '123', 'name' => 'ABC 가죽공방']];
    }
};
$serviceRepository = new class implements ServiceSuggestionRepository {
    /** @var list<mixed> */
    public array $arguments = [];

    public function findActiveSuggestions(string $keyword, int $limit): array
    {
        $this->arguments = [$keyword, $limit];

        return [['id' => '11', 'name' => '가죽 복원']];
    }
};
$brandRepository = new class implements BrandSuggestionRepository {
    /** @var list<mixed> */
    public array $arguments = [];

    public function findActiveSuggestions(string $keyword, int $limit): array
    {
        $this->arguments = [$keyword, $limit];

        return [];
    }
};
$controller = new SearchController(
    new SearchService($businessRepository, $serviceRepository, $brandRepository),
);
$routes = new RouteCollection();
$routes->add(
    'api.v1.search.suggestions',
    new Route(
        '/api/v1/search/suggestions',
        ['_controller' => [$controller, 'suggestions']],
        methods: ['GET'],
    ),
);
$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(
    new UrlMatcher($routes, new RequestContext()),
    new ExceptionHandler($logger),
);

$response = $application->handle(
    Request::create('/api/v1/search/suggestions?keyword=%EA%B0%80%EC%A3%BD', 'GET'),
);
$payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertSearchApi(200, $response->getStatusCode(), 'Search suggestions status is invalid.');
assertSearchApi(
    ['가죽', 10],
    $businessRepository->arguments,
    'Business suggestion arguments are invalid.',
);
assertSearchApi(
    ['가죽', 10],
    $serviceRepository->arguments,
    'Service suggestion arguments are invalid.',
);
assertSearchApi(
    ['가죽', 10],
    $brandRepository->arguments,
    'Brand suggestion arguments are invalid.',
);
assertSearchApi(123, $payload['data']['businesses'][0]['id'], 'Business suggestion ID is invalid.');
assertSearchApi('가죽 복원', $payload['data']['services'][0]['name'], 'Service suggestion is invalid.');
assertSearchApi([], $payload['data']['brands'], 'Brand suggestions must be an empty list.');

$limitedResponse = $application->handle(
    Request::create('/api/v1/search/suggestions?keyword=%EB%B3%B5%EC%9B%90&limit=3', 'GET'),
);
assertSearchApi(200, $limitedResponse->getStatusCode(), 'Limited suggestions status is invalid.');
assertSearchApi(['복원', 3], $businessRepository->arguments, 'Suggestion limit is invalid.');

foreach ([
    '/api/v1/search/suggestions',
    '/api/v1/search/suggestions?keyword=',
    '/api/v1/search/suggestions?keyword[]=%EA%B0%80%EC%A3%BD',
    '/api/v1/search/suggestions?keyword=%EA%B0%80%EC%A3%BD&limit=0',
    '/api/v1/search/suggestions?keyword=%EA%B0%80%EC%A3%BD&limit=101',
    '/api/v1/search/suggestions?keyword=%EA%B0%80%EC%A3%BD&limit[]=10',
] as $invalidPath) {
    $invalidResponse = $application->handle(Request::create($invalidPath, 'GET'));
    $invalidPayload = json_decode(
        $invalidResponse->getContent(),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    assertSearchApi(422, $invalidResponse->getStatusCode(), 'Invalid suggestion query must return 422.');
    assertSearchApi(
        'VALIDATION_ERROR',
        $invalidPayload['error']['code'],
        'Suggestion validation code is invalid.',
    );
}

assertSearchApi(
    '%a!%b!_c!!%',
    SqlLikePattern::contains('a%b_c!'),
    'LIKE wildcard escaping is invalid.',
);

echo "Search API tests passed.\n";
