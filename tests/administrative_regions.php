<?php

declare(strict_types=1);

use App\Database\TransactionManager;
use App\Integration\AdministrativeRegions\OdcloudAdministrativeRegionClient;
use App\Repository\AdministrativeRegionImportStore;
use App\Service\AdministrativeRegionImportService;
use App\Service\AdministrativeRegionRecordNormalizer;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertAdministrativeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function administrativeFailure(callable $operation): string
{
    try {
        $operation();
    } catch (RuntimeException|InvalidArgumentException $exception) {
        return $exception->getMessage();
    }
    throw new LogicException('Expected operation to fail.');
}

$path = '/15136373/v1/uddi:84f86747-7012-4d74-b4c6-9d5068312ac4';
$row = [
    '행정동번호' => 800, '개정일자' => '2018-07-23', '연결번호' => 800,
    '행정동코드' => 2113060, '행정동명' => '연산6동', '배경여부' => 'N',
    '최상위행정동코드' => 21, '부모행정동코드' => 21130, '순번' => 801,
];
$normalizer = new AdministrativeRegionRecordNormalizer();
$normalized = $normalizer->normalize($row);
assertAdministrativeSame('2113060', $normalized['administrative_code'], 'Codes must be strings.');
assertAdministrativeSame('2018-07-23', $normalized['revision_date'], 'Valid date must be parsed.');
$reordered = array_reverse($row, true);
assertAdministrativeSame($normalized, $normalizer->normalize($reordered), 'Source key order must not affect content hash.');
$leading = $normalizer->normalize(array_replace($row, ['행정동코드' => '00110001']));
assertAdministrativeSame('00110001', $leading['administrative_code'], 'Leading zeros must survive.');
$badDate = $normalizer->normalize(array_replace($row, ['개정일자' => '2025-02-30']));
assertAdministrativeSame('2025-02-30', $badDate['revision_text'], 'Original dates must survive.');
assertAdministrativeSame(null, $badDate['revision_date'], 'Invalid date must not be guessed.');
$blank = $normalizer->normalize(array_replace($row, ['개정일자' => '', '행정동코드' => null]));
assertAdministrativeSame(null, $blank['administrative_code'], 'Null source values must survive.');
assertAdministrativeSame('', $blank['revision_text'], 'Blank source values must survive.');
$background = $normalizer->normalize(array_replace($row, ['배경여부' => 'Y', '행정동코드' => null, '행정동명' => "청주시\n\n청원구 및 서원구 일대로 이동"]));
assertAdministrativeSame("청주시\n\n청원구 및 서원구 일대로 이동", $background['name'], 'Multiline historical explanations must survive.');
foreach ([['행정동코드' => true], ['행정동코드' => '11 OR 1=1'], ['행정동명' => "동\0"], ['순번' => 1.5]] as $invalid) {
    administrativeFailure(fn () => $normalizer->normalize(array_replace($row, $invalid)));
}
administrativeFailure(fn () => $normalizer->normalize($row + ['unknown' => 'field']));

$requestedPages = [];
$requestedHeaders = [];
$failSecondPage = false;
$changeFirstPage = false;
$secret = 'test-only-service-key';
$client = new OdcloudAdministrativeRegionClient('https://api.odcloud.kr/api', $path, $secret, 2, 30,
    static function (string $url, array $headers) use (&$requestedPages, &$requestedHeaders, &$failSecondPage, &$changeFirstPage, $row, $secret): array {
        assertAdministrativeSame(false, str_contains($url, $secret), 'Service key must never be in the URL.');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = (int) $query['page'];
        $requestedPages[] = $page;
        $requestedHeaders = $headers;
        if ($page === 2 && $failSecondPage) {
            return ['status' => 401, 'body' => $secret];
        }
        $items = $page === 1 ? [$row, array_replace($row, ['행정동번호' => 801, '순번' => 802])] : [$row];
        if ($changeFirstPage && $page === 1) {
            $items[0]['행정동명'] = '변경된 원본';
        }
        return ['status' => 200, 'body' => json_encode([
            'page' => $page, 'perPage' => 2, 'totalCount' => 3, 'matchCount' => 3,
            'currentCount' => count($items), 'data' => $items,
        ], JSON_THROW_ON_ERROR)];
    },
);
$client->fetchPage(1);
assertAdministrativeSame('Authorization: Infuser ' . $secret, $requestedHeaders[0], 'API header authentication is incorrect.');
administrativeFailure(fn () => $client->fetchPage(0));
administrativeFailure(fn () => new OdcloudAdministrativeRegionClient('https://other.example/api', $path, $secret));
foreach ([
    ['status' => 200, 'body' => '{}'],
    ['status' => 200, 'body' => '{malformed'],
    ['status' => 200, 'body' => json_encode(['page' => 1, 'perPage' => 2, 'totalCount' => 3, 'matchCount' => 3, 'currentCount' => 0, 'data' => []])],
    ['status' => 403, 'body' => $secret],
] as $invalidResponse) {
    $invalidClient = new OdcloudAdministrativeRegionClient('https://api.odcloud.kr/api', $path, $secret, 2,
        transport: static fn (): array => $invalidResponse);
    $message = administrativeFailure(fn () => $invalidClient->fetchPage(1));
    assertAdministrativeSame(false, str_contains($message, $secret), 'Provider errors must not expose secrets.');
}

