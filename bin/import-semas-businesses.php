<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Integration\Semas\SemasOpenApiClient;
use App\Repository\SemasBusinessImportRepository;
use App\Service\SemasBusinessClassifier;
use App\Service\SemasBusinessImportService;

$rootPath = dirname(__DIR__);
$command = $argv[1] ?? 'all';

if (!in_array($command, ['all', 'collect', 'classify', 'sync'], true)) {
    fwrite(STDERR, "Usage: php bin/import-semas-businesses.php [all|collect|classify|sync]\n");
    exit(1);
}

$lock = null;

try {
    require $rootPath . '/vendor/autoload.php';

    $databaseConfig = require $rootPath . '/config/database.php';
    $publicDataConfig = require $rootPath . '/config/public_data.php';

    if (!is_array($databaseConfig) || !is_array($publicDataConfig['semas'] ?? null)) {
        throw new RuntimeException('SEMAS importer configuration is invalid.');
    }

    /** @var array<string, mixed> $semasConfig */
    $semasConfig = $publicDataConfig['semas'];
    $industryCodes = $semasConfig['industry_codes'] ?? null;
    $keywords = $semasConfig['keywords'] ?? null;

    if (!is_array($industryCodes) || !is_array($keywords)) {
        throw new RuntimeException('SEMAS industry codes and keywords must be arrays.');
    }

    $lock = fopen($rootPath . '/storage/cache/semas-business-import.lock', 'c');

    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another SEMAS business import is already running.');
    }

    $connection = (new ConnectionFactory($databaseConfig))->connection();
    $transactionManager = new TransactionManager($connection);
    $repository = new SemasBusinessImportRepository($connection);
    $classifier = new SemasBusinessClassifier(array_values($keywords));
    $client = new SemasOpenApiClient(
        (string) ($semasConfig['endpoint'] ?? ''),
        (string) ($semasConfig['service_key'] ?? ''),
        (int) ($semasConfig['page_size'] ?? 1000),
        (int) ($semasConfig['timeout_seconds'] ?? 30),
    );
    $service = new SemasBusinessImportService(
        $client,
        $repository,
        $classifier,
        $transactionManager,
        array_values($industryCodes),
        (int) ($semasConfig['page_size'] ?? 1000),
        (int) ($semasConfig['request_interval_milliseconds'] ?? 100),
    );

    if (in_array($command, ['all', 'collect'], true)) {
        fwrite(STDOUT, "[1/3] SEMAS industry collection started.\n");
        $collection = $service->collect(
            static function (string $industryCode, int $page, int $totalPages, int $count): void {
                printf("  %s page %d/%d: %d rows saved\n", $industryCode, $page, $totalPages, $count);
            },
        );
        printf(
            "Collection complete: %d pages, %d rows saved, %d invalid rows rejected.\n",
            $collection['pages'],
            $collection['collected'],
            $collection['rejected'],
        );
    }

    if (in_array($command, ['all', 'classify'], true)) {
        fwrite(STDOUT, "[2/3] Keyword classification started.\n");
        $classification = $service->classify();
        printf(
            "Classification complete: %d checked, %d matched, %d unmatched.\n",
            $classification['classified'],
            $classification['matched'],
            $classification['unmatched'],
        );
    }

    if (in_array($command, ['all', 'sync'], true)) {
        fwrite(STDOUT, "[3/3] Matched business synchronization started.\n");
        $synchronization = $service->sync();
        printf(
            "Synchronization complete: %d businesses synchronized, %d failed.\n",
            $synchronization['synced'],
            $synchronization['failed'],
        );
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("SEMAS import failed: %s\n", $exception->getMessage()));
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
