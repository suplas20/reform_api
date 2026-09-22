<?php

declare(strict_types=1);

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\AdController;
use App\Controller\Api\V1\AppConfigController;
use App\Controller\Api\V1\AuthController;
use App\Controller\Api\V1\BusinessController;
use App\Controller\Api\V1\EventController;
use App\Controller\Api\V1\FavoriteController;
use App\Controller\Api\V1\HelpController;
use App\Controller\Api\V1\InquiryController;
use App\Controller\Api\V1\MeController;
use App\Controller\Api\V1\MediaController;
use App\Controller\Api\V1\ReferenceController;
use App\Controller\Api\V1\ReviewController;
use App\Controller\Api\V1\SearchController;
use App\Controller\Api\V1\WorkCaseController;
use App\Controller\Api\V1\UserActivityController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (
    RouteCollection $routes,
    ReferenceController $referenceController,
    BusinessController $businessController,
    EventController $eventController,
    SearchController $searchController,
    ApiDocumentationController $apiDocumentationController,
    ?WorkCaseController $workCaseController = null,
    ?ReviewController $reviewController = null,
    ?AuthController $authController = null,
    ?MeController $meController = null,
    ?FavoriteController $favoriteController = null,
    ?InquiryController $inquiryController = null,
    ?MediaController $mediaController = null,
    ?UserActivityController $userActivityController = null,
    ?HelpController $helpController = null,
    ?AdController $adController = null,
    ?AppConfigController $appConfigController = null,
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

    if ($workCaseController !== null) {
        $routes->add(
            'api.v1.work_cases.index',
            new Route(
                '/api/v1/work-cases',
                ['_controller' => [$workCaseController, 'index']],
                methods: ['GET'],
            ),
        );
        $routes->add(
            'api.v1.work_cases.show',
            new Route(
                '/api/v1/work-cases/{id}',
                ['_controller' => [$workCaseController, 'show']],
                ['id' => '\\d+'],
                methods: ['GET'],
            ),
        );
    }

    if ($reviewController !== null) {
        $routes->add(
            'api.v1.businesses.reviews.index',
            new Route(
                '/api/v1/businesses/{businessId}/reviews',
                ['_controller' => [$reviewController, 'index']],
                ['businessId' => '\\d+'],
                methods: ['GET'],
            ),
        );
        $routes->add(
            'api.v1.businesses.review_summary.show',
            new Route(
                '/api/v1/businesses/{businessId}/review-summary',
                ['_controller' => [$reviewController, 'summary']],
                ['businessId' => '\\d+'],
                methods: ['GET'],
            ),
        );
        $routes->add(
            'api.v1.businesses.reviews.create',
            new Route(
                '/api/v1/businesses/{businessId}/reviews',
                ['_controller' => [$reviewController, 'create']],
                ['businessId' => '\\d+'],
                methods: ['POST'],
            ),
        );
        $routes->add(
            'api.v1.me.reviews.index',
            new Route('/api/v1/me/reviews', ['_controller' => [$reviewController, 'mine']], methods: ['GET']),
        );
    }

    if ($authController !== null) {
        $routes->add('api.v1.auth.social_login', new Route('/api/v1/auth/social-login', ['_controller' => [$authController, 'socialLogin']], methods: ['POST']));
        $routes->add('api.v1.auth.refresh', new Route('/api/v1/auth/refresh', ['_controller' => [$authController, 'refresh']], methods: ['POST']));
        $routes->add('api.v1.auth.logout', new Route('/api/v1/auth/logout', ['_controller' => [$authController, 'logout']], methods: ['POST']));
    }

    if ($meController !== null) {
        $routes->add('api.v1.me.show', new Route('/api/v1/me', ['_controller' => [$meController, 'show']], methods: ['GET']));
        $routes->add('api.v1.me.update', new Route('/api/v1/me', ['_controller' => [$meController, 'update']], methods: ['PATCH']));
        $routes->add('api.v1.me.delete', new Route('/api/v1/me', ['_controller' => [$meController, 'delete']], methods: ['DELETE']));
        $routes->add('api.v1.me.activity_counts', new Route('/api/v1/me/activity-counts', ['_controller' => [$meController, 'activityCounts']], methods: ['GET']));
    }

    if ($favoriteController !== null) {
        $routes->add('api.v1.me.favorites.index', new Route('/api/v1/me/favorites', ['_controller' => [$favoriteController, 'index']], methods: ['GET']));
        $routes->add('api.v1.me.favorites.put', new Route('/api/v1/me/favorites/{businessId}', ['_controller' => [$favoriteController, 'put']], ['businessId' => '\\d+'], methods: ['PUT']));
        $routes->add('api.v1.me.favorites.delete', new Route('/api/v1/me/favorites/{businessId}', ['_controller' => [$favoriteController, 'delete']], ['businessId' => '\\d+'], methods: ['DELETE']));
    }

    if ($inquiryController !== null) {
        $routes->add('api.v1.inquiries.create', new Route('/api/v1/businesses/{businessId}/inquiries', ['_controller' => [$inquiryController, 'create']], ['businessId' => '\\d+'], methods: ['POST']));
        $routes->add('api.v1.me.inquiries.index', new Route('/api/v1/me/inquiries', ['_controller' => [$inquiryController, 'index']], methods: ['GET']));
        $routes->add('api.v1.me.inquiries.show', new Route('/api/v1/me/inquiries/{inquiryId}', ['_controller' => [$inquiryController, 'show']], ['inquiryId' => '\\d+'], methods: ['GET']));
    }

    if ($mediaController !== null) {
        $routes->add('api.v1.media.request', new Route('/api/v1/media/presigned-uploads', ['_controller' => [$mediaController, 'requestUploads']], methods: ['POST']));
        $routes->add('api.v1.media.upload', new Route('/api/v1/media/uploads/{mediaId}', ['_controller' => [$mediaController, 'upload']], ['mediaId' => 'media_[a-f0-9]+'], methods: ['PUT']));
        $routes->add('api.v1.media.complete', new Route('/api/v1/media/complete', ['_controller' => [$mediaController, 'complete']], methods: ['POST']));
    }

    if ($userActivityController !== null) {
        $routes->add('api.v1.me.recent.index', new Route('/api/v1/me/recent-businesses', ['_controller' => [$userActivityController, 'recent']], methods: ['GET']));
        $routes->add('api.v1.me.recent.put', new Route('/api/v1/me/recent-businesses/{businessId}', ['_controller' => [$userActivityController, 'touchRecent']], ['businessId' => '\\d+'], methods: ['PUT']));
        $routes->add('api.v1.me.recent.clear', new Route('/api/v1/me/recent-businesses', ['_controller' => [$userActivityController, 'clearRecent']], methods: ['DELETE']));
        $routes->add('api.v1.me.notifications.index', new Route('/api/v1/me/notifications', ['_controller' => [$userActivityController, 'notifications']], methods: ['GET']));
        $routes->add('api.v1.me.notifications.show', new Route('/api/v1/me/notifications/{notificationId}', ['_controller' => [$userActivityController, 'notification']], ['notificationId' => '\\d+'], methods: ['GET']));
        $routes->add('api.v1.me.notifications.read', new Route('/api/v1/me/notifications/{notificationId}/read', ['_controller' => [$userActivityController, 'markRead']], ['notificationId' => '\\d+'], methods: ['PATCH']));
        $routes->add('api.v1.me.notifications.read_all', new Route('/api/v1/me/notifications/read-all', ['_controller' => [$userActivityController, 'markAllRead']], methods: ['PATCH']));
        $routes->add('api.v1.me.settings.show', new Route('/api/v1/me/settings', ['_controller' => [$userActivityController, 'settings']], methods: ['GET']));
        $routes->add('api.v1.me.settings.update', new Route('/api/v1/me/settings', ['_controller' => [$userActivityController, 'updateSettings']], methods: ['PATCH']));
        $routes->add('api.v1.me.devices.put', new Route('/api/v1/me/devices/{deviceId}', ['_controller' => [$userActivityController, 'putDevice']], methods: ['PUT']));
        $routes->add('api.v1.me.devices.delete', new Route('/api/v1/me/devices/{deviceId}', ['_controller' => [$userActivityController, 'deleteDevice']], methods: ['DELETE']));
    }

    if ($helpController !== null) {
        $routes->add('api.v1.help.categories', new Route('/api/v1/help/categories', ['_controller' => [$helpController, 'categories']], methods: ['GET']));
        $routes->add('api.v1.help.faqs.index', new Route('/api/v1/help/faqs', ['_controller' => [$helpController, 'faqs']], methods: ['GET']));
        $routes->add('api.v1.help.faqs.show', new Route('/api/v1/help/faqs/{faqId}', ['_controller' => [$helpController, 'faq']], ['faqId' => '\\d+'], methods: ['GET']));
    }

    if ($adController !== null) {
        $routes->add('api.v1.ads.show', new Route('/api/v1/ads', ['_controller' => [$adController, 'show']], methods: ['GET']));
        $routes->add('api.v1.ads.impression', new Route('/api/v1/ads/{adId}/impressions', ['_controller' => [$adController, 'impression']], ['adId' => '\\d+'], methods: ['POST']));
        $routes->add('api.v1.ads.click', new Route('/api/v1/ads/{adId}/clicks', ['_controller' => [$adController, 'click']], ['adId' => '\\d+'], methods: ['POST']));
    }

    if ($appConfigController !== null) {
        $routes->add('api.v1.app_config.show', new Route('/api/v1/app-config', ['_controller' => [$appConfigController, 'show']], methods: ['GET']));
    }
};
