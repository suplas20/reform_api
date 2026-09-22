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
                    regions.sort_order
                FROM regions
                WHERE %s
                ORDER BY regions.sort_order ASC, regions.name ASC, regions.id ASC
                SQL,
            implode(' AND ', $conditions),
        ));
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $regions */
        $regions = $statement->fetchAll();

        return $regions;
    }
}
