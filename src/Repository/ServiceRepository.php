<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ServiceRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActive(?int $categoryId, ?int $limit): array
    {
        $join = '';
        $categoryCondition = '';
        $parameters = ['status' => 'ACTIVE'];

        if ($categoryId !== null) {
            $join = 'INNER JOIN category_services ON category_services.service_id = services.id';
            $categoryCondition = ' AND category_services.category_id = :category_id';
            $parameters['category_id'] = $categoryId;
        }

        $statement = $this->connection->prepare(
            sprintf(
                <<<'SQL'
                    SELECT
                        services.id,
                        services.name,
                        services.slug,
                        services.description,
                        services.sort_order
                    FROM services
                    %s
                    WHERE services.status = :status%s
                    ORDER BY services.sort_order ASC, services.name ASC, services.id ASC
                    %s
                    SQL,
                $join,
                $categoryCondition,
                $limit === null ? '' : 'LIMIT :limit',
            ),
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $statement->execute();

        /** @var list<array<string, mixed>> $services */
        $services = $statement->fetchAll();

        return $services;
    }
}
