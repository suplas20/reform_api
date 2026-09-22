<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class WorkCaseRepository implements WorkCaseReadRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countPublic(string $publishedAt, array $criteria): int
    {
        [$conditions, $parameters] = $this->publicConditions($criteria);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT COUNT(*)
                FROM work_cases
                INNER JOIN businesses ON businesses.id = work_cases.business_id
                WHERE work_cases.status = :work_case_status
                  AND work_cases.deleted_at IS NULL
                  AND work_cases.published_at IS NOT NULL
                  AND work_cases.published_at <= :work_case_published_at
                  AND businesses.status = :business_status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :business_published_at
                  %s
                SQL,
            implode("\n", $conditions),
        ));
        $statement->execute([
            'work_case_status' => 'PUBLISHED',
            'business_status' => 'ACTIVE',
            'work_case_published_at' => $publishedAt,
            'business_published_at' => $publishedAt,
            ...$parameters,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
        array $criteria,
    ): array {
        [$conditions, $parameters] = $this->publicConditions($criteria);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    work_cases.id,
                    work_cases.business_id,
                    work_cases.title,
                    work_cases.slug,
                    work_cases.description,
                    work_cases.published_at,
                    categories.id AS category_id,
                    categories.name AS category_name,
                    brands.id AS brand_id,
                    brands.name AS brand_name
                FROM work_cases
                INNER JOIN businesses ON businesses.id = work_cases.business_id
                LEFT JOIN categories ON categories.id = work_cases.category_id
                LEFT JOIN brands ON brands.id = work_cases.brand_id
                WHERE work_cases.status = :work_case_status
                  AND work_cases.deleted_at IS NULL
                  AND work_cases.published_at IS NOT NULL
                  AND work_cases.published_at <= :work_case_published_at
                  AND businesses.status = :business_status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :business_published_at
                  %s
                ORDER BY work_cases.published_at DESC, work_cases.id DESC
                LIMIT :limit OFFSET :offset
                SQL,
            implode("\n", $conditions),
        ));
        $statement->bindValue(':work_case_status', 'PUBLISHED');
        $statement->bindValue(':business_status', 'ACTIVE');
        $statement->bindValue(':work_case_published_at', $publishedAt);
        $statement->bindValue(':business_published_at', $publishedAt);

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    public function findPublicById(int $id, string $publishedAt): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    work_cases.id,
                    work_cases.business_id,
                    work_cases.title,
                    work_cases.slug,
                    work_cases.description,
                    work_cases.published_at,
                    businesses.name AS business_name,
                    categories.id AS category_id,
                    categories.name AS category_name,
                    brands.id AS brand_id,
                    brands.name AS brand_name
                FROM work_cases
                INNER JOIN businesses ON businesses.id = work_cases.business_id
                LEFT JOIN categories ON categories.id = work_cases.category_id
                LEFT JOIN brands ON brands.id = work_cases.brand_id
                WHERE work_cases.id = :id
                  AND work_cases.status = :work_case_status
                  AND work_cases.deleted_at IS NULL
                  AND work_cases.published_at IS NOT NULL
                  AND work_cases.published_at <= :work_case_published_at
                  AND businesses.status = :business_status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :business_published_at
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'id' => $id,
            'work_case_status' => 'PUBLISHED',
            'business_status' => 'ACTIVE',
            'work_case_published_at' => $publishedAt,
            'business_published_at' => $publishedAt,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function findActiveServicesByWorkCaseIds(array $workCaseIds): array
    {
        $workCaseIds = array_values(array_unique($workCaseIds));

        if ($workCaseIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = ['status' => 'ACTIVE'];

        foreach ($workCaseIds as $index => $workCaseId) {
            $parameter = 'work_case_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $workCaseId;
        }

        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT work_case_services.work_case_id, services.id, services.name
                FROM work_case_services
                INNER JOIN services ON services.id = work_case_services.service_id
                WHERE work_case_services.work_case_id IN (%s)
                  AND services.status = :status
                ORDER BY work_case_services.work_case_id ASC, services.name ASC, services.id ASC
                SQL,
            implode(', ', $placeholders),
        ));
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @param array<string, int|null> $criteria
     * @return array{list<string>, array<string, int>}
     */
    private function publicConditions(array $criteria): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['businessId' => 'business_id', 'categoryId' => 'category_id', 'brandId' => 'brand_id'] as $key => $column) {
            if (($criteria[$key] ?? null) === null) {
                continue;
            }

            $conditions[] = sprintf('AND work_cases.%s = :%s', $column, $column);
            $parameters[$column] = $criteria[$key];
        }

        if (($criteria['serviceId'] ?? null) !== null) {
            $conditions[] = <<<'SQL'
                AND EXISTS (
                    SELECT 1
                    FROM work_case_services
                    WHERE work_case_services.work_case_id = work_cases.id
                      AND work_case_services.service_id = :service_id
                )
                SQL;
            $parameters['service_id'] = $criteria['serviceId'];
        }

        return [$conditions, $parameters];
    }
}
