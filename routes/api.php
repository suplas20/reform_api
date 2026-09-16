<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Controller\Api\V1\EventController;
use App\Controller\Api\V1\ReferenceController;
use App\Controller\Api\V1\SearchController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (
    RouteCollection $routes,
    ReferenceController $referenceController,
    BusinessController $businessController,
    EventController $eventController,
    SearchController $searchController,
    ApiDocumentationController $apiDocumentationController,
): void {
    $routes->add(
        'api.documentation.index',
        new Route(
            '/api/docs',
            ['_controller' => [$apiDocumentationController, 'index']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.documentation.specification',
        new Route(
            '/api/docs/openapi.json',
            ['_controller' => [$apiDocumentationController, 'specification']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.documentation.asset',
        new Route(
            '/api/docs/assets/{asset}',
            ['_controller' => [$apiDocumentationController, 'asset']],
            [
                'asset' => 'swagger-ui\.css|swagger-ui-bundle\.js|swagger-ui-standalone-preset\.js|initializer\.js',
            ],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.categories.index',
        new Route(
            '/api/v1/categories',
            ['_controller' => [$referenceController, 'categories']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.services.index',
        new Route(
            '/api/v1/services',
            ['_controller' => [$referenceController, 'services']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.regions.index',
        new Route(
            '/api/v1/regions',
            ['_controller' => [$referenceController, 'regions']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.brands.index',
        new Route(
            '/api/v1/brands',
            ['_controller' => [$referenceController, 'brands']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.search.suggestions',
        new Route(
            '/api/v1/search/suggestions',
            ['_controller' => [$searchController, 'suggestions']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.events.create',
        new Route(
            '/api/v1/events',
            ['_controller' => [$eventController, 'create']],
            methods: ['POST'],
        ),
    );
    $routes->add(
        'api.v1.businesses.index',
        new Route(
            '/api/v1/businesses',
            ['_controller' => [$businessController, 'index']],
            methods: ['GET'],
        ),
    );
    $routes->add(
        'api.v1.businesses.show',
        new Route(
            '/api/v1/businesses/{id}',
            ['_controller' => [$businessController, 'show']],
            ['id' => '\d+'],
            methods: ['GET'],
        ),
    );
};
