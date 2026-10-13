<?php

declare(strict_types=1);

namespace App\Repository;

interface AdministrativeRegionImportStore
{
    /** @return array<string, mixed>|null */
    public function findSnapshot(string $snapshotKey): ?array;

    public function createSnapshot(string $snapshotKey, string $version, string $path, int $pageSize, int $totalCount, string $firstPageHash, string $now): int;

    /** @param list<array<string, string|null>> $records */
    public function savePage(int $importId, int $page, int $pageSize, array $records, string $pageHash, string $now): void;

    public function completeSnapshot(int $importId, string $now): void;

    /** @return array<string, mixed> */
    public function summary(int $importId): array;
}
