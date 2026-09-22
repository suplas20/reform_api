<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\RegionRepository;
use App\Repository\ServiceRepository;

final class ReferenceService implements ReferenceDataProvider
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly RegionRepository $regionRepository,
        private readonly BrandRepository $brandRepository,
    ) {
    }

    public function getCategories(?int $parentId, ?int $level): array
    {
        return array_map(
            static fn (array $category): array => [
                'id' => (int) $category['id'],
                'parentId' => self::nullableInt($category['parent_id']),
                'name' => (string) $category['name'],
                'slug' => (string) $category['slug'],
                'description' => self::nullableString($category['description']),
                'sortOrder' => (int) $category['sort_order'],
            ],
            $this->categoryRepository->findActive($parentId, $level),
        );
    }

    public function getServices(?int $categoryId, ?int $limit): array
    {
        if ($categoryId !== null && !$this->categoryRepository->activeExists($categoryId)) {
            throw new NotFoundException(
                'CATEGORY_NOT_FOUND',
                '카테고리를 찾을 수 없습니다.',
            );
        }

        return array_map(
            static fn (array $service): array => [
                'id' => (int) $service['id'],
                'categoryId' => $categoryId,
                'name' => (string) $service['name'],
                'slug' => (string) $service['slug'],
                'description' => self::nullableString($service['description']),
                'sortOrder' => (int) $service['sort_order'],
            ],
            $this->serviceRepository->findActive($categoryId, $limit),
        );
    }

    public function getRegions(?int $parentId, ?int $level, ?string $keyword): array
    {
        return array_map(
            static fn (array $region): array => [
                'id' => (int) $region['id'],
                'parentId' => self::nullableInt($region['parent_id']),
                'code' => self::nullableString($region['code']),
                'name' => (string) $region['name'],
                'slug' => (string) $region['slug'],
                'level' => (int) $region['level'],
                'sortOrder' => (int) $region['sort_order'],
            ],
            $this->regionRepository->findActive($parentId, $level, $keyword),
        );
    }

    public function getBrands(?string $keyword, int $page, int $perPage): array
    {
        $total = $this->brandRepository->countActive($keyword);

        return [
            'items' => array_map(
                static fn (array $brand): array => [
                    'id' => (int) $brand['id'],
                    'name' => (string) $brand['name'],
                    'slug' => (string) $brand['slug'],
                ],
                $this->brandRepository->findActivePage(
                    $keyword,
                    $perPage,
                    ($page - 1) * $perPage,
                ),
            ),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
