<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Repository\RegionCenterRepository;
use App\Service\RegionCenterImportService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$command = $argv[1] ?? 'plan';
if (!in_array($command, ['plan', 'apply'], true)) {
    fwrite(STDERR, "Usage: php bin/import-region-centers.php [plan|apply]\n");
    exit(1);
}
$lock = null;
try {
    require $root . '/vendor/autoload.php';
    $lock = fopen($root . '/storage/cache/administrative-region-import.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another region maintenance command is running.');
    }
    $db = (new ConnectionFactory(require $root . '/config/database.php'))->connection();
    $config = require $root . '/config/region_centers.php';
    $service = new RegionCenterImportService(new RegionCenterRepository($db), new TransactionManager($db));
    echo json_encode($service->run($config['path'], $config['sha256'], $config['source'], $command === 'apply'),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof PDOException ? "Region center database operation failed.\n" : $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
