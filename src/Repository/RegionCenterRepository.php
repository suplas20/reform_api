<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use InvalidArgumentException;

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

    /** @param list<int> $verifiedRegionIds */
    public function clearUnreferencedCenters(array $verifiedRegionIds): int
    {
        if ($verifiedRegionIds === [] || count($verifiedRegionIds) > 10000) {
            throw new InvalidArgumentException('A nonempty verified office scope is required.');
        }
        $parameters = $placeholders = [];
        foreach (array_values(array_unique($verifiedRegionIds)) as $index => $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Verified office region IDs must be positive integers.');
            }
            $parameter = 'office_region_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $id;
        }
        $statement = $this->connection->prepare(
            'UPDATE regions
             SET center_latitude = NULL, center_longitude = NULL, center_source = NULL,
                 center_source_code = NULL, center_updated_at = NULL
             WHERE id NOT IN (' . implode(', ', $placeholders) . ')
               AND (center_latitude IS NOT NULL OR center_longitude IS NOT NULL OR center_source IS NOT NULL)',
        );
        $statement->execute($parameters);

        return $statement->rowCount();
    }
}
