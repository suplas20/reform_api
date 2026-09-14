<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Application;
use App\Http\ExceptionHandler;
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
        $servicesConfig = self::loadConfig($rootPath . '/config/services.php');

        date_default_timezone_set((string) $appConfig['timezone']);
        ini_set('display_errors', '0');

        $logger = self::createLogger($servicesConfig);
        $routes = self::loadRoutes($rootPath);
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

    private static function loadRoutes(string $rootPath): RouteCollection
    {
        $routes = new RouteCollection();

        foreach (['api.php', 'web.php', 'admin.php'] as $routeFile) {
            $registrar = require $rootPath . '/routes/' . $routeFile;

            if (!is_callable($registrar)) {
                throw new RuntimeException(sprintf('Route file must return a callable: %s', $routeFile));
            }

            $registrar($routes);
        }

        return $routes;
    }
}
