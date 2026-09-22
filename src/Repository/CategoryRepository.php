<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActive(?int $parentId, ?int $level): array
    {
        if ($level !== null) {
            return $this->findActiveByLevel($parentId, $level);
        }

        $conditions = ['categories.status = :status'];
        $parameters = ['status' => 'ACTIVE'];

        if ($parentId === null) {
            $conditions[] = 'categories.parent_id IS NULL';
        } else {
            $conditions[] = 'categories.parent_id = :parent_id';
            $parameters['parent_id'] = $parentId;
        }

        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    categories.id,
                    categories.parent_id,
                    categories.name,
                    categories.slug,
                    categories.description,
                    categories.sort_order
                FROM categories
                WHERE %s
                ORDER BY categories.sort_order ASC, categories.name ASC, categories.id ASC
                SQL,
            implode(' AND ', $conditions),
        ));
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $categories */
        $categories = $statement->fetchAll();

        return $categories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findActiveByLevel(?int $parentId, int $level): array
    {
        $parentCondition = '';
        $parameters = [
            'root_status' => 'ACTIVE',
            'child_status' => 'ACTIVE',
            'level' => $level,
        ];

        if ($parentId !== null) {
            $parentCondition = ' AND category_tree.parent_id = :parent_id';
            $parameters['parent_id'] = $parentId;
        }

        $statement = $this->connection->prepare(
            sprintf(
                <<<'SQL'
                    WITH RECURSIVE category_tree AS (
                        SELECT
                            categories.id,
                            categories.parent_id,
                            categories.name,
                            categories.slug,
                            categories.description,
                            categories.sort_order,
                            1 AS hierarchy_level
                        FROM categories
                        WHERE categories.parent_id IS NULL
                          AND categories.status = :root_status

                        UNION ALL

                        SELECT
                            child.id,
                            child.parent_id,
                            child.name,
                            child.slug,
                            child.description,
                            child.sort_order,
                            category_tree.hierarchy_level + 1
                        FROM categories AS child
                        INNER JOIN category_tree
                            ON category_tree.id = child.parent_id
                        WHERE child.status = :child_status
                    )
                    SELECT
                        category_tree.id,
                        category_tree.parent_id,
                        category_tree.name,
                        category_tree.slug,
                        category_tree.description,
                        category_tree.sort_order
                    FROM category_tree
                    WHERE category_tree.hierarchy_level = :level%s
                    ORDER BY category_tree.sort_order ASC, category_tree.name ASC, category_tree.id ASC
                    SQL,
                $parentCondition,
            ),
        );
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $categories */
        $categories = $statement->fetchAll();

        return $categories;
    }

    public function activeExists(int $id): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT 1
                FROM categories
                WHERE categories.id = :id
                  AND categories.status = :status
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'id' => $id,
            'status' => 'ACTIVE',
        ]);

        return $statement->fetchColumn() !== false;
    }
}
