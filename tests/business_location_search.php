<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repository\BusinessRepository;
use App\Service\BusinessService;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertLocationSearch(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{float, float} */
function destinationForTest(float $latitude, float $longitude, float $meters, float $bearing = 0): array
{
    $angle = $meters / 6370986.0;
    $lat = deg2rad($latitude);
    $lng = deg2rad($longitude);
    $bearing = deg2rad($bearing);
    $newLat = asin(sin($lat) * cos($angle) + cos($lat) * sin($angle) * cos($bearing));
    $newLng = $lng + atan2(sin($bearing) * sin($angle) * cos($lat), cos($angle) - sin($lat) * sin($newLat));
    return [rad2deg($newLat), fmod(rad2deg($newLng) + 540, 360) - 180];
}

// Capture real prepared queries and enforce read-only search behavior after fixture setup.
final class LocationSearchStatement extends PDOStatement
{
    private array $bound = [];
    protected function __construct(private readonly Closure $record)
    {
    }
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[$param] = $value;
        return parent::bindValue($param, $value, $type);
    }
    public function execute(?array $params = null): bool
    {
        assertLocationSearch(preg_match('/\A\s*(SELECT|EXPLAIN)\b/i', $this->queryString) === 1, 'Location searches must never write user coordinates or any other data.');
        ($this->record)($this->queryString, $params ?? $this->bound);
        return parent::execute($params);
    }
}

