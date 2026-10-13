<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Integration\AdministrativeRegions\OdcloudAdministrativeRegionClient;
use App\Repository\AdministrativeRegionImportRepository;
use App\Service\AdministrativeRegionImportService;
use App\Service\AdministrativeRegionRecordNormalizer;

// Local maintenance only. Never invoke from an HTTP request or a production shell dependency.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$rootPath = dirname(__DIR__);
$command = $argv[1] ?? 'collect';
if (!in_array($command, ['collect', 'status'], true)) {
    fwrite(STDERR, "Usage: php bin/import-administrative-regions.php [collect|status]\n");
    exit(1);
}
$lock = null;
try {
    require $rootPath . '/vendor/autoload.php';
    $config = require $rootPath . '/config/administrative_regions.php';
    if ($command === 'collect') {
        $lock = fopen($rootPath . '/storage/cache/administrative-region-import.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another administrative region collection is already running.');
        }
    }
    $databaseConfig = require $rootPath . '/config/database.php';
    $connection = (new ConnectionFactory($databaseConfig))->connection();
    $service = new AdministrativeRegionImportService(
        new OdcloudAdministrativeRegionClient($config['base_url'], $config['path'], $config['service_key'], $config['page_size'], $config['timeout_seconds']),
        new AdministrativeRegionImportRepository($connection),
        new TransactionManager($connection),
        new AdministrativeRegionRecordNormalizer(),
        $config['snapshot_key'], $config['dataset_version'], $config['path'],
        $config['page_size'], $config['request_interval_milliseconds'],
    );
    $result = $command === 'status' ? $service->status() : $service->collect(
        static function (int $page, int $pages, int $count): void {
            if ($page === 1 || $page % 10 === 0 || $page === $pages) {
                printf("Page %d/%d: %d source rows saved.\n", $page, $pages, $count);
            }
        },
    );
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    // Provider bodies and connection credentials must not appear in maintenance output.
    $message = $exception instanceof PDOException ? 'Database operation failed; check local database settings and migrations.' : $exception->getMessage();
    fwrite(STDERR, 'Administrative region import failed: ' . $message . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
