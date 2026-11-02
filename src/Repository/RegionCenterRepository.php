<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class RegionCenterRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function regions(): array
    {
        return $this->connection->query(
            'SELECT r.id, r.code, r.name, r.level, r.status, r.center_latitude, r.center_longitude,
                    r.center_source, r.center_source_code, p.name AS parent_name, g.name AS ancestor_name
             FROM regions r
             LEFT JOIN regions p ON p.id = r.parent_id
             LEFT JOIN regions g ON g.id = p.parent_id
             ORDER BY r.id',
        )->fetchAll();
    }

    /** @param array<string, mixed> $center */
    public function update(int $id, array $center, string $source, string $now): void
    {
        $statement = $this->connection->prepare(
            'UPDATE regions
             SET center_latitude = :lat, center_longitude = :lng, center_source = :source,
                 center_source_code = :source_code, center_updated_at = :updated
             WHERE id = :id',
        );
        $statement->execute(['lat' => $center['latitude'], 'lng' => $center['longitude'], 'source' => $source,
            'source_code' => $center['source_code'], 'updated' => $now, 'id' => $id]);
    }
}