$connection = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$connection->beginTransaction();
try {
    $suffix = 'geo-' . bin2hex(random_bytes(8));
    $now = gmdate('Y-m-d H:i:s');
    $region = $connection->prepare('INSERT INTO regions (name, slug, level, status, created_at, updated_at) VALUES (:name, :slug, 1, :status, :created_at, :updated_at)');
    $region->execute(['name' => $suffix, 'slug' => $suffix, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $regionId = (int) $connection->lastInsertId();
    $insertBusiness = $connection->prepare('INSERT INTO businesses (name, slug, supports_shipping, supports_visit, supports_pickup, status, published_at, created_at, updated_at, deleted_at) VALUES (:name, :slug, 0, 1, 0, :status, :published_at, :created_at, :updated_at, :deleted_at)');
    $insertLocation = $connection->prepare('INSERT INTO business_locations (business_id, region_id, road_address, latitude, longitude, is_primary, created_at, updated_at) VALUES (:business_id, :region_id, :road_address, :latitude, :longitude, :is_primary, :created_at, :updated_at)');
    $addLocation = static function (int $id, ?array $coordinates, int $primary = 1) use ($insertLocation, $regionId, $now): void {
        $insertLocation->execute([
            'business_id' => $id, 'region_id' => $regionId, 'road_address' => '거리 검색 테스트',
            'latitude' => $coordinates === null ? null : sprintf('%.7F', $coordinates[0]),
            'longitude' => $coordinates === null ? null : sprintf('%.7F', $coordinates[1]),
            'is_primary' => $primary, 'created_at' => $now, 'updated_at' => $now,
        ]);
    };
    $addBusiness = static function (string $group, string $label, ?array $coordinates, string $status = 'ACTIVE', ?string $publishedAt = null, ?string $deletedAt = null) use ($insertBusiness, $addLocation, $connection, $suffix, $now): int {
        $insertBusiness->execute([
            'name' => $suffix . '-' . $group . '-' . $label, 'slug' => $suffix . '-' . $group . '-' . $label,
            'status' => $status, 'published_at' => $publishedAt ?? $now,
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $deletedAt,
        ]);
        $id = (int) $connection->lastInsertId();
        $addLocation($id, $coordinates);
        return $id;
    };

    $distances = [0, 500, 999.8, 1000.2, 1500, 2999.8, 3000.2, 3500, 4999.8, 5000.2, 7000, 9999.8, 10000.2, 11000];
    $ids = [];
    foreach ($distances as $index => $distance) {
        $ids[$index] = $addBusiness('seoul', 'distance-' . $index, destinationForTest(37.5, 127.03, $distance));
    }
    $tieId = $addBusiness('seoul', 'zero-tie', [37.5, 127.03]);
    $unknownId = $addBusiness('seoul', 'missing', null);
    $invalidId = $addBusiness('seoul', 'invalid', [95, 200]);
    // Inside the 5 km bounding square, but outside the actual search circle.
    $cornerId = $addBusiness('seoul', 'corner', [37.5 + rad2deg(4000 / 6370986), 127.03 + rad2deg(4000 / (6370986 * cos(deg2rad(37.5))))]);
    $farPrimaryId = $addBusiness('seoul', 'far-primary', destinationForTest(37.5, 127.03, 11000));
    $addLocation($farPrimaryId, [37.5, 127.03], 0);
    $missingPrimaryId = $addBusiness('seoul', 'missing-primary', null);
    $addLocation($missingPrimaryId, [37.5, 127.03], 0);
    $noPrimaryId = $addBusiness('fallback', 'first', [37.5, 127.03]);
    $updatePrimary = $connection->prepare('UPDATE business_locations SET is_primary = 0 WHERE business_id = :id');
    $updatePrimary->execute(['id' => $noPrimaryId]);
    $addLocation($noPrimaryId, destinationForTest(37.5, 127.03, 11000), 0);
    $addBusiness('seoul', 'draft', [37.5, 127.03], 'DRAFT');
    $addBusiness('seoul', 'future', [37.5, 127.03], publishedAt: gmdate('Y-m-d H:i:s', time() + 86400));
    $addBusiness('seoul', 'deleted', [37.5, 127.03], deletedAt: $now);
    $datelineId = $addBusiness('dateline', 'across', [0, -179.999]);
    $addBusiness('dateline', 'outside', [0, -179.9]);
    $poleId = $addBusiness('pole', 'across', [89.999, -135]);
    $addBusiness('pole', 'outside', [89, 45]);
    $antipodeId = $addBusiness('antipode', 'opposite', [0, 180]);

    $queries = [];
    $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LocationSearchStatement::class, [
        static function (string $sql, array $parameters) use (&$queries): void { $queries[] = [$sql, $parameters]; },
    ]]);
    $service = new BusinessService(new BusinessRepository($connection));
    $search = static fn (array $criteria, int $page = 1, int $perPage = 100): array => $service->getPublicList($page, $perPage, $criteria + ['keyword' => $suffix . '-seoul-', 'lat' => 37.5, 'lng' => 127.03, 'sort' => 'distance']);

    foreach ([1, 3, 5, 10] as $radius) {
        $result = $search(['radiusKm' => (float) $radius]);
        $expected = count(array_filter($distances, static fn ($distance): bool => $distance <= $radius * 1000)) + 1;
        if ($radius === 10) {
            ++$expected; // The 5 km bounding-box corner is within 10 km.
        }
        assertLocationSearch($result['total'] === $expected && count($result['items']) === $expected, 'Radius count and page membership must agree.');
        $last = -1.0;
        foreach ($result['items'] as $item) {
            assertLocationSearch(is_float($item['distanceMeters']) && $item['distanceMeters'] >= $last && $item['distanceMeters'] <= $radius * 1000, 'Distances must be nonnegative, in meters, within the circle and sorted.');
            assertLocationSearch(abs($item['distanceKm'] * 1000 - $item['distanceMeters']) < 0.00001, 'Legacy kilometer distance must match meter distance.');
            $last = $item['distanceMeters'];
        }
        assertLocationSearch($result['items'][0]['id'] === $tieId, 'Equal distances must retain the stable ID tie-breaker.');
    }
    $first = $search(['radiusKm' => 5.0], 1, 2);
    $second = $search(['radiusKm' => 5.0], 2, 2);
    assertLocationSearch(count(array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id'))) === 0, 'Location pagination must not duplicate businesses.');
    assertLocationSearch($first['total'] === $second['total'], 'Location pagination totals must agree.');
    $unsorted = $search(['sort' => 'recent', 'radiusKm' => 5.0]);
    assertLocationSearch($unsorted['items'][0]['distanceMeters'] !== null, 'Coordinates must produce distances with other sorts too.');
    $withoutRadius = $search([]);
    $lastIds = array_column(array_slice($withoutRadius['items'], -3), 'id');
    assertLocationSearch(count(array_intersect($lastIds, [$unknownId, $invalidId, $missingPrimaryId])) === 3, 'Missing and invalid primary coordinates must sort last.');
    $far = array_values(array_filter($withoutRadius['items'], static fn ($item): bool => $item['id'] === $farPrimaryId))[0];
    assertLocationSearch(abs($far['distanceMeters'] - 11000) < 0.05, 'Distance and radius must use the displayed primary location, not a different branch.');
    $origin = array_values(array_filter($withoutRadius['items'], static fn ($item): bool => $item['id'] === $ids[0]))[0];
    assertLocationSearch(abs($origin['distanceMeters']) < 0.01, 'Identical coordinates must return zero distance.');
    $fallback = $search(['keyword' => $suffix . '-fallback-', 'radiusKm' => 1.0]);
    assertLocationSearch($fallback['total'] === 1 && $fallback['items'][0]['id'] === $noPrimaryId, 'Missing primary flags must use the first location deterministically.');
    foreach ([179.999, -180.0, 180.0] as $longitude) {
        $result = $search(['keyword' => $suffix . '-dateline-', 'lat' => 0.0, 'lng' => $longitude, 'radiusKm' => 1.0]);
        assertLocationSearch($result['total'] === 1 && $result['items'][0]['id'] === $datelineId, 'Bounding boxes must include nearby points across the dateline.');
    }
    foreach ([89.999, 90.0] as $latitude) {
        $result = $search(['keyword' => $suffix . '-pole-', 'lat' => $latitude, 'lng' => 45.0, 'radiusKm' => 1.0]);
        assertLocationSearch($result['total'] === 1 && $result['items'][0]['id'] === $poleId, 'Polar bounding boxes must cover all longitudes.');
    }
    $world = $search(['radiusKm' => PHP_FLOAT_MAX]);
    assertLocationSearch($world['total'] === count($withoutRadius['items']) - 3, 'Very large finite radii must not overflow or include invalid coordinates.');
    $antipode = $search(['keyword' => $suffix . '-antipode-', 'lat' => 0.0, 'lng' => 0.0, 'radiusKm' => PHP_FLOAT_MAX]);
    assertLocationSearch($antipode['total'] === 1 && $antipode['items'][0]['id'] === $antipodeId, 'World-sized searches must include the exact antipode despite parameter rounding.');
    $noUserCoordinates = $service->getPublicList(1, 100, ['keyword' => $suffix . '-seoul-']);
    assertLocationSearch($noUserCoordinates['items'][0]['distanceMeters'] === null, 'Requests without user coordinates must keep null distances.');

    [$countSql, $countParameters] = $queries[0];
    $explain = $connection->prepare('EXPLAIN ' . $countSql);
    $explain->execute($countParameters);
    $plan = $explain->fetchAll();
    $coordinatePlan = array_values(array_filter($plan, static fn (array $row): bool => $row['table'] === 'business_locations' && str_contains((string) $row['possible_keys'], 'idx_business_locations_coordinates')));
    assertLocationSearch($coordinatePlan !== [], 'Bounding-box predicates must expose the existing coordinate index to the optimizer.');
    echo 'Coordinate candidate plan: ' . json_encode($coordinatePlan, JSON_THROW_ON_ERROR) . PHP_EOL;
    $connection->rollBack();
    echo "Business location search integration tests passed (fixtures rolled back).\n";
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
