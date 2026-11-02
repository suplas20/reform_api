<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Repository\AdministrativeRegionSyncRepository;
use App\Service\AdministrativeRegionSyncPlanner;
use App\Service\AdministrativeRegionSyncService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$rootPath = dirname(__DIR__);
$command = $argv[1] ?? 'plan';
if (!in_array($command, ['plan', 'apply'], true)) {
    fwrite(STDERR, "Usage: php bin/sync-administrative-regions.php [plan|apply]\n");
    exit(1);
}
$lock = null;
try {
    require $rootPath . '/vendor/autoload.php';
    $lock = fopen($rootPath . '/storage/cache/administrative-region-import.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another administrative region maintenance command is running.');
    }
    $config = require $rootPath . '/config/administrative_regions.php';
    $connection = (new ConnectionFactory(require $rootPath . '/config/database.php'))->connection();
    $service = new AdministrativeRegionSyncService(
        new AdministrativeRegionSyncRepository($connection), new TransactionManager($connection),
        new AdministrativeRegionSyncPlanner(), $config['snapshot_key'],
        require $rootPath . '/config/administrative_region_sync.php',
    );
    echo json_encode($service->run($command === 'apply'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    $message = $exception instanceof PDOException ? 'Database operation failed; no partial synchronization was committed.' : $exception->getMessage();
    fwrite(STDERR, 'Administrative region synchronization failed: ' . $message . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
