<?php

declare(strict_types=1);

namespace App\Service;

interface ReferenceDataProvider
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getCategories(?int $parentId, ?int $level): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getServices(?int $categoryId, ?int $limit): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getRegions(?int $parentId, ?int $level): array;

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     totalPages: int
     * }
     */
    public function getBrands(?string $keyword, int $page, int $perPage): array;
}
