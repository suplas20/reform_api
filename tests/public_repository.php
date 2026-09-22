<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repository\BrandRepository;
use App\Repository\BusinessRepository;
use App\Repository\CategoryRepository;
use App\Repository\RegionRepository;
use App\Repository\ReviewRepository;
use App\Repository\ServiceRepository;
use App\Repository\WorkCaseRepository;
use App\Service\BusinessService;
use App\Service\ReferenceService;
use App\Service\ReviewService;
use App\Service\WorkCaseService;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertPublicRepository(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $parameters */
function insertFixture(PDO $connection, string $sql, array $parameters): int
{
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);

    return (int) $connection->lastInsertId();
}

$connection = null;

try {
    $config = require dirname(__DIR__) . '/config/database.php';

    if (!is_array($config)) {
        throw new RuntimeException('Database configuration must return an array.');
    }

    $connection = (new ConnectionFactory($config))->connection();
    $connection->beginTransaction();
    $suffix = bin2hex(random_bytes(6));
    $now = gmdate('Y-m-d H:i:s');
    $publishedAt = gmdate('Y-m-d H:i:s', time() - 60);
    $userId = insertFixture(
        $connection,
        'INSERT INTO users (name, status, created_at, updated_at) VALUES (:name, :status, :created_at, :updated_at)',
        ['name' => '통합 테스트 사용자', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );
    $regionId = insertFixture(
        $connection,
        'INSERT INTO regions (name, slug, level, sort_order, status, created_at, updated_at) VALUES (:name, :slug, :level, :sort_order, :status, :created_at, :updated_at)',
        ['name' => '통합테스트구', 'slug' => 'region-' . $suffix, 'level' => 2, 'sort_order' => 1, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );
    $categoryId = insertFixture(
        $connection,
        'INSERT INTO categories (name, slug, sort_order, status, created_at, updated_at) VALUES (:name, :slug, :sort_order, :status, :created_at, :updated_at)',
        ['name' => '통합 카테고리', 'slug' => 'category-' . $suffix, 'sort_order' => 1, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );
    $serviceId = insertFixture(
        $connection,
        'INSERT INTO services (name, slug, sort_order, status, created_at, updated_at) VALUES (:name, :slug, :sort_order, :status, :created_at, :updated_at)',
        ['name' => '통합 서비스', 'slug' => 'service-' . $suffix, 'sort_order' => 1, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );
    $brandId = insertFixture(
        $connection,
        'INSERT INTO brands (name, slug, status, created_at, updated_at) VALUES (:name, :slug, :status, :created_at, :updated_at)',
        ['name' => '통합 브랜드', 'slug' => 'brand-' . $suffix, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );
    $businessId = insertFixture(
        $connection,
        'INSERT INTO businesses (name, slug, short_description, supports_shipping, supports_visit, supports_pickup, status, published_at, created_at, updated_at) VALUES (:name, :slug, :short_description, :supports_shipping, :supports_visit, :supports_pickup, :status, :published_at, :created_at, :updated_at)',
        ['name' => '통합 리폼 공방', 'slug' => 'business-' . $suffix, 'short_description' => '통합 테스트 업체', 'supports_shipping' => 0, 'supports_visit' => 1, 'supports_pickup' => 1, 'status' => 'ACTIVE', 'published_at' => $publishedAt, 'created_at' => $now, 'updated_at' => $now],
    );

    $statements = [
        ['INSERT INTO category_services (category_id, service_id, sort_order) VALUES (:category_id, :service_id, 1)', ['category_id' => $categoryId, 'service_id' => $serviceId]],
        ['INSERT INTO business_categories (business_id, category_id, is_primary, created_at) VALUES (:business_id, :category_id, 1, :created_at)', ['business_id' => $businessId, 'category_id' => $categoryId, 'created_at' => $now]],
        ['INSERT INTO business_offerings (business_id, category_id, service_id, is_primary, status, created_at, updated_at) VALUES (:business_id, :category_id, :service_id, 1, :status, :created_at, :updated_at)', ['business_id' => $businessId, 'category_id' => $categoryId, 'service_id' => $serviceId, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]],
        ['INSERT INTO business_brands (business_id, brand_id, created_at) VALUES (:business_id, :brand_id, :created_at)', ['business_id' => $businessId, 'brand_id' => $brandId, 'created_at' => $now]],
        ['INSERT INTO business_locations (business_id, region_id, road_address, latitude, longitude, is_primary, created_at, updated_at) VALUES (:business_id, :region_id, :road_address, :latitude, :longitude, 1, :created_at, :updated_at)', ['business_id' => $businessId, 'region_id' => $regionId, 'road_address' => '서울 통합테스트구 테스트로 1', 'latitude' => '37.5000000', 'longitude' => '127.0300000', 'created_at' => $now, 'updated_at' => $now]],
        ['INSERT INTO business_verifications (business_id, verification_type, status, verified_at, created_at, updated_at) VALUES (:business_id, :verification_type, :status, :verified_at, :created_at, :updated_at)', ['business_id' => $businessId, 'verification_type' => 'PHONE', 'status' => 'APPROVED', 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
        ['INSERT INTO business_reputation_stats (business_id, review_count, rating_avg, favorite_count, work_case_count, updated_at) VALUES (:business_id, 1, 5.00, 0, 1, :updated_at)', ['business_id' => $businessId, 'updated_at' => $now]],
    ];

    foreach ($statements as [$sql, $parameters]) {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
    }

    $workCaseId = insertFixture(
        $connection,
        'INSERT INTO work_cases (business_id, category_id, brand_id, title, slug, description, status, published_at, created_at, updated_at) VALUES (:business_id, :category_id, :brand_id, :title, :slug, :description, :status, :published_at, :created_at, :updated_at)',
        ['business_id' => $businessId, 'category_id' => $categoryId, 'brand_id' => $brandId, 'title' => '통합 작업 사례', 'slug' => 'work-case-' . $suffix, 'description' => '통합 상세', 'status' => 'PUBLISHED', 'published_at' => $publishedAt, 'created_at' => $now, 'updated_at' => $now],
    );
    $statement = $connection->prepare('INSERT INTO work_case_services (work_case_id, service_id) VALUES (:work_case_id, :service_id)');
    $statement->execute(['work_case_id' => $workCaseId, 'service_id' => $serviceId]);
    insertFixture(
        $connection,
        'INSERT INTO reviews (business_id, user_id, rating, content, status, created_at, updated_at) VALUES (:business_id, :user_id, :rating, :content, :status, :created_at, :updated_at)',
        ['business_id' => $businessId, 'user_id' => $userId, 'rating' => 5, 'content' => '통합 리뷰', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
    );

    $referenceService = new ReferenceService(
        new CategoryRepository($connection),
        new ServiceRepository($connection),
        new RegionRepository($connection),
        new BrandRepository($connection),
    );
    $regions = $referenceService->getRegions(null, null, '통합테스트');
    assertPublicRepository(count($regions) === 1 && $regions[0]['id'] === $regionId, 'Region query repository integration failed.');

    $businessService = new BusinessService(new BusinessRepository($connection));
    $businesses = $businessService->getPublicList(1, 20, [
        'keyword' => '통합 리폼',
        'categoryId' => $categoryId,
        'serviceId' => $serviceId,
        'regionId' => $regionId,
        'brandId' => $brandId,
        'lat' => null,
        'lng' => null,
        'radiusKm' => null,
        'verifiedOnly' => true,
        'sort' => 'recommended',
    ]);
    assertPublicRepository($businesses['total'] === 1, 'Business repository integration count failed.');
    assertPublicRepository($businesses['items'][0]['categoryId'] === $categoryId, 'Business category batch mapping failed.');
    assertPublicRepository($businesses['items'][0]['serviceIds'] === [$serviceId], 'Business service batch mapping failed.');
    assertPublicRepository($businesses['items'][0]['verified'] === true, 'Business verification batch mapping failed.');

    $workCaseService = new WorkCaseService(new WorkCaseRepository($connection));
    $workCases = $workCaseService->getPublicList(1, 20, ['businessId' => $businessId, 'categoryId' => null, 'serviceId' => $serviceId, 'brandId' => null]);
    assertPublicRepository($workCases['total'] === 1, 'Work Case repository integration count failed.');
    assertPublicRepository($workCases['items'][0]['services'][0]['id'] === $serviceId, 'Work Case service mapping failed.');
    assertPublicRepository($workCaseService->getPublicDetail($workCaseId)['business']['id'] === $businessId, 'Work Case detail integration failed.');

    $reviewService = new ReviewService(new ReviewRepository($connection));
    $reviews = $reviewService->getPublicList($businessId, 1, 20, ['rating' => 5, 'hasImages' => false, 'sort' => 'latest']);
    assertPublicRepository($reviews['total'] === 1, 'Review repository integration count failed.');
    assertPublicRepository($reviews['summary']['averageRating'] === 5.0, 'Review summary integration failed.');
    $reviewDistribution = $reviewService->getPublicSummary($businessId)['distribution'];
    assertPublicRepository(
        abs((float) $reviewDistribution[0]['ratio'] - 1.0) < 0.000001,
        'Review distribution integration failed.',
    );

    $connection->rollBack();
    fwrite(STDOUT, "Public repository integration tests passed.\n");
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, sprintf("Public repository integration tests failed: %s\n", $exception->getMessage()));
    exit(1);
}
