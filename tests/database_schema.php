<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param list<string> $expected
 * @param list<string> $actual
 */
function assertSameList(array $expected, array $actual, string $message): void
{
    sort($expected, SORT_STRING);
    sort($actual, SORT_STRING);

    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            implode(', ', $expected),
            implode(', ', $actual),
        ));
    }
}

/**
 * @param array<string, string> $expected
 * @param array<string, string> $actual
 */
function assertSameMap(array $expected, array $actual, string $message): void
{
    ksort($expected, SORT_STRING);
    ksort($actual, SORT_STRING);

    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
        ));
    }
}

$expectedTables = [
    'admins',
    'auth_sessions',
    'brands',
    'business_brands',
    'business_categories',
    'business_claims',
    'business_hours',
    'business_images',
    'business_links',
    'business_locations',
    'business_offerings',
    'business_sources',
    'business_verifications',
    'businesses',
    'categories',
    'category_services',
    'event_logs',
    'guides',
    'regions',
    'schema_migrations',
    'search_logs',
    'services',
    'user_favorites',
    'user_social_accounts',
    'users',
    'work_case_images',
    'work_case_services',
    'work_cases',
];

$expectedDeleteRules = [
    'fk_auth_sessions_user' => 'CASCADE',
    'fk_business_brands_brand' => 'RESTRICT',
    'fk_business_brands_business' => 'CASCADE',
    'fk_business_categories_business' => 'CASCADE',
    'fk_business_categories_category' => 'RESTRICT',
    'fk_business_claims_admin' => 'SET NULL',
    'fk_business_claims_business' => 'CASCADE',
    'fk_business_claims_user' => 'CASCADE',
    'fk_business_hours_business' => 'CASCADE',
    'fk_business_images_business' => 'CASCADE',
    'fk_business_links_business' => 'CASCADE',
    'fk_business_locations_business' => 'CASCADE',
    'fk_business_locations_region' => 'RESTRICT',
    'fk_business_offerings_business' => 'CASCADE',
    'fk_business_offerings_category' => 'RESTRICT',
    'fk_business_offerings_service' => 'RESTRICT',
    'fk_business_sources_business' => 'CASCADE',
    'fk_business_verifications_admin' => 'SET NULL',
    'fk_business_verifications_business' => 'CASCADE',
    'fk_businesses_claimed_user' => 'SET NULL',
    'fk_categories_parent' => 'RESTRICT',
    'fk_category_services_category' => 'RESTRICT',
    'fk_category_services_service' => 'RESTRICT',
    'fk_regions_parent' => 'RESTRICT',
    'fk_social_accounts_user' => 'CASCADE',
    'fk_user_favorites_business' => 'CASCADE',
    'fk_user_favorites_user' => 'CASCADE',
    'fk_work_case_images_work_case' => 'CASCADE',
    'fk_work_case_services_service' => 'RESTRICT',
    'fk_work_case_services_work_case' => 'CASCADE',
    'fk_work_cases_brand' => 'RESTRICT',
    'fk_work_cases_business' => 'CASCADE',
    'fk_work_cases_category' => 'RESTRICT',
];

try {
    $config = require dirname(__DIR__) . '/config/database.php';

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $connection = (new ConnectionFactory($config))->connection();

    $tablesStatement = $connection->query(
        <<<'SQL'
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
            SQL,
    );

    if ($tablesStatement === false) {
        throw new RuntimeException('Unable to inspect database tables.');
    }

    $actualTables = $tablesStatement->fetchAll(PDO::FETCH_COLUMN);
    assertSameList($expectedTables, $actualTables, 'The database table set is invalid.');

    $storageStatement = $connection->query(
        <<<'SQL'
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION <> 'utf8mb4_unicode_ci')
            SQL,
    );

    if ($storageStatement === false) {
        throw new RuntimeException('Unable to inspect table storage settings.');
    }

    assertSameList(
        [],
        $storageStatement->fetchAll(PDO::FETCH_COLUMN),
        'Every table must use InnoDB and utf8mb4_unicode_ci.',
    );

    $softDeleteStatement = $connection->query(
        <<<'SQL'
            SELECT TABLE_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME = 'deleted_at'
            SQL,
    );

    if ($softDeleteStatement === false) {
        throw new RuntimeException('Unable to inspect soft-delete columns.');
    }

    assertSameList(
        ['businesses', 'guides', 'users', 'work_cases'],
        $softDeleteStatement->fetchAll(PDO::FETCH_COLUMN),
        'The soft-delete table set is invalid.',
    );

    $foreignKeyStatement = $connection->query(
        <<<'SQL'
            SELECT CONSTRAINT_NAME, DELETE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            SQL,
    );

    if ($foreignKeyStatement === false) {
        throw new RuntimeException('Unable to inspect foreign keys.');
    }

    $actualDeleteRules = [];

    while ($row = $foreignKeyStatement->fetch()) {
        $actualDeleteRules[(string) $row['CONSTRAINT_NAME']] = (string) $row['DELETE_RULE'];
    }

    assertSameMap($expectedDeleteRules, $actualDeleteRules, 'The foreign-key delete rules are invalid.');

    $offeringIndexStatement = $connection->query(
        <<<'SQL'
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'business_offerings'
            GROUP BY INDEX_NAME
            SQL,
    );

    if ($offeringIndexStatement === false) {
        throw new RuntimeException('Unable to inspect business offering indexes.');
    }

    $offeringIndexes = $offeringIndexStatement->fetchAll(PDO::FETCH_COLUMN);

    foreach ([
        'uq_business_offerings_combination',
        'idx_business_offerings_category_service',
        'idx_business_offerings_service_id',
        'idx_business_offerings_status',
    ] as $requiredIndex) {
        if (!in_array($requiredIndex, $offeringIndexes, true)) {
            throw new RuntimeException(sprintf(
                'Required business offering index is missing: %s',
                $requiredIndex,
            ));
        }
    }

    fwrite(STDOUT, "Database schema tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database schema tests failed: %s\n", $exception->getMessage()));
    exit(1);
}