$store = new class implements AdministrativeRegionImportStore {
    public ?array $snapshot = null;
    public array $records = [];
    public function findSnapshot(string $snapshotKey): ?array { return $this->snapshot; }
    public function createSnapshot(string $snapshotKey, string $version, string $path, int $pageSize, int $totalCount, string $firstPageHash, string $now): int
    {
        $this->snapshot = ['id' => 1, 'snapshot_key' => $snapshotKey, 'dataset_version' => $version, 'endpoint_path' => $path,
            'page_size' => $pageSize, 'total_count' => $totalCount, 'first_page_hash' => $firstPageHash,
            'last_page_hash' => null, 'last_completed_page' => 0, 'fetched_count' => 0, 'status' => 'COLLECTING'];
        return 1;
    }
    public function savePage(int $importId, int $page, int $pageSize, array $records, string $pageHash, string $now): void
    {
        $this->records = [...$this->records, ...$records];
        $this->snapshot['last_completed_page'] = $page;
        $this->snapshot['fetched_count'] += count($records);
        $this->snapshot['last_page_hash'] = $pageHash;
    }
    public function completeSnapshot(int $importId, string $now): void
    {
        assertAdministrativeSame(3, count($this->records), 'Completion requires every source row.');
        $this->snapshot['status'] = 'COMPLETE';
    }
    public function summary(int $importId): array { return ['stored_count' => count($this->records)]; }
};
$pdo = new class extends PDO {
    public int $commits = 0;
    private bool $active = false;
    public function __construct() {}
    public function beginTransaction(): bool { $this->active = true; return true; }
    public function commit(): bool { ++$this->commits; $this->active = false; return true; }
    public function rollBack(): bool { $this->active = false; return true; }
    public function inTransaction(): bool { return $this->active; }
};
$service = new AdministrativeRegionImportService($client, $store, new TransactionManager($pdo), $normalizer,
    'test-snapshot', '20250704', $path, 2, 0);
assertAdministrativeSame('NOT_STARTED', $service->status()['status'], 'Initial state must be explicit.');
$failSecondPage = true;
$message = administrativeFailure(fn () => $service->collect());
assertAdministrativeSame(false, str_contains($message, $secret), 'Collection errors must not expose secrets.');
assertAdministrativeSame(1, $store->snapshot['last_completed_page'], 'Failed page must not advance checkpoint.');
assertAdministrativeSame(2, count($store->records), 'First completed page must survive interruption.');
$changeFirstPage = true;
administrativeFailure(fn () => $service->collect());
assertAdministrativeSame(2, count($store->records), 'Changed source must not append rows.');
$changeFirstPage = false;
$failSecondPage = false;
$result = $service->collect();
assertAdministrativeSame('COMPLETE', $result['snapshot']['status'], 'Resumed import must complete.');
assertAdministrativeSame(3, count($store->records), 'Same-code and exact duplicate source rows must be preserved.');
$calls = count($requestedPages);
$service->collect();
assertAdministrativeSame($calls, count($requestedPages), 'Completed import must not call the API again.');
assertAdministrativeSame(3, count($store->records), 'Repeated import must not duplicate rows.');

echo "Administrative region import tests passed.\n";
