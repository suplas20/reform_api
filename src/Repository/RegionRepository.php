<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class RegionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActive(?int $parentId, ?int $level, ?string $keyword): array
    {
        $conditions = ['regions.status = :status'];
        $parameters = ['status' => 'ACTIVE'];

        if ($parentId !== null) {
            $conditions[] = 'regions.parent_id = :parent_id';
            $parameters['parent_id'] = $parentId;
        }

        if ($level !== null) {
            $conditions[] = 'regions.level = :level';
            $parameters['level'] = $level;
        }

        if ($keyword !== null) {
            $conditions[] = "regions.name LIKE :keyword ESCAPE '!'";
            $parameters['keyword'] = SqlLikePattern::contains($keyword);
        }

        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    regions.id,
                    regions.parent_id,
                    regions.code,
                    regions.name,
                    regions.slug,
                    regions.level,
                    regions.sort_order,
                    regions.center_latitude,
                    regions.center_longitude,
                    regions.center_source,
                    parent_region.name AS parent_name,
                    ancestor_region.name AS ancestor_name,
                    EXISTS (
                        SELECT 1
                        FROM regions AS child_region
                        WHERE child_region.parent_id = regions.id
                          AND child_region.status = :child_status
                    ) AS has_children
                FROM regions
                LEFT JOIN regions AS parent_region ON parent_region.id = regions.parent_id
                LEFT JOIN regions AS ancestor_region ON ancestor_region.id = parent_region.parent_id
                WHERE %s
                ORDER BY regions.sort_order ASC, regions.name ASC, regions.id ASC
                SQL,
            implode(' AND ', $conditions),
        ));
        $statement->execute([...$parameters, 'child_status' => 'ACTIVE']);

        /** @var list<array<string, mixed>> $regions */
        $regions = $statement->fetchAll();

        return $regions;
    }
}
