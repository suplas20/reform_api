<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;

$rootPath = dirname(__DIR__);

try {
    require $rootPath . '/vendor/autoload.php';

    $config = require $rootPath . '/config/database.php';

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $connection = (new ConnectionFactory($config))->connection();
    $statement = $connection->query(
        <<<'SQL'
            SELECT
                VERSION() AS version,
                @@character_set_database AS charset_name,
                @@collation_database AS collation_name
            SQL,
    );

    if ($statement === false) {
        throw new RuntimeException('Unable to inspect the database server.');
    }

    $database = $statement->fetch();

    if (!is_array($database)) {
        throw new RuntimeException('Unable to read the database server information.');
    }

    printf(
        "Database connection successful.\nMariaDB: %s\nCharset: %s\nCollation: %s\n",
        $database['version'],
        $database['charset_name'],
        $database['collation_name'],
    );
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database connection failed: %s\n", $exception->getMessage()));
    exit(1);
}
