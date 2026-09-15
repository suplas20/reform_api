<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

$rootPath = dirname(__DIR__);
$command = $argv[1] ?? 'migrate';

if (!in_array($command, ['migrate', 'status'], true)) {
    fwrite(STDERR, "Usage: php bin/migrate.php [migrate|status]\n");
    exit(1);
}

try {
    require $rootPath . '/vendor/autoload.php';

    $config = require $rootPath . '/config/database.php';

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $connection = (new ConnectionFactory($config))->connection();
    $runner = new MigrationRunner(
        $connection,
        $rootPath . '/database/migrations',
    );

    if ($command === 'status') {
        $status = $runner->status();

        foreach ($status['applied'] as $version) {
            printf("[applied] %s\n", $version);
        }

        foreach ($status['pending'] as $version) {
            printf("[pending] %s\n", $version);
        }

        if ($status['applied'] === [] && $status['pending'] === []) {
            fwrite(STDOUT, "No migrations found.\n");
        }

        exit(0);
    }

    $applied = $runner->migrate();

    if ($applied === []) {
        fwrite(STDOUT, "No pending migrations.\n");
        exit(0);
    }

    foreach ($applied as $version) {
        printf("Applied migration: %s\n", $version);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Migration failed: %s\n", $exception->getMessage()));
    exit(1);
}
