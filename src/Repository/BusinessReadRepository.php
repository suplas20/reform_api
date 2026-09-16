<?php

declare(strict_types=1);

namespace App\Repository;

interface BusinessReadRepository
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function countPublic(string $publishedAt, array $criteria = []): int;

    /**
     * @return list<array<string, mixed>>
     * @param array<string, mixed> $criteria
     */
    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
        array $criteria = [],
    ): array;

    public function activeCategoryServiceCombinationExists(int $categoryId, int $serviceId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublicDetailById(int $id, string $publishedAt): ?array;

    /**
     * @param list<int> $businessIds
     * @return list<array<string, mixed>>
     */
    public function findActiveExternalReputationByBusinessIds(array $businessIds): array;
}
