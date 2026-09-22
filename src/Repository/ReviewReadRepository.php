<?php

declare(strict_types=1);

namespace App\Repository;

interface ReviewReadRepository
{
    public function publicBusinessExists(int $businessId, string $publishedAt): bool;

    /** @param array<string, mixed> $criteria */
    public function countPublicByBusinessId(int $businessId, array $criteria): int;

    /**
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    public function findPublicPageByBusinessId(
        int $businessId,
        int $limit,
        int $offset,
        array $criteria,
    ): array;

    /**
     * @return array{average_rating: mixed, review_count: mixed, rating_1: mixed, rating_2: mixed, rating_3: mixed, rating_4: mixed, rating_5: mixed}
     */
    public function summarizePublicByBusinessId(int $businessId): array;
}
