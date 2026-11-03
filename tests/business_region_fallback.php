<?php

declare(strict_types=1);

use App\Controller\Api\V1\BusinessController;
use App\Database\ConnectionFactory;
use App\Repository\BusinessRepository;
use App\Service\BusinessService;
use App\Repository\RegionCenterRepository;
use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertRegionFallback(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class RegionFallbackFixtureRepository
{
    public function __construct(private readonly PDO $db, private readonly string $prefix)
    {
    }

    private function insert(string $sql, array $parameters): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return (int) $this->db->lastInsertId();
    }

    public function region(?int $parent, string $name, int $level, string $status = 'ACTIVE'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->insert(
            'INSERT INTO regions (parent_id, name, slug, level, status, created_at, updated_at)
             VALUES (:parent, :name, :slug, :level, :status, :created, :updated)',
            ['parent' => $parent, 'name' => $name, 'slug' => $this->prefix . '-' . bin2hex(random_bytes(4)),
                'level' => $level, 'status' => $status, 'created' => $now, 'updated' => $now],
        );
    }

    public function business(string $label, int $regionId, ?float $latitude, string $state = 'ACTIVE'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $id = $this->insert(
            'INSERT INTO businesses (name, slug, supports_shipping, supports_visit, supports_pickup,
                 status, published_at, deleted_at, created_at, updated_at)
             VALUES (:name, :slug, 0, 1, 0, :status, :published, :deleted, :created, :updated)',
            ['name' => $this->prefix . '-' . $label, 'slug' => $this->prefix . '-' . $label,
                'status' => $state === 'DRAFT' ? 'DRAFT' : 'ACTIVE',
                'published' => gmdate('Y-m-d H:i:s', time() + ($state === 'FUTURE' ? 86400 : -60)),
                'deleted' => $state === 'DELETED' ? $now : null, 'created' => $now, 'updated' => $now],
        );
        $this->location($id, $regionId, $latitude);
        return $id;
    }

    public function location(int $businessId, int $regionId, ?float $latitude, int $primary = 1): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->insert(
            'INSERT INTO business_locations (business_id, region_id, road_address, latitude, longitude, is_primary, created_at, updated_at)
             VALUES (:business, :region, :address, :lat, :lng, :primary, :created, :updated)',
            ['business' => $businessId, 'region' => $regionId, 'address' => $this->prefix, 'lat' => $latitude,
                'lng' => $latitude === null ? null : 127.03, 'primary' => $primary, 'created' => $now, 'updated' => $now],
        );
    }

    public function filters(int $businessId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $params = ['name' => $this->prefix, 'slug' => $this->prefix, 'status' => 'ACTIVE', 'created' => $now, 'updated' => $now];
        $category = $this->insert('INSERT INTO categories (name, slug, status, created_at, updated_at) VALUES (:name, :slug, :status, :created, :updated)', $params);
        $service = $this->insert('INSERT INTO services (name, slug, status, created_at, updated_at) VALUES (:name, :slug, :status, :created, :updated)', $params);
        $brand = $this->insert('INSERT INTO brands (name, slug, status, created_at, updated_at) VALUES (:name, :slug, :status, :created, :updated)', $params);
        $this->insert('INSERT INTO category_services (category_id, service_id) VALUES (:category, :service)', ['category' => $category, 'service' => $service]);
        $this->insert(
            'INSERT INTO business_offerings (business_id, category_id, service_id, status, created_at, updated_at)
             VALUES (:business, :category, :service, :status, :created, :updated)',
            ['business' => $businessId, 'category' => $category, 'service' => $service, 'status' => 'ACTIVE', 'created' => $now, 'updated' => $now],
        );
        $this->insert('INSERT INTO business_brands (business_id, brand_id, created_at) VALUES (:business, :brand, :created)',
            ['business' => $businessId, 'brand' => $brand, 'created' => $now]);
        $this->insert(
            'INSERT INTO business_verifications (business_id, verification_type, status, created_at, updated_at)
             VALUES (:business, :type, :status, :created, :updated)',
            ['business' => $businessId, 'type' => 'PHONE', 'status' => 'APPROVED', 'created' => $now, 'updated' => $now],
        );
        return ['categoryId' => $category, 'serviceId' => $service, 'brandId' => $brand, 'verifiedOnly' => true];
    }
}

