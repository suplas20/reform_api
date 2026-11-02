<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repository\AdministrativeRegionImportRepository;
use App\Repository\AdministrativeRegionSyncRepository;
use App\Repository\RegionRepository;
use App\Service\AdministrativeRegionRecordNormalizer;
use App\Service\AdministrativeRegionSyncPlanner;

require dirname(__DIR__) . '/vendor/autoload.php';

final class RegionSyncFixtureRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function createRoot(string $name, string $now): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO regions (code, name, slug, level, sort_order, status, created_at, updated_at)
             VALUES (:code, :name, :slug, 1, 0, :status, :created_at, :updated_at)',
        );
        $statement->execute(['code' => 'TEST-' . $name, 'name' => $name, 'slug' => $name,
            'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
        return (int) $this->connection->lastInsertId();
    }

    public function linkedRows(int $id): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM administrative_region_records WHERE import_id = :id AND region_id IS NOT NULL');
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn();
    }

    public function locationHash(): string
    {
        return hash('sha256', json_encode($this->connection->query(
            'SELECT id, business_id, region_id FROM business_locations ORDER BY id',
        )->fetchAll(), JSON_THROW_ON_ERROR));
    }
}

$connection = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$fixtures = new RegionSyncFixtureRepository($connection);
$before = $fixtures->locationHash();
$connection->beginTransaction();
try {
    $now = gmdate('Y-m-d H:i:s');
    $name = 'sync-test-' . bin2hex(random_bytes(6));
    $rootId = $fixtures->createRoot($name, $now);
    $normalizer = new AdministrativeRegionRecordNormalizer();
    $rows = [];
    foreach ([['98', $name, null], ['98010', '검증시', '98010'], ['98010510', "검증'동", '98010'], ['98010510', "검증'동", '98010']] as [$code, $label, $parent]) {
        $rows[] = $normalizer->normalize(['행정동번호' => '1', '개정일자' => '2025-04-01', '연결번호' => '1',
            '행정동코드' => $code, '행정동명' => $label, '배경여부' => 'N',
            '최상위행정동코드' => '98', '부모행정동코드' => $parent, '순번' => '1']);
    }
    $imports = new AdministrativeRegionImportRepository($connection);
    $hash = $normalizer->pageHash($rows);
    $id = $imports->createSnapshot($name, 'test', '/15136373/v1/uddi:84f86747-7012-4d74-b4c6-9d5068312ac4', 4, 4, $hash, $now);
    $imports->savePage($id, 1, 4, $rows, $hash, $now);
    $imports->completeSnapshot($id, $now);
    $repository = new AdministrativeRegionSyncRepository($connection);
    $snapshot = $repository->snapshot($name);
    $source = $repository->source($id, $snapshot['revision']);
    $rules = ['root_targets' => [], 'district_aliases' => [], 'replaced_districts' => []];
    $planner = new AdministrativeRegionSyncPlanner();
    $plan = $planner->plan($source, $repository->regions(), [], $rules);
    if ($plan['createdDistricts'] !== 1 || $plan['createdDongs'] !== 1) {
        throw new RuntimeException('Fixture source must create exactly one district and one dong.');
    }
    $ids = [];
    foreach ($plan['create'] as $region) {
        $parentId = str_starts_with($region['parent'], 'id:') ? (int) substr($region['parent'], 3) : $ids[$region['parent']];
        $ids[$region['code']] = $repository->insert($region, $parentId, $now);
    }
    $mapping = [];
    foreach ($plan['mapping'] as $code => $ref) {
        $mapping[$code] = str_starts_with($ref, 'id:') ? (int) substr($ref, 3) : $ids[$ref];
    }
    $repository->linkSource($id, $snapshot['revision'], $mapping);
    $repository->linkSource($id, $snapshot['revision'], $mapping);
    $dongs = (new RegionRepository($connection))->findActive($ids['KOSTAT-98010'], 3, null);
    if (count($dongs) !== 1 || $dongs[0]['name'] !== "검증'동" || $fixtures->linkedRows($id) !== 4
        || $planner->plan($source, $repository->regions(), [], $rules)['created'] !== 0
        || $fixtures->locationHash() !== $before) {
        throw new RuntimeException('Hierarchy, source duplicate links, idempotence or business location preservation failed.');
    }
    $connection->rollBack();
    if ($imports->findSnapshot($name) !== null || (new RegionRepository($connection))->findActive($rootId, null, null) !== []) {
        throw new RuntimeException('Rollback must remove synchronized hierarchy and source links.');
    }
    echo "Administrative region sync repository tests passed (fixtures rolled back).\n";
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
