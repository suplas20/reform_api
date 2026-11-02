<?php

declare(strict_types=1);

use App\Controller\Api\V1\ReferenceController;
use App\Database\ConnectionFactory;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\RegionRepository;
use App\Repository\ServiceRepository;
use App\Service\ReferenceService;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

final class RegionSelectionFixtureRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function insert(?int $parentId, string $name, int $level, string $status = 'ACTIVE'): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO regions (parent_id, name, slug, level, sort_order, status, created_at, updated_at)
             VALUES (:parent_id, :name, :slug, :level, :sort_order, :status, :created_at, :updated_at)',
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute(['parent_id' => $parentId, 'name' => $name, 'slug' => 'test-' . bin2hex(random_bytes(6)),
            'level' => $level, 'sort_order' => 0, 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $this->connection->lastInsertId();
    }
}

function assertRegionSelection(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$connection->beginTransaction();
try {
    $fixtures = new RegionSelectionFixtureRepository($connection);
    $marker = '지역선택-' . bin2hex(random_bytes(6));
    $root = $fixtures->insert(null, $marker, 1);
    $district = $fixtures->insert($root, '선택구', 2);
    $otherDistrict = $fixtures->insert($root, '다른구', 2);
    $emptyDistrict = $fixtures->insert($root, '빈구', 2);
    $dong = $fixtures->insert($district, '같은동', 3);
    $otherDong = $fixtures->insert($otherDistrict, '같은동', 3);
    $fixtures->insert($emptyDistrict, '비활성동', 3, 'INACTIVE');
    $fixtures->insert($district, '비활성동', 3, 'INACTIVE');
    $literal = $fixtures->insert($district, "특수%_!'동", 3);
    $fixtures->insert($district, "특수AB!'동", 3);
    $sejongRoot = $fixtures->insert(null, $marker . '-세종', 1);
    $sejongDistrict = $fixtures->insert($sejongRoot, $marker . '-세종', 2);
    $sejongDong = $fixtures->insert($sejongDistrict, '조치원읍', 3);
    $controller = new ReferenceController(new ReferenceService(
        new CategoryRepository($connection), new ServiceRepository($connection),
        new RegionRepository($connection), new BrandRepository($connection),
    ));
    $get = static function (array $query) use ($controller): array {
        $response = $controller->regions(Request::create('/api/v1/regions', 'GET', $query));
        assertRegionSelection($response->getStatusCode() === 200, 'Region selection response failed.');
        $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        assertRegionSelection($json['success'] === true && !isset($json['meta']), 'Existing list envelope must be preserved.');
        return $json['data'];
    };
    $roots = array_column($get(['level' => '1', 'query' => $marker]), null, 'id');
    assertRegionSelection($roots[$root]['fullName'] === $marker && $roots[$root]['hasChildren'] === true,
        'Root name and active child availability failed.');
    $districts = array_column($get(['parentId' => (string) $root]), null, 'id');
    assertRegionSelection(count($districts) === 3 && $districts[$district]['fullName'] === $marker . ' 선택구'
        && $districts[$emptyDistrict]['hasChildren'] === false, 'Only immediate children and active descendants must be exposed.');
    $dongs = array_column($get(['parentId' => (string) $district, 'level' => '3']), null, 'id');
    assertRegionSelection(count($dongs) === 3 && $dongs[$dong]['hasChildren'] === false
        && $dongs[$dong]['fullName'] === $marker . ' 선택구 같은동', 'Dong selection hierarchy or leaf indicator failed.');
    assertRegionSelection(is_int($dongs[$dong]['id']) && is_int($dongs[$dong]['parentId'])
        && is_bool($dongs[$dong]['hasChildren']), 'Region JSON types must be stable.');
    $matches = array_column($get(['level' => '3', 'query' => '같은동']), null, 'id');
    assertRegionSelection($matches[$dong]['fullName'] !== $matches[$otherDong]['fullName'],
        'Equal dong names must be distinguishable by their full paths.');
    $literalMatches = $get(['parentId' => (string) $district, 'query' => "%_!'동"]);
    assertRegionSelection(count($literalMatches) === 1 && $literalMatches[0]['id'] === $literal,
        'LIKE special characters and quotes must be treated as literal search text.');
    assertRegionSelection($get(['parentId' => (string) $district, 'query' => "' OR 1=1 --"]) === [],
        'SQL-like search input must not alter the query.');
    assertRegionSelection($get(['parentId' => (string) $dong]) === []
        && $get(['parentId' => (string) $root, 'level' => '3']) === [], 'Leaf and mismatched level queries must return empty lists.');
    $sejong = $get(['parentId' => (string) $sejongDistrict]);
    assertRegionSelection($sejong[0]['id'] === $sejongDong && $sejong[0]['fullName'] === $marker . '-세종 조치원읍',
        'Repeated province/district names must not be duplicated in display names.');
    $connection->rollBack();
    echo "Region selection repository tests passed (fixtures rolled back).\n";
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
