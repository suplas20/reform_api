<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class AdministrativeRegionImportRepository implements AdministrativeRegionImportStore
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findSnapshot(string $snapshotKey): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, snapshot_key, dataset_version, endpoint_path, page_size, total_count,
                    last_completed_page, fetched_count, first_page_hash, last_page_hash, status
             FROM administrative_region_imports WHERE snapshot_key = :snapshot_key',
        );
        $statement->execute(['snapshot_key' => $snapshotKey]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function createSnapshot(string $snapshotKey, string $version, string $path, int $pageSize, int $totalCount, string $firstPageHash, string $now): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO administrative_region_imports
                (snapshot_key, dataset_version, endpoint_path, page_size, total_count,
                 first_page_hash, status, started_at, updated_at)
             VALUES (:snapshot_key, :dataset_version, :endpoint_path, :page_size, :total_count,
                     :first_page_hash, :status, :started_at, :updated_at)',
        );
        $statement->execute([
            'snapshot_key' => $snapshotKey, 'dataset_version' => $version, 'endpoint_path' => $path,
            'page_size' => $pageSize, 'total_count' => $totalCount, 'first_page_hash' => $firstPageHash,
            'status' => 'COLLECTING', 'started_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    public function savePage(int $importId, int $page, int $pageSize, array $records, string $pageHash, string $now): void
    {
        // Preserve every source row, including equal codes/dates and exact duplicate rows.
        // Identity is its position within this immutable collection snapshot, not its code.
        foreach (array_chunk($records, 100, true) as $chunk) {
            $tuples = [];
            $parameters = [];
            foreach ($chunk as $index => $record) {
                $values = [
                    'import_id' => $importId, 'source_row_number' => ($page - 1) * $pageSize + $index + 1,
                    ...$record, 'collected_at' => $now,
                ];
                $placeholders = [];
                // Identifiers are exclusively server-defined, never taken from source input.
                foreach (['import_id', 'source_row_number', 'source_record_number', 'revision_text', 'revision_date',
                    'link_number', 'administrative_code', 'name', 'background_flag', 'top_level_code',
                    'parent_code', 'source_sequence', 'record_hash', 'collected_at'] as $column) {
                    $parameter = $column . '_' . $index;
                    $placeholders[] = ':' . $parameter;
                    $parameters[$parameter] = $values[$column];
                }
                $tuples[] = '(' . implode(', ', $placeholders) . ')';
            }
            $statement = $this->connection->prepare(
                'INSERT INTO administrative_region_records
                    (import_id, source_row_number, source_record_number, revision_text, revision_date,
                     link_number, administrative_code, name, background_flag, top_level_code,
                     parent_code, source_sequence, record_hash, collected_at)
                 VALUES ' . implode(', ', $tuples),
            );
            $statement->execute($parameters);
        }

        $statement = $this->connection->prepare(
            'UPDATE administrative_region_imports
             SET last_completed_page = :page, fetched_count = fetched_count + :row_count,
                 last_page_hash = :page_hash, updated_at = :updated_at
             WHERE id = :id AND last_completed_page = :previous_page AND status = :status',
        );
        $statement->execute([
            'page' => $page, 'row_count' => count($records), 'page_hash' => $pageHash,
            'updated_at' => $now, 'id' => $importId, 'previous_page' => $page - 1, 'status' => 'COLLECTING',
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Administrative region checkpoint changed; page transaction must be rolled back.');
        }
    }

    public function completeSnapshot(int $importId, string $now): void
    {
        $statement = $this->connection->prepare(
            'SELECT i.total_count, i.fetched_count, COUNT(r.source_row_number) AS stored_count
             FROM administrative_region_imports i
             LEFT JOIN administrative_region_records r ON r.import_id = i.id
             WHERE i.id = :id GROUP BY i.id, i.total_count, i.fetched_count',
        );
        $statement->execute(['id' => $importId]);
        $row = $statement->fetch();
        if ($row === false || (int) $row['total_count'] !== (int) $row['stored_count']
            || (int) $row['fetched_count'] !== (int) $row['stored_count']) {
            throw new RuntimeException('Administrative region stored count does not match API total; snapshot remains incomplete.');
        }
        $statement = $this->connection->prepare(
            'UPDATE administrative_region_imports SET status = :status, completed_at = :completed_at,
             updated_at = :updated_at WHERE id = :id AND status = :collecting',
        );
        $statement->execute([
            'status' => 'COMPLETE', 'completed_at' => $now, 'updated_at' => $now,
            'id' => $importId, 'collecting' => 'COLLECTING',
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Administrative region completion state changed.');
        }
    }

    public function summary(int $importId): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS stored_count, COUNT(DISTINCT administrative_code) AS distinct_codes,
                    MIN(revision_date) AS earliest_revision, MAX(revision_date) AS latest_revision,
                    SUM(revision_date IS NULL) AS unparsed_dates, SUM(region_id IS NOT NULL) AS linked_rows
             FROM administrative_region_records WHERE import_id = :id',
        );
        $statement->execute(['id' => $importId]);
        $summary = $statement->fetch();
        $statement = $this->connection->prepare(
            'SELECT revision_text, COUNT(*) AS row_count, COUNT(DISTINCT administrative_code) AS distinct_codes
             FROM administrative_region_records WHERE import_id = :id
             GROUP BY revision_text ORDER BY revision_text DESC LIMIT 200',
        );
        $statement->execute(['id' => $importId]);
        $summary['revisions'] = $statement->fetchAll();
        $statement = $this->connection->prepare(
            'SELECT background_flag, COUNT(*) AS row_count FROM administrative_region_records
             WHERE import_id = :id GROUP BY background_flag ORDER BY background_flag',
        );
        $statement->execute(['id' => $importId]);
        $summary['background_flags'] = $statement->fetchAll();
        return $summary;
    }
}
