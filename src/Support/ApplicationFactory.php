<?php

declare(strict_types=1);

namespace App\Support;

use App\Controller\Api\ApiDocumentationController;
use App\Controller\Api\V1\BusinessController;
use App\Database\ConnectionFactory;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\BusinessRepository;
use App\Service\BusinessService;
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
        $businessRepository = new BusinessRepository($connection);
        $businessService = new BusinessService($businessRepository);
        $businessController = new BusinessController($businessService);
        $apiDocumentationController = new ApiDocumentationController(
            $rootPath . '/openapi/openapi.json',
            $rootPath . '/vendor/swagger-api/swagger-ui/dist',
        );
        $routes = self::loadRoutes(
            $rootPath,
            $businessController,
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
        BusinessController $businessController,
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
            $businessController,
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
