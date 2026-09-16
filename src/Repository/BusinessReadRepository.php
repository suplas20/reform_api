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
     * @return array{
     *     primaryLocation: array<string, mixed>|null,
     *     hours: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     offerings: list<array<string, mixed>>,
     *     brands: list<array<string, mixed>>,
     *     links: list<array<string, mixed>>,
     *     verificationTypes: list<string>
     * }
     */
    public function findPublicDetailRelations(int $businessId): array;

    /**
     * @param list<int> $businessIds
     * @return list<array<string, mixed>>
     */
    public function findActiveExternalReputationByBusinessIds(array $businessIds): array;
}
