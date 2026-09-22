<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Repository\WorkCaseReadRepository;
use DateTimeImmutable;
use DateTimeZone;

final class WorkCaseService
{
    public function __construct(private readonly WorkCaseReadRepository $workCaseRepository)
    {
    }

    /**
     * @param array<string, int|null> $criteria
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int}
     */
    public function getPublicList(int $page, int $perPage, array $criteria): array
    {
        $publishedAt = gmdate('Y-m-d H:i:s');
        $total = $this->workCaseRepository->countPublic($publishedAt, $criteria);
        $rows = $this->workCaseRepository->findPublicPage(
            $publishedAt,
            $perPage,
            ($page - 1) * $perPage,
            $criteria,
        );
        $services = $this->groupServices(
            $this->workCaseRepository->findActiveServicesByWorkCaseIds(
                array_map(static fn (array $row): int => (int) $row['id'], $rows),
            ),
        );

        return [
            'items' => array_map(
                static fn (array $row): array => self::mapSummary(
                    $row,
                    $services[(int) $row['id']] ?? [],
                ),
                $rows,
            ),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /** @return array<string, mixed> */
    public function getPublicDetail(int $id): array
    {
        $row = $this->workCaseRepository->findPublicById($id, gmdate('Y-m-d H:i:s'));

        if ($row === null) {
            throw new NotFoundException('WORK_CASE_NOT_FOUND', '작업 사례를 찾을 수 없습니다.');
        }

        $services = $this->groupServices(
            $this->workCaseRepository->findActiveServicesByWorkCaseIds([$id]),
        );

        return [
            ...self::mapSummary($row, $services[$id] ?? []),
            'description' => self::nullableString($row['description']),
            'business' => [
                'id' => (int) $row['business_id'],
                'name' => (string) $row['business_name'],
            ],
            // Public image URL policy is not finalized. Never expose storage paths.
            'images' => [],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{id: int, name: string}> $services
     * @return array<string, mixed>
     */
    private static function mapSummary(array $row, array $services): array
    {
        return [
            'id' => (int) $row['id'],
            'businessId' => (int) $row['business_id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'category' => self::relatedEntity($row['category_id'], $row['category_name']),
            'brand' => self::relatedEntity($row['brand_id'], $row['brand_name']),
            'services' => $services,
            'beforeImageUrl' => null,
            'afterImageUrl' => null,
            'publishedAt' => self::utcDateTime((string) $row['published_at']),
        ];
    }

    /** @return array{id: int, name: string}|null */
    private static function relatedEntity(mixed $id, mixed $name): ?array
    {
        if ($id === null || $name === null) {
            return null;
        }

        return ['id' => (int) $id, 'name' => (string) $name];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function groupServices(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['work_case_id']][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        return $grouped;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
