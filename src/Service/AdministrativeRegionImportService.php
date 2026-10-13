<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Integration\AdministrativeRegions\AdministrativeRegionSource;
use App\Repository\AdministrativeRegionImportStore;
use InvalidArgumentException;
use RuntimeException;

final class AdministrativeRegionImportService
{
    public function __construct(
        private readonly AdministrativeRegionSource $source,
        private readonly AdministrativeRegionImportStore $store,
        private readonly TransactionManager $transactions,
        private readonly AdministrativeRegionRecordNormalizer $normalizer,
        private readonly string $snapshotKey,
        private readonly string $version,
        private readonly string $path,
        private readonly int $pageSize = 1000,
        private readonly int $intervalMilliseconds = 100,
    ) {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,100}\z/', $snapshotKey) !== 1
            || preg_match('/\A[a-zA-Z0-9_-]{1,30}\z/', $version) !== 1
            || preg_match('/\A\/15136373\/v1\/uddi:[a-f0-9-]{36}\z/', $path) !== 1
            || $pageSize < 1 || $pageSize > 1000 || $intervalMilliseconds < 0 || $intervalMilliseconds > 5000) {
            throw new InvalidArgumentException('Administrative region import configuration is invalid.');
        }
    }

    /** @param null|callable(int, int, int): void $progress @return array<string, mixed> */
    public function collect(?callable $progress = null): array
    {
        $snapshot = $this->store->findSnapshot($this->snapshotKey);
        if ($snapshot !== null) {
            $this->validateSnapshot($snapshot);
            if ($snapshot['status'] === 'COMPLETE') {
                return $this->status();
            }
        }
        $first = $this->source->fetchPage(1);
        $firstRecords = $this->normalize($first['items']);
        $firstHash = $this->normalizer->pageHash($firstRecords);
        $totalPages = max(1, (int) ceil($first['totalCount'] / $this->pageSize));
        if ($totalPages > 10000) {
            throw new RuntimeException('Administrative region collection exceeds the 10,000-page safety limit.');
        }
        if ($snapshot === null) {
            $this->transactions->run(fn () => $this->store->createSnapshot(
                $this->snapshotKey, $this->version, $this->path, $this->pageSize,
                $first['totalCount'], $firstHash, gmdate('Y-m-d H:i:s'),
            ));
            $snapshot = $this->store->findSnapshot($this->snapshotKey);
        }
        if ($snapshot === null || (int) $snapshot['total_count'] !== $first['totalCount']
            || !hash_equals($snapshot['first_page_hash'], $firstHash)) {
            throw new RuntimeException('Administrative region source changed; use a new snapshot key, preserving previous rows.');
        }
        $importId = (int) $snapshot['id'];
        $lastPage = (int) $snapshot['last_completed_page'];
        if ($lastPage > 0) {
            $checkpoint = $lastPage === 1 ? $first : $this->source->fetchPage($lastPage);
            if ($checkpoint['totalCount'] !== $first['totalCount']
                || !hash_equals($snapshot['last_page_hash'], $this->normalizer->pageHash($this->normalize($checkpoint['items'])))) {
                throw new RuntimeException('Administrative region checkpoint content changed; collection stopped.');
            }
        }
        for ($page = $lastPage + 1; $page <= $totalPages; ++$page) {
            $response = $page === 1 ? $first : $this->source->fetchPage($page);
            if ($response['totalCount'] !== (int) $snapshot['total_count']) {
                throw new RuntimeException('Administrative region total changed during collection; collection stopped.');
            }
            $records = $page === 1 ? $firstRecords : $this->normalize($response['items']);
            $expected = min($this->pageSize, max(0, $first['totalCount'] - ($page - 1) * $this->pageSize));
            if (count($records) !== $expected) {
                throw new RuntimeException('Administrative region page is incomplete; checkpoint was not advanced.');
            }
            $hash = $this->normalizer->pageHash($records);
            $this->transactions->run(fn () => $this->store->savePage($importId, $page, $this->pageSize, $records, $hash, gmdate('Y-m-d H:i:s')));
            if ($progress !== null) {
                $progress($page, $totalPages, count($records));
            }
            if ($page < $totalPages && $this->intervalMilliseconds > 0) {
                usleep($this->intervalMilliseconds * 1000);
            }
        }
        // Detect endpoint drift before declaring the snapshot complete.
        $verification = $this->source->fetchPage(1);
        if ($verification['totalCount'] !== $first['totalCount']
            || !hash_equals($firstHash, $this->normalizer->pageHash($this->normalize($verification['items'])))) {
            throw new RuntimeException('Administrative region first page changed during collection; snapshot remains incomplete.');
        }
        $this->transactions->run(fn () => $this->store->completeSnapshot($importId, gmdate('Y-m-d H:i:s')));
        return $this->status();
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $snapshot = $this->store->findSnapshot($this->snapshotKey);
        if ($snapshot === null) {
            return ['snapshotKey' => $this->snapshotKey, 'status' => 'NOT_STARTED'];
        }
        return ['snapshot' => $snapshot, 'summary' => $this->store->summary((int) $snapshot['id'])];
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, string|null>> */
    private function normalize(array $items): array
    {
        return array_map($this->normalizer->normalize(...), $items);
    }

    /** @param array<string, mixed> $snapshot */
    private function validateSnapshot(array $snapshot): void
    {
        if ($snapshot['dataset_version'] !== $this->version || $snapshot['endpoint_path'] !== $this->path
            || (int) $snapshot['page_size'] !== $this->pageSize
            || !in_array($snapshot['status'], ['COLLECTING', 'COMPLETE'], true)) {
            throw new RuntimeException('Existing snapshot configuration differs; use a new snapshot key.');
        }
    }
}
