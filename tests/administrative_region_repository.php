<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repository\AdministrativeRegionImportRepository;
use App\Service\AdministrativeRegionRecordNormalizer;

require dirname(__DIR__) . '/vendor/autoload.php';

$connection = (new ConnectionFactory(require dirname(__DIR__) . '/config/database.php'))->connection();
$connection->beginTransaction();
try {
    $repository = new AdministrativeRegionImportRepository($connection);
    $normalizer = new AdministrativeRegionRecordNormalizer();
    $record = $normalizer->normalize([
        '행정동번호' => '0001', '개정일자' => '2025-04-01', '연결번호' => '1',
        '행정동코드' => '00110001', '행정동명' => "테스트'동", '배경여부' => 'N',
        '최상위행정동코드' => '00', '부모행정동코드' => '00110', '순번' => '1',
    ]);
    $key = 'test-' . bin2hex(random_bytes(12));
    $hash = $normalizer->pageHash([$record, $record]);
    $now = gmdate('Y-m-d H:i:s');
    $id = $repository->createSnapshot($key, 'test', '/15136373/v1/uddi:84f86747-7012-4d74-b4c6-9d5068312ac4', 2, 2, $hash, $now);
    $repository->savePage($id, 1, 2, [$record, $record], $hash, $now);
    $summary = $repository->summary($id);
    if ((int) $summary['stored_count'] !== 2 || (int) $summary['distinct_codes'] !== 1
        || (int) $summary['linked_rows'] !== 0 || $summary['latest_revision'] !== '2025-04-01') {
        throw new RuntimeException('Repository must preserve duplicate source rows without creating service regions.');
    }
    $snapshot = $repository->findSnapshot($key);
    if ((int) $snapshot['fetched_count'] !== 2 || (int) $snapshot['last_completed_page'] !== 1) {
        throw new RuntimeException('Repository checkpoint does not match saved data.');
    }
    $repository->completeSnapshot($id, $now);
    if ($repository->findSnapshot($key)['status'] !== 'COMPLETE') {
        throw new RuntimeException('Snapshot completion failed.');
    }
    $connection->rollBack();
    if ($repository->findSnapshot($key) !== null) {
        throw new RuntimeException('Rollback must remove both raw rows and collection checkpoint.');
    }
    echo "Administrative region repository tests passed (fixtures rolled back).\n";
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
