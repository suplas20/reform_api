<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repository\BusinessRepository;
use App\Service\BusinessService;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertBusinessSorting(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$connection->beginTransaction();

try {
    $suffix = 'sort-' . bin2hex(random_bytes(8));
    $now = gmdate('Y-m-d H:i:s');
    $region = $connection->prepare('INSERT INTO regions (name, slug, level, status, created_at, updated_at) VALUES (:name, :slug, 1, :status, :created_at, :updated_at)');
    $region->execute(['name' => $suffix, 'slug' => $suffix, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $regionId = (int) $connection->lastInsertId();
    $business = $connection->prepare('INSERT INTO businesses (name, slug, supports_shipping, supports_visit, supports_pickup, status, published_at, created_at, updated_at) VALUES (:name, :slug, 0, 1, 0, :status, :published_at, :created_at, :updated_at)');
    $stats = $connection->prepare('INSERT INTO business_reputation_stats (business_id, rating_avg, review_count, favorite_count, work_case_count, updated_at) VALUES (:business_id, :rating_avg, :review_count, 0, 0, :updated_at)');
    $location = $connection->prepare('INSERT INTO business_locations (business_id, region_id, road_address, latitude, longitude, is_primary, created_at, updated_at) VALUES (:business_id, :region_id, :road_address, :latitude, :longitude, 1, :created_at, :updated_at)');
    $fixtures = [
        ['가나다 공방', 4.5, 5, 0.005],
        ['나무 공방', 1.5, 10, 0.001],
        ['다솜 공방', null, 0, null],
        ['가나다 공방', 4.5, 5, 0.005],
        ['라온 공방', 0.0, 1, 0.002],
        ['미소 공방', null, 0, 0.0025],
    ];
    $ids = [];
    foreach ($fixtures as $index => [$name, $rating, $reviews, $offset]) {
        $business->execute(['name' => $suffix . '-' . $name, 'slug' => $suffix . '-' . $index, 'status' => 'ACTIVE', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $connection->lastInsertId();
        $ids[] = $id;
        $stats->execute(['business_id' => $id, 'rating_avg' => $rating, 'review_count' => $reviews, 'updated_at' => $now]);
        $location->execute(['business_id' => $id, 'region_id' => $regionId, 'road_address' => $suffix, 'latitude' => $offset === null ? null : 37.5 + $offset, 'longitude' => $offset === null ? null : 127.03, 'created_at' => $now, 'updated_at' => $now]);
    }

    $expected = [
        'distance' => [1, 4, 5, 3, 0, 2],
        'name' => [3, 0, 1, 2, 4, 5],
        'reviewCount' => [1, 3, 0, 4, 5, 2],
        'rating' => [3, 0, 1, 4, 5, 2],
        'ratingAsc' => [4, 1, 3, 0, 5, 2],
    ];
    $service = new BusinessService(new BusinessRepository($connection));
    foreach ($expected as $sort => $indexes) {
        $allExpectedIds = array_map(static fn (int $index): int => $ids[$index], $indexes);
        $expectedIds = array_values(array_filter($allExpectedIds, static fn (int $id): bool => $id !== $ids[2]));
        $criteria = ['keyword' => $suffix, 'regionId' => $regionId, 'lat' => 37.5, 'lng' => 127.03, 'sort' => $sort];
        $all = $service->getPublicList(1, 100, $criteria);
        assertBusinessSorting(array_column($all['items'], 'id') === $expectedIds, $sort . ': order, null handling or stable tie-breaker is incorrect.');
        $pagedIds = [];
        for ($page = 1; $page <= 3; ++$page) {
            $result = $service->getPublicList($page, 2, $criteria);
            assertBusinessSorting($result['total'] === 5 && $result['totalPages'] === 3, $sort . ': pagination totals are incorrect.');
            $pagedIds = array_merge($pagedIds, array_column($result['items'], 'id'));
        }
        assertBusinessSorting($pagedIds === $expectedIds, $sort . ': sorting must happen before pagination.');
        assertBusinessSorting(count(array_unique($pagedIds)) === 5, $sort . ': pages must not duplicate or omit businesses.');
        if ($sort !== 'distance') {
            $withoutLocation = $service->getPublicList(1, 100, ['keyword' => $suffix, 'sort' => $sort]);
            assertBusinessSorting(array_column($withoutLocation['items'], 'id') === $allExpectedIds, $sort . ': nationwide searches must support sorting without an origin.');
        }
    }
    $connection->rollBack();
    echo "Business sorting integration tests passed (five sorts, pagination, ties and missing ratings; fixtures rolled back).\n";
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
