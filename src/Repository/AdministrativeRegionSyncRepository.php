<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class AdministrativeRegionSyncRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string, mixed> */
    public function snapshot(string $key): array
    {
        $statement = $this->connection->prepare(
            'SELECT i.id, i.status, i.total_count, i.fetched_count,
                    MAX(r.revision_date) AS revision, COUNT(r.source_row_number) AS stored_count
             FROM administrative_region_imports i
             LEFT JOIN administrative_region_records r ON r.import_id = i.id
             WHERE i.snapshot_key = :snapshot_key
             GROUP BY i.id, i.status, i.total_count, i.fetched_count',
        );
        $statement->execute(['snapshot_key' => $key]);
        $snapshot = $statement->fetch();
        if ($snapshot === false || $snapshot['status'] !== 'COMPLETE'
            || (int) $snapshot['stored_count'] !== (int) $snapshot['total_count']
            || (int) $snapshot['fetched_count'] !== (int) $snapshot['total_count']) {
            throw new RuntimeException('A fully collected administrative region snapshot is required.');
        }
        return $snapshot;
    }

    /** @return list<array<string, mixed>> */
    public function source(int $importId, string $revision): array
    {
        $statement = $this->connection->prepare(
            'SELECT administrative_code, name, top_level_code, parent_code
             FROM administrative_region_records
             WHERE import_id = :import_id AND revision_date = :revision
             ORDER BY source_row_number',
        );
        $statement->execute(['import_id' => $importId, 'revision' => $revision]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function regions(): array
    {
        return $this->connection->query(
            'SELECT id, parent_id, code, name, level, status FROM regions ORDER BY id',
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function currentDongs(): array
    {
        return $this->connection->query(
            'SELECT DISTINCT s.district_code, s.administrative_dong_name
             FROM semas_business_imports s
             INNER JOIN business_locations l ON l.business_id = s.business_id
             INNER JOIN regions r ON r.id = l.region_id AND r.code = CONCAT(\'KR-\', s.district_code)
             WHERE s.business_id IS NOT NULL AND s.administrative_dong_name IS NOT NULL
               AND s.administrative_dong_name <> \'\'',
        )->fetchAll();
    }

    /** @param array<string, mixed> $region */
    public function insert(array $region, int $parentId, string $now): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO regions (parent_id, code, name, slug, level, sort_order, status, created_at, updated_at)
             VALUES (:parent_id, :code, :name, :slug, :level, :sort_order, :status, :created_at, :updated_at)',
        );
        $statement->execute([
            'parent_id' => $parentId, 'code' => $region['code'], 'name' => $region['name'],
            'slug' => strtolower($region['code']), 'level' => $region['level'], 'sort_order' => 0,
            'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int> $mapping */
    public function linkSource(int $importId, string $revision, array $mapping): void
    {
        $statement = $this->connection->prepare(
            'UPDATE administrative_region_records SET region_id = :region_id
             WHERE import_id = :import_id AND administrative_code = :code AND revision_date = :revision
               AND (region_id IS NULL OR region_id <> :expected_id)',
        );
        foreach ($mapping as $code => $id) {
            $statement->execute(['region_id' => $id, 'expected_id' => $id, 'import_id' => $importId,
                'code' => (string) $code, 'revision' => $revision]);
        }
    }
}
