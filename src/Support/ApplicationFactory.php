<?php

declare(strict_types=1);

namespace App\Support;

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Controller\Api\V1\EventController;
use App\Controller\Api\V1\ReferenceController;
use App\Database\ConnectionFactory;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BrandRepository;
use App\Repository\BusinessRepository;
use App\Repository\CategoryRepository;
use App\Repository\EventLogRepository;
use App\Repository\RegionRepository;
use App\Repository\ServiceRepository;
use App\Service\BusinessService;
use App\Service\EventService;
use App\Service\ReferenceService;
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
        $apiDocumentationController = new ApiDocumentationController(
            $rootPath . '/openapi/openapi.json',
            $rootPath . '/vendor/swagger-api/swagger-ui/dist',
        );
        $routes = self::loadRoutes(
            $rootPath,
            $referenceController,
            $businessController,
            $eventController,
            $apiDocumentationController,
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
        ApiDocumentationController $apiDocumentationController,
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
            $apiDocumentationController,
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
