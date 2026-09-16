<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (
    RouteCollection $routes,
    BusinessController $businessController,
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
