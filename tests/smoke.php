<?php

declare(strict_types=1);

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Http\Middleware\CorsMiddleware;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
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

$routes = new RouteCollection();
$routes->add(
    'smoke.success',
    new Route(
        '/api/v1/smoke',
        [
            '_controller' => static fn (Request $request) => ApiResponse::success([
                'path' => $request->getPathInfo(),
            ]),
        ],
        methods: ['GET'],
    ),
);
$routes->add(
    'smoke.validation',
    new Route(
        '/api/v1/smoke/validation',
        [
            '_controller' => static function (Request $request): never {
                throw new ValidationException();
            },
        ],
        methods: ['POST'],
    ),
);
$routes->add(
    'smoke.internal-error',
    new Route(
        '/api/v1/smoke/internal-error',
        [
            '_controller' => static function (Request $request): never {
                throw new RuntimeException('Sensitive internal error.');
            },
        ],
        methods: ['GET'],
    ),
);

$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(
    new UrlMatcher($routes, new RequestContext()),
    new ExceptionHandler($logger),
    new CorsMiddleware(['http://localhost:8081']),
);

$successResponse = $application->handle(Request::create(
    '/api/v1/smoke',
    'GET',
    server: ['HTTP_ORIGIN' => 'http://localhost:8081'],
));
$successPayload = json_decode($successResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertSameValue(200, $successResponse->getStatusCode(), 'The success route must return HTTP 200.');
assertSameValue(true, $successPayload['success'], 'The success response contract is invalid.');
assertSameValue('/api/v1/smoke', $successPayload['data']['path'], 'The request path was not passed.');
assertSameValue(
    'http://localhost:8081',
    $successResponse->headers->get('Access-Control-Allow-Origin'),
    'Allowed API origins must receive the CORS response header.',
);

$preflightResponse = $application->handle(Request::create(
    '/api/v1/smoke',
    'OPTIONS',
    server: [
        'HTTP_ORIGIN' => 'http://localhost:8081',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
    ],
));

assertSameValue(204, $preflightResponse->getStatusCode(), 'Valid CORS preflight requests must return HTTP 204.');
assertSameValue(
    'http://localhost:8081',
    $preflightResponse->headers->get('Access-Control-Allow-Origin'),
    'Valid preflight responses must echo the allowed Origin.',
);

$disallowedOriginResponse = $application->handle(Request::create(
    '/api/v1/smoke',
    'GET',
    server: ['HTTP_ORIGIN' => 'https://untrusted.example'],
));
assertSameValue(
    null,
    $disallowedOriginResponse->headers->get('Access-Control-Allow-Origin'),
    'Disallowed origins must not receive CORS permission.',
);

$listResponse = ApiResponse::list([], page: 1, perPage: 20, total: 0, totalPages: 0);
$listPayload = json_decode($listResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertSameValue(1, $listPayload['meta']['page'], 'The list page metadata is invalid.');
assertSameValue(20, $listPayload['meta']['perPage'], 'The list perPage metadata is invalid.');
assertSameValue(0, $listPayload['meta']['total'], 'The list total metadata is invalid.');
assertSameValue(0, $listPayload['meta']['totalPages'], 'The list totalPages metadata is invalid.');

$validationResponse = $application->handle(
    Request::create('/api/v1/smoke/validation', 'POST'),
);
$validationPayload = json_decode($validationResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertSameValue(422, $validationResponse->getStatusCode(), 'Validation errors must return HTTP 422.');
assertSameValue('VALIDATION_ERROR', $validationPayload['error']['code'], 'The error contract is invalid.');

$notFoundResponse = $application->handle(Request::create('/api/v1/missing', 'GET'));
$notFoundPayload = json_decode($notFoundResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

assertSameValue(404, $notFoundResponse->getStatusCode(), 'Unknown API routes must return HTTP 404.');
assertSameValue(false, $notFoundPayload['success'], 'The not-found response contract is invalid.');
assertSameValue('NOT_FOUND', $notFoundPayload['error']['code'], 'The not-found error code is invalid.');

$methodResponse = $application->handle(Request::create('/api/v1/smoke', 'POST'));

assertSameValue(405, $methodResponse->getStatusCode(), 'Invalid methods must return HTTP 405.');
assertSameValue('GET', $methodResponse->headers->get('Allow'), 'The Allow header is invalid.');

$internalErrorResponse = $application->handle(
    Request::create('/api/v1/smoke/internal-error', 'GET'),
);
$internalErrorPayload = json_decode(
    $internalErrorResponse->getContent(),
    true,
    flags: JSON_THROW_ON_ERROR,
);

assertSameValue(500, $internalErrorResponse->getStatusCode(), 'Internal errors must return HTTP 500.');
assertSameValue('INTERNAL_ERROR', $internalErrorPayload['error']['code'], 'Internal error code is invalid.');
assertSameValue(
    false,
    str_contains((string) $internalErrorResponse->getContent(), 'Sensitive internal error.'),
    'Internal error details must not be exposed.',
);
assertSameValue(
    true,
    in_array('mysql', PDO::getAvailableDrivers(), true),
    'The pdo_mysql driver is required for MariaDB.',
);

fwrite(STDOUT, "Foundation smoke tests passed.\n");
