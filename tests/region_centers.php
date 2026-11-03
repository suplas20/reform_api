<?php

declare(strict_types=1);

use App\Controller\Api\V1\ReferenceController;
use App\Database\ConnectionFactory;
use App\Database\TransactionManager;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\RegionCenterRepository;
use App\Repository\RegionRepository;
use App\Repository\ServiceRepository;
use App\Service\ReferenceService;
use App\Service\RegionCenterImportService;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertCenter(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invalidCenter(callable $operation): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new LogicException('Invalid center reference must be rejected.');
}

final class CenterFixtureRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function region(?int $parent, string $code, string $name, int $level): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO regions (parent_id, code, name, slug, level, status, created_at, updated_at)
             VALUES (:parent, :code, :name, :slug, :level, :status, :created, :updated)',
        );
        $stmt->execute(['parent' => $parent, 'code' => $code, 'name' => $name, 'slug' => 'center-' . bin2hex(random_bytes(6)),
            'level' => $level, 'status' => 'ACTIVE', 'created' => gmdate('Y-m-d H:i:s'), 'updated' => gmdate('Y-m-d H:i:s')]);
        return (int) $this->db->lastInsertId();
    }
}

$db = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$db->beginTransaction();
try {
    $fixture = new CenterFixtureRepository($db);
    $province = '좌표테스트-' . bin2hex(random_bytes(6));
    $root = $fixture->region(null, 'KR-99', $province, 1);
    $district = $fixture->region($root, 'KOSTAT-99010', '테스트시 장안구', 2);
    $dong = $fixture->region($district, 'KOSTAT-99010510', '홍제1동', 3);
    $otherDistrict = $fixture->region($root, 'KOSTAT-99020', '다른구', 2);
    $otherDong = $fixture->region($otherDistrict, 'KOSTAT-99020510', '홍제1동', 3);
    $ids = [$root, $district, $dong, $otherDistrict, $otherDong];
    $repository = new RegionCenterRepository($db);
    $service = new RegionCenterImportService($repository, new TransactionManager($db));
    $rows = static fn (): array => array_values(array_filter($repository->regions(), static fn (array $r): bool => in_array((int) $r['id'], $ids, true)));
    $source = [
        ['province' => $province, 'district' => '테스트시장안구', 'dong' => '홍제제1동',
            'office_name' => '홍제제1동 주민센터', 'road_address' => '테스트로 1',
            'latitude' => '37.5', 'longitude' => '127.03', 'source' => 'OFFICE-TEST-20261103',
            'source_url' => 'https://www.ddm.go.kr/www/contents.do?key=293', 'verified_at' => '2026-11-03'],
        ['province' => $province, 'district' => '다른구', 'dong' => '홍제제1동',
            'office_name' => '홍제제1동 행정복지센터', 'road_address' => '테스트로 2',
            'latitude' => '37.4', 'longitude' => '127.04', 'source' => 'OFFICE-TEST-20261103',
            'source_url' => 'https://www.ddm.go.kr/www/contents.do?key=293', 'verified_at' => '2026-11-03'],
    ];
    $plan = $service->plan($source, $rows());
    assertCenter($plan['matched'] === 2 && count($plan['updates']) === 2 && $plan['unmatched'] === [],
        'Office names must match full province/district/town hierarchy, including ordinal and spacing variants.');
    foreach ($plan['updates'] as $update) {
        $repository->update($update['id'], $update['center'], $update['center']['source'], gmdate('Y-m-d H:i:s'));
    }
    $repeat = $service->plan($source, $rows());
    assertCenter($repeat['matched'] === 2 && $repeat['updates'] === [], 'Repeated office imports must be idempotent.');
    $controller = new ReferenceController(new ReferenceService(new CategoryRepository($db), new ServiceRepository($db), new RegionRepository($db), new BrandRepository($db)));
    $response = $controller->regions(Request::create('/api/v1/regions', 'GET', ['parentId' => (string) $district]));
    $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertCenter($json['data'][0]['id'] === $dong && $json['data'][0]['latitude'] === 37.5
        && $json['data'][0]['longitude'] === 127.03 && $json['data'][0]['centerSource'] === 'OFFICE-TEST-20261103',
        'Region API must expose only actual community office coordinates and provenance.');
    $ambiguous = $service->plan([...$source, $source[0]], $rows());
    assertCenter($ambiguous['matched'] === 1 && count($ambiguous['unmatched']) === 1
        && $ambiguous['unmatched'][0]['id'] === $dong && $ambiguous['unmatched'][0]['reason'] === 'AMBIGUOUS_OFFICE',
        'Conflicting office references must never be guessed.');
    $missing = $service->plan([$source[0]], $rows());
    assertCenter($missing['matched'] === 1 && $missing['unmatched'][0]['id'] === $otherDong,
        'A town in a different district must never inherit another town office.');
    foreach ([['latitude' => '91'], ['longitude' => 'NaN'], ['source' => 'FORECAST'],
        ['source_url' => 'https://example.com/office'], ['verified_at' => '2026-02-30'],
        ['office_name' => '다른동 주민센터'], ['office_name' => '홍제1동 아파트'], ['road_address' => '']] as $invalid) {
        invalidCenter(fn () => $service->plan([[...$source[0], ...$invalid]], $rows()));
    }
    $repository->update($dong, ['latitude' => 37.6, 'longitude' => 127.06, 'source_code' => 'old'],
        'KMA-LEGACY', gmdate('Y-m-d H:i:s'));
    $legacy = $controller->regions(Request::create('/api/v1/regions', 'GET', ['parentId' => (string) $district]));
    $legacyJson = json_decode($legacy->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertCenter($legacyJson['data'][0]['latitude'] === null && $legacyJson['data'][0]['longitude'] === null
        && $legacyJson['data'][0]['centerSource'] === null, 'Forecast coordinates must not be exposed as office positions.');
    try {
        $repository->clearUnreferencedCenters([]);
        throw new LogicException('An empty import scope must never clear all centers.');
    } catch (InvalidArgumentException) {
    }
    $repository->clearUnreferencedCenters([$otherDong]);
    $cleared = array_column($rows(), null, 'id');
    assertCenter($cleared[$dong]['center_latitude'] === null && $cleared[$dong]['center_source'] === null
        && $cleared[$otherDong]['center_source'] === 'OFFICE-TEST-20261103',
        'Retiring legacy coordinates must preserve verified offices.');
    $config = require dirname(__DIR__) . '/config/region_centers.php';
    invalidCenter(fn () => $service->run($config['path'], str_repeat('0', 64), $config['source'], false));
    assertCenter(hash_file('sha256', $config['path']) === $config['sha256'], 'Committed office reference must match its checksum.');
    $db->rollBack();
    echo "Region center import and API tests passed (community offices only, scoped matching, validation, idempotence; fixtures rolled back).\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
