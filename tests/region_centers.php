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
        ['source_code' => '9900000000', 'province' => $province, 'district' => '', 'dong' => '', 'latitude' => '38', 'longitude' => '127'],
        ['source_code' => '9911000000', 'province' => $province, 'district' => '테스트시장안구', 'dong' => '', 'latitude' => '37.9', 'longitude' => '127'],
        ['source_code' => '9911051000', 'province' => $province, 'district' => '테스트시장안구', 'dong' => '홍제제1동', 'latitude' => '37.5', 'longitude' => '127.03'],
        ['source_code' => '9912000000', 'province' => $province, 'district' => '다른구', 'dong' => '', 'latitude' => '37.4', 'longitude' => '127.03'],
        ['source_code' => '9912051000', 'province' => $province, 'district' => '다른구', 'dong' => '홍제제1동', 'latitude' => '0', 'longitude' => '0'],
    ];
    $plan = $service->plan($source, $rows(), 'TEST-CENTERS');
    assertCenter($plan['matched'] === 5 && count($plan['updates']) === 5 && $plan['unmatched'] === [],
        'Scoped name matching, native codes, ordinal names and flattened city spacing must resolve.');
    foreach ($plan['updates'] as $update) {
        $repository->update($update['id'], $update['center'], 'TEST-CENTERS', gmdate('Y-m-d H:i:s'));
    }
    $repeat = $service->plan($source, $rows(), 'TEST-CENTERS');
    assertCenter($repeat['matched'] === 5 && $repeat['updates'] === [], 'Repeated center imports must be idempotent.');
    $controller = new ReferenceController(new ReferenceService(new CategoryRepository($db), new ServiceRepository($db), new RegionRepository($db), new BrandRepository($db)));
    $response = $controller->regions(Request::create('/api/v1/regions', 'GET', ['parentId' => (string) $district]));
    $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertCenter($json['data'][0]['id'] === $dong && $json['data'][0]['latitude'] === 37.5
        && $json['data'][0]['longitude'] === 127.03 && $json['data'][0]['centerSource'] === 'TEST-CENTERS',
        'Region API must return typed reference coordinates and provenance.');
    $zero = $controller->regions(Request::create('/api/v1/regions', 'GET', ['parentId' => (string) $otherDistrict]));
    $zeroJson = json_decode($zero->getContent(), true, flags: JSON_THROW_ON_ERROR);
    assertCenter($zeroJson['data'][0]['latitude'] === 0 && $zeroJson['data'][0]['longitude'] === 0, 'Valid zero coordinates must survive JSON mapping.');
    $ambiguous = $source;
    $ambiguous[] = [...$source[2], 'source_code' => '9911052000'];
    $ambiguousPlan = $service->plan($ambiguous, $rows(), 'TEST-CENTERS');
    assertCenter(count($ambiguousPlan['unmatched']) === 1 && $ambiguousPlan['unmatched'][0]['id'] === $dong,
        'Ambiguous dong matches must never receive guessed coordinates.');
    invalidCenter(fn () => $service->plan([...$source, $source[0]], $rows(), 'TEST-CENTERS'));
    foreach (['91', 'NaN', 'INF'] as $badLatitude) {
        $invalid = $source;
        $invalid[2]['latitude'] = $badLatitude;
        invalidCenter(fn () => $service->plan($invalid, $rows(), 'TEST-CENTERS'));
    }
    $config = require dirname(__DIR__) . '/config/region_centers.php';
    invalidCenter(fn () => $service->run($config['path'], str_repeat('0', 64), $config['source'], false));
    assertCenter(hash_file('sha256', $config['path']) === $config['sha256'], 'Committed reference file must match its reviewed checksum.');
    $db->rollBack();
    echo "Region center import and API tests passed (scoped matching, validation, idempotence; fixtures rolled back).\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
