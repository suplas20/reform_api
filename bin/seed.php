<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;

$rootPath = dirname(__DIR__);
$target = $argv[1] ?? 'all';

if (!in_array($target, ['all', 'reference', 'demo'], true)) {
    fwrite(STDERR, "Usage: php bin/seed.php [all|reference|demo]\n");
    exit(1);
}

try {
    require $rootPath . '/vendor/autoload.php';

    $config = require $rootPath . '/config/database.php';

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $files = match ($target) {
        'reference' => [$rootPath . '/database/seeds/001_reference_data.sql'],
        'demo' => [$rootPath . '/database/seeds/002_demo_content.sql'],
        default => glob($rootPath . '/database/seeds/*.sql'),
    };

    if (!is_array($files) || $files === []) {
        throw new RuntimeException('No seed SQL files were found.');
    }

    sort($files, SORT_STRING);
    $connection = (new ConnectionFactory($config))->connection();

    foreach ($files as $path) {
        $sql = file_get_contents($path);

        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException(sprintf('Seed file is empty or unreadable: %s', basename($path)));
        }

        $connection->beginTransaction();

        try {
            $connection->exec($sql);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        printf("Seeded: %s\n", pathinfo($path, PATHINFO_FILENAME));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Seed failed: %s\n", $exception->getMessage()));
    exit(1);
}
