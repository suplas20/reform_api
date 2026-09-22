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

/**
 * @param list<string> $requiredIndexes
 */
function assertRequiredIndexes(
    PDO $connection,
    string $table,
    array $requiredIndexes,
): void {
    $statement = $connection->prepare(
        <<<'SQL'
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            GROUP BY INDEX_NAME
            SQL,
    );
    $statement->execute(['table_name' => $table]);
    $actualIndexes = $statement->fetchAll(PDO::FETCH_COLUMN);

    foreach ($requiredIndexes as $requiredIndex) {
        if (!in_array($requiredIndex, $actualIndexes, true)) {
            throw new RuntimeException(sprintf(
                'Required index is missing from %s: %s',
                $table,
                $requiredIndex,
            ));
        }
    }
}

$expectedTables = [
    'ad_events',
    'ads',
    'admins',
    'auth_sessions',
    'brands',
    'business_brands',
    'business_categories',
    'business_claims',
    'business_external_ratings',
    'business_external_refs',
    'business_hours',
    'business_images',
    'business_links',
    'business_locations',
    'business_offerings',
    'business_reputation_stats',
    'business_sources',
    'business_verifications',
    'businesses',
    'categories',
    'category_services',
    'event_logs',
    'guides',
    'help_categories',
    'help_faqs',
    'inquiries',
    'inquiry_media',
    'inquiry_replies',
    'media_uploads',
    'notifications',
    'recent_businesses',
    'regions',
    'schema_migrations',
    'search_logs',
    'services',
    'review_images',
    'reviews',
    'review_helpful_users',
    'review_tags',
    'user_favorites',
    'user_devices',
    'user_settings',
    'user_social_accounts',
    'users',
    'work_case_images',
    'work_case_services',
    'work_cases',
];

$expectedDeleteRules = [
    'fk_ad_events_ad' => 'CASCADE',
    'fk_ad_events_user' => 'SET NULL',
    'fk_auth_sessions_user' => 'CASCADE',
    'fk_business_brands_brand' => 'RESTRICT',
    'fk_business_brands_business' => 'CASCADE',
    'fk_business_categories_business' => 'CASCADE',
    'fk_business_categories_category' => 'RESTRICT',
    'fk_business_claims_admin' => 'SET NULL',
    'fk_business_claims_business' => 'CASCADE',
    'fk_business_claims_user' => 'CASCADE',
    'fk_business_external_ratings_ref' => 'CASCADE',
    'fk_business_external_refs_business' => 'CASCADE',
    'fk_business_hours_business' => 'CASCADE',
    'fk_business_images_business' => 'CASCADE',
    'fk_business_links_business' => 'CASCADE',
    'fk_business_locations_business' => 'CASCADE',
    'fk_business_locations_region' => 'RESTRICT',
    'fk_business_offerings_business' => 'CASCADE',
    'fk_business_offerings_category' => 'RESTRICT',
    'fk_business_offerings_service' => 'RESTRICT',
    'fk_business_reputation_stats_business' => 'CASCADE',
    'fk_business_sources_business' => 'CASCADE',
    'fk_business_verifications_admin' => 'SET NULL',
    'fk_business_verifications_business' => 'CASCADE',
    'fk_businesses_claimed_user' => 'SET NULL',
    'fk_categories_parent' => 'RESTRICT',
    'fk_category_services_category' => 'RESTRICT',
    'fk_category_services_service' => 'RESTRICT',
    'fk_regions_parent' => 'RESTRICT',
    'fk_help_faqs_category' => 'RESTRICT',
    'fk_inquiries_business' => 'CASCADE',
    'fk_inquiries_category' => 'RESTRICT',
    'fk_inquiries_user' => 'CASCADE',
    'fk_inquiry_media_inquiry' => 'CASCADE',
    'fk_inquiry_media_upload' => 'RESTRICT',
    'fk_inquiry_replies_inquiry' => 'CASCADE',
    'fk_media_uploads_user' => 'CASCADE',
    'fk_notifications_user' => 'CASCADE',
    'fk_recent_businesses_business' => 'CASCADE',
    'fk_recent_businesses_user' => 'CASCADE',
    'fk_review_helpful_users_review' => 'CASCADE',
    'fk_review_helpful_users_user' => 'CASCADE',
    'fk_review_images_review' => 'CASCADE',
    'fk_review_tags_review' => 'CASCADE',
    'fk_reviews_business' => 'CASCADE',
    'fk_reviews_user' => 'CASCADE',
    'fk_social_accounts_user' => 'CASCADE',
    'fk_user_favorites_business' => 'CASCADE',
    'fk_user_favorites_user' => 'CASCADE',
    'fk_user_devices_user' => 'CASCADE',
    'fk_user_settings_user' => 'CASCADE',
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
        ['businesses', 'guides', 'reviews', 'users', 'work_cases'],
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

    assertRequiredIndexes(
        $connection,
        'business_offerings',
        [
            'uq_business_offerings_combination',
            'idx_business_offerings_category_service',
            'idx_business_offerings_service_id',
            'idx_business_offerings_status',
        ],
    );
    assertRequiredIndexes(
        $connection,
        'business_external_refs',
        [
            'uq_business_external_refs_business_provider',
            'uq_business_external_refs_provider_external_id',
        ],
    );
    assertRequiredIndexes(
        $connection,
        'business_external_ratings',
        [
            'uq_business_external_ratings_business_provider',
            'idx_business_external_ratings_provider',
            'idx_business_external_ratings_rating',
            'idx_business_external_ratings_review_count',
            'idx_business_external_ratings_fetched_at',
            'idx_business_external_ratings_expires_at',
        ],
    );
    assertRequiredIndexes(
        $connection,
        'reviews',
        [
            'idx_reviews_business_id',
            'idx_reviews_user_id',
            'idx_reviews_status',
            'idx_reviews_created_at',
            'idx_reviews_business_status',
            'uq_reviews_user_idempotency',
        ],
    );

    $ratingCheckStatement = $connection->prepare(
        <<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.CHECK_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = :constraint_name
            SQL,
    );
    $ratingCheckStatement->execute(['constraint_name' => 'chk_reviews_rating']);

    if ((int) $ratingCheckStatement->fetchColumn() !== 1) {
        throw new RuntimeException('The reviews rating check constraint is missing.');
    }

    fwrite(STDOUT, "Database schema tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database schema tests failed: %s\n", $exception->getMessage()));
    exit(1);
}