final class RegionFallbackReadStatement extends PDOStatement
{
    protected function __construct(private readonly Closure $record)
    {
    }

    public function execute(?array $params = null): bool
    {
        assertRegionFallback(preg_match('/\A\s*(SELECT|WITH)\b/i', $this->queryString) === 1, 'Fallback searches must remain read-only.');
        ($this->record)($this->queryString);
        return parent::execute($params);
    }
}

$db = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$db->beginTransaction();
try {
    $prefix = 'nearby-scope-' . bin2hex(random_bytes(6));
    $fixtures = new RegionFallbackFixtureRepository($db, $prefix);
    $city = $fixtures->region(null, $prefix . '시', 1);
    $district = $fixtures->region($city, '선택구', 2);
    $dong = $fixtures->region($district, '선택동', 3);
    $otherDistrict = $fixtures->region($city, '인근구', 2);
    $otherCity = $fixtures->region(null, $prefix . '다른시', 1);
    $otherCityDistrict = $fixtures->region($otherCity, '다른시구', 2);
    $missingDong = $fixtures->region($district, '미확인동', 3);
    $forecastDong = $fixtures->region($district, '예보좌표동', 3);
    $centers = new RegionCenterRepository($db);
    $centers->update($dong, ['latitude' => 37.5, 'longitude' => 127.03, 'source_code' => 'office'], 'OFFICE-TEST-CENTERS', gmdate('Y-m-d H:i:s'));
    $centers->update($district, ['latitude' => 38.0, 'longitude' => 127.03, 'source_code' => 'parent'], 'OFFICE-TEST-CENTERS', gmdate('Y-m-d H:i:s'));
    $centers->update($forecastDong, ['latitude' => 37.5, 'longitude' => 127.03, 'source_code' => 'forecast'], 'KMA-LEGACY', gmdate('Y-m-d H:i:s'));
    $local = $fixtures->business('match-local', $dong, 37.502);
    $neighbor = $fixtures->business('match-neighbor', $otherDistrict, 37.501);
    $otherCityMatch = $fixtures->business('match-other-city', $otherCityDistrict, 37.503);
    $fiveKm = $fixtures->business('match-5km', $district, 37.5 + rad2deg(4999.8 / 6370986));
    $tenKm = $fixtures->business('match-10km', $otherCityDistrict, 37.5 + rad2deg(9999.8 / 6370986));
    $twentyKm = $fixtures->business('match-20km', $otherDistrict, 37.5 + rad2deg(19999.8 / 6370986));
    $fixtures->business('match-outside', $district, 37.5 + rad2deg(20000.2 / 6370986));
    $fixtures->business('match-missing', $dong, null);
    $fixtures->location($neighbor, $otherDistrict, 37.501, 0);
    foreach (['DRAFT', 'FUTURE', 'DELETED'] as $state) {
        $fixtures->business('match-hidden-' . $state, $dong, 37.50001, $state);
    }
    $filters = $fixtures->filters($neighbor);
    $queries = [];
    $db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RegionFallbackReadStatement::class,
        [static function (string $sql) use (&$queries): void { $queries[] = $sql; }]]);
    $service = new BusinessService(new BusinessRepository($db));
    $manual = ['keyword' => $prefix . '-match-', 'regionId' => $dong];
    $automatic = ['keyword' => $prefix . '-match-', 'lat' => 37.5, 'lng' => 127.03];
    foreach ([null, 10.0, 20.0] as $requestedRadius) {
        $radius = $requestedRadius ?? 5.0;
        $radiusFilter = $requestedRadius === null ? [] : ['radiusKm' => $requestedRadius];
        $selected = $service->getPublicList(1, 100, $manual + $radiusFilter);
        $gps = $service->getPublicList(1, 100, $automatic + $radiusFilter);
        $expected = [$neighbor, $local, $otherCityMatch, $fiveKm];
        if ($radius >= 10) { $expected[] = $tenKm; }
        if ($radius >= 20) { $expected[] = $twentyKm; }
        assertRegionFallback(array_column($selected['items'], 'id') === $expected, 'Manual searches must cross district/city borders and stop at the circle.');
        assertRegionFallback($selected['items'] === $gps['items'] && $selected['total'] === $gps['total'], 'Identical GPS and office origins must return identical ordered businesses and distances.');
        assertRegionFallback($selected['distanceOrigin']['type'] === 'REGION_CENTER' && $selected['distanceOrigin']['regionId'] === $dong, 'Manual origin must remain the selected office.');
        assertRegionFallback($selected['regionSearch']['appliedRegionIds'] === [] && !$selected['regionSearch']['expanded'], 'No administrative scope may be applied.');
        assertRegionFallback($gps['regionSearch'] === null && $gps['distanceOrigin']['type'] === 'COORDINATES', 'GPS origin metadata must remain compatible.');
    }
    $selected = $service->getPublicList(1, 100, $manual);
    $paged = [];
    for ($page = 1; $page <= 3; ++$page) {
        $result = $service->getPublicList($page, 2, $manual);
        assertRegionFallback($result['total'] === 4 && $result['totalPages'] === 2, 'Pagination must retain the same radial count.');
        $paged = array_merge($paged, array_column($result['items'], 'id'));
    }
    assertRegionFallback($paged === array_column($selected['items'], 'id'), 'Pagination must preserve stable distance ordering without duplicates.');
    $filtered = $service->getPublicList(1, 100, $manual + $filters);
    assertRegionFallback(array_column($filtered['items'], 'id') === [$neighbor], 'Category, service, brand and verification filters must still apply across boundaries.');
    $empty = $service->getPublicList(1, 100, ['keyword' => $prefix . '-no-match-', 'regionId' => $dong]);
    assertRegionFallback($empty['total'] === 0 && $empty['items'] === [], 'An empty circle must not trigger administrative expansion.');
    $explicit = $service->getPublicList(1, 100, $manual + ['lat' => 37.501, 'lng' => 127.03]);
    assertRegionFallback($explicit['distanceOrigin']['type'] === 'COORDINATES' && $explicit['items'][0]['distanceMeters'] < 0.01, 'Explicit coordinates must take priority over the office, with no region boundary.');
    foreach ([$missingDong, $forecastDong, PHP_INT_MAX] as $unverifiedId) {
        try {
            $service->getPublicList(1, 100, ['keyword' => $prefix, 'regionId' => $unverifiedId]);
            throw new LogicException('Unverified offices must not fall back to parent centers or administrative lists.');
        } catch (ValidationException $error) {
            assertRegionFallback($error->errorCode() === 'LOCATION_COORDINATES_UNAVAILABLE', 'Missing office must have the documented error code.');
        }
    }
    $response = (new BusinessController($service))->index(Request::create('/api/v1/businesses', 'GET', ['regionId' => (string) $dong, 'keyword' => $prefix . '-match-', 'radiusKm' => '20']));
    $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertRegionFallback($response->getStatusCode() === 200 && $json['meta']['total'] === 6 && $json['meta']['distanceOrigin']['regionId'] === $dong, 'Region-only API requests must support a 20km circle.');
    assertRegionFallback($queries !== [] && !array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'location_regions')), 'Nearby queries must not contain region-boundary SQL.');
    $db->rollBack();
    echo "Business nearby scope tests passed (GPS/office equality, cross-border circles, 5/10/20km, filters, pagination, validation, read-only; fixtures rolled back).\n";
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}
