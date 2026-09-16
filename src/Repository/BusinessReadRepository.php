<?php

declare(strict_types=1);

namespace App\Repository;

interface BusinessReadRepository
{
    public function countPublic(string $publishedAt): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
    ): array;

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
