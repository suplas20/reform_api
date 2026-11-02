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
    $prefix = 'region-fallback-' . bin2hex(random_bytes(6));
    $fixtures = new RegionFallbackFixtureRepository($db, $prefix);
    $metro = $fixtures->region(null, $prefix . '특별시', 1);
    $district = $fixtures->region($metro, '선택구', 2);
    $dong = $fixtures->region($district, '선택동', 3);
    $otherDistrict = $fixtures->region($metro, '인근구', 2);
    $otherDong = $fixtures->region($otherDistrict, '인근동', 3);
    $outsideMetro = $fixtures->region(null, $prefix . '다른시', 1);
    $outsideDistrict = $fixtures->region($outsideMetro, '다른구', 2);
    $local = $fixtures->business('local-only', $dong, 37.51);
    $fixtures->business('local-outside-dong', $district, 37.50001);
    $near = $fixtures->business('district-near', $district, 37.501);
    $far = $fixtures->business('district-far', $district, 37.507);
    $missing = $fixtures->business('district-missing', $district, null);
    $fixtures->business('district-other', $otherDistrict, 37.50001);
    $cityFar = $fixtures->business('city-far', $otherDistrict, 37.52);
    $cityNear = $fixtures->business('city-near', $otherDong, 37.502);
    $fixtures->location($cityNear, $otherDong, 37.502, 0);
    $fixtures->business('city-outside', $outsideDistrict, 37.50001);
    foreach (['DRAFT', 'FUTURE', 'DELETED'] as $state) {
        $fixtures->business('city-hidden-' . $state, $dong, 37.50001, $state);
    }
    $filterMatch = $fixtures->business('filtered-match', $otherDong, 37.503);
    $fixtures->business('filtered-wrong', $district, 37.50001);
    $filters = $fixtures->filters($filterMatch);

    $province = $fixtures->region(null, $prefix . '도', 1);
    $cityName = $prefix . '수원시';
    $cityDistrict = $fixtures->region($province, $cityName . ' 장안구', 2);
    $cityDong = $fixtures->region($cityDistrict, '파장동', 3);
    $siblingDistrict = $fixtures->region($province, $cityName . ' 영통구', 2);
    $siblingDong = $fixtures->region($siblingDistrict, '영통동', 3);
    $otherCityDistrict = $fixtures->region($province, $prefix . '성남시 분당구', 2);
    $flatCityMatch = $fixtures->business('flat-match', $siblingDong, 37.503);
    $fixtures->business('flat-other-city', $otherCityDistrict, 37.50001);
    $plainCity = $fixtures->region($province, $prefix . '춘천시', 2);
    $plainDong = $fixtures->region($plainCity, '빈동', 3);
    $fixtures->business('plain-outside', $otherCityDistrict, 37.50001);
    $inactiveDistrict = $fixtures->region($metro, '폐지구', 2, 'INACTIVE');
    $inactiveParentDong = $fixtures->region($inactiveDistrict, '잔여동', 3);

    $centerCity = $fixtures->region(null, $prefix . '기준시', 1);
    $centerDistrict = $fixtures->region($centerCity, '기준구', 2);
    $centerDong = $fixtures->region($centerDistrict, '기준동', 3);
    $centerMissingDong = $fixtures->region($centerDistrict, '좌표없는동', 3);
    $centerOtherDistrict = $fixtures->region($centerCity, '확장구', 2);
    $centers = new RegionCenterRepository($db);
    foreach ([$centerCity => 39.0, $centerDistrict => 38.0, $centerDong => 37.5] as $id => $latitude) {
        $centers->update($id, ['latitude' => $latitude, 'longitude' => 127.03, 'source_code' => 'test-' . $id], 'TEST-CENTERS', gmdate('Y-m-d H:i:s'));
    }
    $centerNear = $fixtures->business('center-district-near', $centerDistrict, 37.501);
    $centerFar = $fixtures->business('center-district-far', $centerDistrict, 37.502);
    $centerCityNear = $fixtures->business('center-city-near', $centerOtherDistrict, 37.504);
    $centerCityFar = $fixtures->business('center-city-far', $centerOtherDistrict, 37.506);
    $centerLocal = $fixtures->business('center-local', $centerDong, 37.503);

    $queries = [];
    $db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RegionFallbackReadStatement::class,
        [static function (string $sql) use (&$queries): void { $queries[] = $sql; }]]);
    $repository = new BusinessRepository($db);
    $service = new BusinessService($repository);
    $search = static fn (string $group, int $region, array $extra = [], int $page = 1, int $perPage = 100): array => $service->getPublicList(
        $page, $perPage, $extra + ['keyword' => $prefix . '-' . $group, 'regionId' => $region,
            'lat' => 37.5, 'lng' => 127.03, 'sort' => 'recommended'],
    );
    $result = $search('local-', $dong);
    assertRegionFallback($result['total'] === 1 && $result['items'][0]['id'] === $local && !$result['regionSearch']['expanded'],
        'A nonempty dong must not expand even if another business is closer.');
    $result = $search('district-', $dong);
    assertRegionFallback(array_column($result['items'], 'id') === [$near, $far, $missing]
        && $result['regionSearch']['appliedRegionIds'] === [$district], 'Empty dong must use its district and sort by distance, with null coordinates last.');
    assertRegionFallback($result['items'][0]['distanceMeters'] > 0 && $result['items'][2]['distanceMeters'] === null,
        'Fallback must retain meter distances and nullable coordinate behavior.');
    $result = $search('city-', $dong);
    assertRegionFallback($result['total'] === 2 && array_column($result['items'], 'id') === [$cityNear, $cityFar]
        && $result['regionSearch']['appliedRegionIds'] === [$metro], 'Empty district must use the city, including businesses stored at dong level without duplicates.');
    assertRegionFallback($result['regionSearch']['requestedRegionId'] === $dong && $result['regionSearch']['expanded'], 'Response must distinguish requested and applied scope.');
    $fromDistrict = $search('city-', $district);
    assertRegionFallback(array_column($fromDistrict['items'], 'id') === [$cityNear, $cityFar], 'A district selection must also expand to its city if empty.');
    $withRadius = $search('city-', $dong, ['radiusKm' => 1.0]);
    assertRegionFallback($withRadius['total'] === 1 && $withRadius['items'][0]['id'] === $cityNear, 'Explicit radius must survive region expansion.');
    assertRegionFallback($search('city-', $dong, ['radiusKm' => 0.001])['total'] === 0, 'Fallback must never silently remove the radius limit.');
    $filtered = $search('filtered-', $dong, $filters + ['radiusKm' => 1.0]);
    assertRegionFallback($filtered['total'] === 1 && $filtered['items'][0]['id'] === $filterMatch
        && $filtered['regionSearch']['appliedRegionIds'] === [$metro], 'Category, service, brand and verification must apply to each scope.');
    for ($page = 1; $page <= 4; ++$page) {
        $paged = $search('district-', $dong, page: $page, perPage: 1);
        assertRegionFallback($paged['total'] === 3 && $paged['totalPages'] === 3 && $paged['regionSearch']['appliedRegionIds'] === [$district],
            'Empty pages must not trigger further expansion or change total counts.');
        assertRegionFallback(array_column($paged['items'], 'id') === ($page <= 3 ? [[$near, $far, $missing][$page - 1]] : []),
            'Fallback distance sort must happen before pagination.');
    }
    $manual = $search('district-', $dong, ['lat' => null, 'lng' => null, 'sort' => 'name']);
    assertRegionFallback(array_column($manual['items'], 'id') === [$far, $missing, $near]
        && $manual['items'][0]['distanceMeters'] === null, 'Manual location without coordinates must retain the requested non-distance sort.');
    $flat = $search('flat-', $cityDong);
    assertRegionFallback($flat['total'] === 1 && $flat['items'][0]['id'] === $flatCityMatch
        && $flat['regionSearch']['appliedRegionIds'] === [$cityDistrict, $siblingDistrict]
        && $flat['regionSearch']['appliedRegionName'] === $cityName, 'City expansion must not include a different city in the same province.');
    $flatFromDistrict = $search('flat-', $cityDistrict);
    assertRegionFallback($flatFromDistrict['total'] === 1 && $flatFromDistrict['items'][0]['id'] === $flatCityMatch, 'Flattened district selections must expand to sibling districts.');
    $plain = $search('plain-', $plainDong);
    assertRegionFallback($plain['total'] === 0 && $plain['regionSearch']['appliedRegionIds'] === [$plainCity], 'A full city selection must stop at the city boundary.');
    $unknown = $search('city-', PHP_INT_MAX);
    assertRegionFallback($unknown['total'] === 0 && !$unknown['regionSearch']['expanded'], 'Unknown regions must not become nationwide searches.');
    $empty = $search('no-matches-', $dong);
    assertRegionFallback($empty['items'] === [] && $empty['total'] === 0 && $empty['totalPages'] === 0,
        'All empty scopes must produce an empty result without removing the keyword.');
    $inactive = $search('city-', $inactiveParentDong);
    assertRegionFallback($inactive['total'] === 0 && !$inactive['regionSearch']['expanded'], 'Fallback must not traverse an inactive parent.');
    $withoutRegion = $service->getPublicList(1, 100, ['keyword' => $prefix . '-city-']);
    assertRegionFallback($withoutRegion['total'] === 3 && $withoutRegion['regionSearch'] === null, 'Requests without a region must preserve their original behavior.');
    foreach ([[], [0], ['1 OR 1=1']] as $invalidIds) {
        try {
            $repository->countPublic(gmdate('Y-m-d H:i:s'), ['regionIds' => $invalidIds]);
            throw new LogicException('Invalid internal region scopes must not execute.');
        } catch (InvalidArgumentException) {
        }
    }
    $response = (new BusinessController($service))->index(Request::create('/api/v1/businesses', 'GET',
        ['regionId' => (string) $dong, 'keyword' => $prefix . '-city-', 'latitude' => '37.5', 'longitude' => '127.03']));
    $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertRegionFallback($response->getStatusCode() === 200 && $json['success'] === true
        && $json['meta']['regionSearch']['expanded'] === true && $json['meta']['total'] === 2
        && array_column($json['data'], 'id') === [$cityNear, $cityFar], 'API response and coordinate aliases must expose fallback results and metadata.');
    assertRegionFallback($queries !== [], 'Integration searches must exercise real prepared statements.');
    // A region-only request must use the selected dong's reference point, never
    // the district/city centers even when those scopes supply the results.
    foreach ([['center-district-', [$centerNear, $centerFar], $centerDistrict],
        ['center-city-', [$centerCityNear, $centerCityFar], $centerCity]] as [$group, $expectedIds, $scopeId]) {
        $centered = $search($group, $centerDong, ['lat' => null, 'lng' => null]);
        assertRegionFallback(array_column($centered['items'], 'id') === $expectedIds
            && $centered['regionSearch']['appliedRegionIds'] === [$scopeId]
            && $centered['distanceOrigin']['type'] === 'REGION_CENTER'
            && $centered['distanceOrigin']['regionId'] === $centerDong
            && $centered['distanceOrigin']['latitude'] === 37.5,
            'Expanded manual-region searches must keep the selected dong origin and distance ordering.');
        assertRegionFallback($centered['items'][0]['distanceMeters'] > 100 && $centered['items'][0]['distanceMeters'] < 500,
            'Distances must be measured from the selected dong, not its far-away parent center.');
    }
    $centeredLocal = $search('center-local', $centerDong, ['lat' => null, 'lng' => null]);
    assertRegionFallback(!$centeredLocal['regionSearch']['expanded'] && $centeredLocal['items'][0]['id'] === $centerLocal
        && $centeredLocal['items'][0]['distanceKm'] > 0, 'Direct dong results must use the same region center.');
    $explicitOrigin = $search('center-district-', $centerDong, ['lat' => 37.501, 'lng' => 127.03]);
    assertRegionFallback($explicitOrigin['distanceOrigin']['type'] === 'COORDINATES'
        && $explicitOrigin['items'][0]['distanceMeters'] < 0.01, 'Explicit request coordinates must retain priority over stored centers.');
    $noCenter = $search('center-district-', $centerMissingDong, ['lat' => null, 'lng' => null]);
    assertRegionFallback($noCenter['distanceOrigin'] === null && $noCenter['items'][0]['distanceMeters'] === null,
        'A missing dong center must never silently use its parent center.');
    try {
        $search('center-district-', $centerMissingDong, ['lat' => null, 'lng' => null, 'sort' => 'distance']);
        throw new LogicException('Distance sorting with no valid origin must fail validation.');
    } catch (ValidationException) {
    }
    $centerResponse = (new BusinessController($service))->index(Request::create('/api/v1/businesses', 'GET',
        ['regionId' => (string) $centerDong, 'keyword' => $prefix . '-center-city-', 'sort' => 'distance', 'radiusKm' => '0.5']));
    $centerJson = json_decode($centerResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertRegionFallback($centerResponse->getStatusCode() === 200 && $centerJson['meta']['total'] === 1
        && $centerJson['data'][0]['id'] === $centerCityNear
        && $centerJson['meta']['distanceOrigin']['regionId'] === $centerDong,
        'Region-only API requests must support radius and distance sort using the original region center.');
    $db->rollBack();
    echo "Business region fallback tests passed (dong, district, city, filters, distance, pagination, read-only; fixtures rolled back).\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
