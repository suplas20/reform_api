<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\UnavailableSocialIdentityVerifier;
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
use App\Controller\Api\V1\UserActivityController;
use App\Controller\Api\V1\WorkCaseController;
use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BrandRepository;
use App\Repository\AdRepository;
use App\Repository\AuthRepository;
use App\Repository\BusinessRepository;
use App\Repository\CategoryRepository;
use App\Repository\EventLogRepository;
use App\Repository\FavoriteRepository;
use App\Repository\HelpRepository;
use App\Repository\InquiryRepository;
use App\Repository\MediaRepository;
use App\Repository\RegionRepository;
use App\Repository\ReviewRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserActivityRepository;
use App\Repository\UserRepository;
use App\Repository\WorkCaseRepository;
use App\Service\BusinessService;
use App\Service\AdService;
use App\Service\AppConfigService;
use App\Service\AuthService;
use App\Service\EventService;
use App\Service\FavoriteService;
use App\Service\HelpService;
use App\Service\InquiryService;
use App\Service\MediaService;
use App\Service\ReferenceService;
use App\Service\ReviewService;
use App\Service\SearchService;
use App\Service\UserActivityService;
use App\Service\UserService;
use App\Service\WorkCaseService;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class ApplicationFactory
{
    public static function create(string $rootPath): Application
    {
        $appConfig = self::loadConfig($rootPath . '/config/app.php');
        $databaseConfig = self::loadConfig($rootPath . '/config/database.php');
        $servicesConfig = self::loadConfig($rootPath . '/config/services.php');

        date_default_timezone_set((string) $appConfig['timezone']);
        ini_set('display_errors', '0');

        $logger = self::createLogger($servicesConfig);
        $connection = (new ConnectionFactory($databaseConfig))->connection();
        $transactionManager = new TransactionManager($connection);
        $categoryRepository = new CategoryRepository($connection);
        $serviceRepository = new ServiceRepository($connection);
        $regionRepository = new RegionRepository($connection);
        $brandRepository = new BrandRepository($connection);
        $referenceService = new ReferenceService(
            $categoryRepository,
            $serviceRepository,
            $regionRepository,
            $brandRepository,
        );
        $referenceController = new ReferenceController($referenceService);
        $eventLogRepository = new EventLogRepository($connection);
        $eventService = new EventService($eventLogRepository);
        $eventController = new EventController($eventService);
        $businessRepository = new BusinessRepository($connection);
        $businessService = new BusinessService($businessRepository);
        $businessController = new BusinessController($businessService);
        $searchService = new SearchService(
            $businessRepository,
            $serviceRepository,
            $brandRepository,
        );
        $searchController = new SearchController($searchService);
        $workCaseController = new WorkCaseController(
            new WorkCaseService(new WorkCaseRepository($connection)),
        );
        $reviewRepository = new ReviewRepository($connection);
        $authService = new AuthService(
            new AuthRepository($connection),
            new UnavailableSocialIdentityVerifier(),
            $transactionManager,
            (int) $servicesConfig['auth']['access_ttl_seconds'],
            (int) $servicesConfig['auth']['refresh_ttl_seconds'],
        );
        $reviewController = new ReviewController(
            new ReviewService($reviewRepository, $reviewRepository, $transactionManager),
            $authService,
        );
        $authController = new AuthController($authService);
        $meController = new MeController($authService, new UserService(new UserRepository($connection)));
        $favoriteController = new FavoriteController(
            $authService,
            new FavoriteService(
                new FavoriteRepository($connection),
                $businessRepository,
                $businessService,
                $transactionManager,
            ),
        );
        $inquiryController = new InquiryController(
            $authService,
            new InquiryService(new InquiryRepository($connection), $businessRepository, $transactionManager),
        );
        $mediaController = new MediaController(
            $authService,
            new MediaService(
                new MediaRepository($connection),
                (string) $servicesConfig['storage']['uploads_path'],
                (string) $servicesConfig['storage']['public_base_url'],
                (int) $servicesConfig['storage']['maximum_image_bytes'],
            ),
        );
        $userActivityController = new UserActivityController(
            $authService,
            new UserActivityService(new UserActivityRepository($connection), $businessRepository, $businessService),
        );
        $helpController = new HelpController(new HelpService(new HelpRepository($connection)));
        $adController = new AdController(
            $authService,
            new AdService(new AdRepository($connection), (string) $servicesConfig['ads']['tracking_secret']),
        );
        $appConfigController = new AppConfigController(new AppConfigService($servicesConfig['mobile_app']));
        $apiDocumentationController = new ApiDocumentationController(
            $rootPath . '/openapi/openapi.json',
            $rootPath . '/vendor/swagger-api/swagger-ui/dist',
        );
        $routes = self::loadRoutes(
            $rootPath,
            $referenceController,
            $businessController,
            $eventController,
            $searchController,
            $apiDocumentationController,
            $workCaseController,
            $reviewController,
            $authController,
            $meController,
            $favoriteController,
            $inquiryController,
            $mediaController,
            $userActivityController,
            $helpController,
            $adController,
            $appConfigController,
        );
        $matcher = new UrlMatcher($routes, new RequestContext());

        return new Application($matcher, new ExceptionHandler($logger));
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(string $path): array
    {
        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException(sprintf('Configuration file must return an array: %s', $path));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $servicesConfig
     */
    private static function createLogger(array $servicesConfig): Logger
    {
        /** @var array{path: string, level: string} $logging */
        $logging = $servicesConfig['logging'];
        $directory = dirname($logging['path']);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the application log directory.');
        }

        $logger = new Logger('app');
        $logger->pushHandler(
            new StreamHandler($logging['path'], Level::fromName($logging['level'])),
        );

        return $logger;
    }

    private static function loadRoutes(
        string $rootPath,
        ReferenceController $referenceController,
        BusinessController $businessController,
        EventController $eventController,
        SearchController $searchController,
        ApiDocumentationController $apiDocumentationController,
        WorkCaseController $workCaseController,
        ReviewController $reviewController,
        AuthController $authController,
        MeController $meController,
        FavoriteController $favoriteController,
        InquiryController $inquiryController,
        MediaController $mediaController,
        UserActivityController $userActivityController,
        HelpController $helpController,
        AdController $adController,
        AppConfigController $appConfigController,
    ): RouteCollection
    {
        $routes = new RouteCollection();

        $apiRegistrar = require $rootPath . '/routes/api.php';

        if (!is_callable($apiRegistrar)) {
            throw new RuntimeException('Route file must return a callable: api.php');
        }

        $apiRegistrar(
            $routes,
            $referenceController,
            $businessController,
            $eventController,
            $searchController,
            $apiDocumentationController,
            $workCaseController,
            $reviewController,
            $authController,
            $meController,
            $favoriteController,
            $inquiryController,
            $mediaController,
            $userActivityController,
            $helpController,
            $adController,
            $appConfigController,
        );

        foreach (['web.php', 'admin.php'] as $routeFile) {
            $registrar = require $rootPath . '/routes/' . $routeFile;

            if (!is_callable($registrar)) {
                throw new RuntimeException(sprintf('Route file must return a callable: %s', $routeFile));
            }

            $registrar($routes);
        }

        return $routes;
    }
}
