<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BrandRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countActive(?string $keyword): int
    {
        [$condition, $parameters] = $this->keywordCondition($keyword);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT COUNT(*)
                FROM brands
                WHERE brands.status = :status%s
                SQL,
            $condition,
        ));
        $statement->execute([
            'status' => 'ACTIVE',
            ...$parameters,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActivePage(
        ?string $keyword,
        int $limit,
        int $offset,
    ): array {
        [$condition, $parameters] = $this->keywordCondition($keyword);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    brands.id,
                    brands.name,
                    brands.slug
                FROM brands
                WHERE brands.status = :status%s
                ORDER BY brands.name ASC, brands.id ASC
                LIMIT :limit OFFSET :offset
                SQL,
            $condition,
        ));
        $statement->bindValue(':status', 'ACTIVE');

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $brands */
        $brands = $statement->fetchAll();

        return $brands;
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function keywordCondition(?string $keyword): array
    {
        if ($keyword === null) {
            return ['', []];
        }

        $escapedKeyword = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $keyword,
        );

        return [
            ' AND brands.name LIKE :keyword',
            ['keyword' => '%' . $escapedKeyword . '%'],
        ];
    }
}
