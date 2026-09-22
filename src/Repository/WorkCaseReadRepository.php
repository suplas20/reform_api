<?php

declare(strict_types=1);

namespace App\Repository;

interface WorkCaseReadRepository
{
    /**
     * @param array<string, int|null> $criteria
     */
    public function countPublic(string $publishedAt, array $criteria): int;

    /**
     * @param array<string, int|null> $criteria
     * @return list<array<string, mixed>>
     */
    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
        array $criteria,
    ): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublicById(int $id, string $publishedAt): ?array;

    /**
     * @param list<int> $workCaseIds
     * @return list<array<string, mixed>>
     */
    public function findActiveServicesByWorkCaseIds(array $workCaseIds): array;
}
