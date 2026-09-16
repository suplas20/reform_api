<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Repository\BusinessReadRepository;
use DateTimeImmutable;
use DateTimeZone;

final class BusinessService
{
    public function __construct(private readonly BusinessReadRepository $businessRepository)
    {
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     totalPages: int
     * }
     */
    public function getPublicList(int $page, int $perPage): array
    {
        $publishedAt = gmdate('Y-m-d H:i:s');
        $total = $this->businessRepository->countPublic($publishedAt);
        $businesses = $this->businessRepository->findPublicPage(
            $publishedAt,
            $perPage,
            ($page - 1) * $perPage,
        );
        $businessIds = array_map(
            static fn (array $business): int => (int) $business['id'],
            $businesses,
        );
        $externalReputation = $this->groupExternalReputation(
            $this->businessRepository->findActiveExternalReputationByBusinessIds($businessIds),
        );

        return [
            'items' => array_map(
                static fn (array $business): array => self::mapSummary(
                    $business,
                    $externalReputation[(int) $business['id']] ?? [],
                ),
                $businesses,
            ),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     shortDescription: ?string,
     *     description: ?string,
     *     phone: ?string,
     *     supports: array{shipping: bool, visit: bool, pickup: bool},
     *     publishedAt: string,
     *     reputation: array{
     *         internal: array{
     *             rating: ?float,
     *             reviewCount: int,
     *             favoriteCount: int,
     *             workCaseCount: int
     *         },
     *         external: list<array{
     *             provider: string,
     *             rating: ?float,
     *             reviewCount: ?int,
     *             url: string
     *         }>
     *     }
     * }
     */
    public function getPublicDetail(int $id): array
    {
        $business = $this->businessRepository->findPublicDetailById(
            $id,
            gmdate('Y-m-d H:i:s'),
        );

        if ($business === null) {
            throw new NotFoundException(
                'BUSINESS_NOT_FOUND',
                '업체를 찾을 수 없습니다.',
            );
        }

        $externalReputation = $this->businessRepository
            ->findActiveExternalReputationByBusinessIds([$id]);

        return [
            'id' => (int) $business['id'],
            'name' => (string) $business['name'],
            'slug' => (string) $business['slug'],
            'shortDescription' => self::nullableString($business['short_description']),
            'description' => self::nullableString($business['description']),
            'phone' => self::nullableString($business['phone']),
            'supports' => [
                'shipping' => (bool) $business['supports_shipping'],
                'visit' => (bool) $business['supports_visit'],
                'pickup' => (bool) $business['supports_pickup'],
            ],
            'publishedAt' => self::utcDateTime((string) $business['published_at']),
            'reputation' => self::mapReputation($business, $externalReputation),
        ];
    }

    /**
     * @param array<string, mixed> $business
     * @param list<array<string, mixed>> $externalReputation
     * @return array<string, mixed>
     */
    private static function mapSummary(array $business, array $externalReputation): array
    {
        return [
            'id' => (int) $business['id'],
            'name' => (string) $business['name'],
            'slug' => (string) $business['slug'],
            'shortDescription' => self::nullableString($business['short_description']),
            'supports' => [
                'shipping' => (bool) $business['supports_shipping'],
                'visit' => (bool) $business['supports_visit'],
                'pickup' => (bool) $business['supports_pickup'],
            ],
            'publishedAt' => self::utcDateTime((string) $business['published_at']),
            'reputation' => self::mapReputation($business, $externalReputation),
        ];
    }

    /**
     * @param array<string, mixed> $business
     * @param list<array<string, mixed>> $externalReputation
     * @return array{
     *     internal: array{
     *         rating: ?float,
     *         reviewCount: int,
     *         favoriteCount: int,
     *         workCaseCount: int
     *     },
     *     external: list<array{
     *         provider: string,
     *         rating: ?float,
     *         reviewCount: ?int,
     *         url: string
     *     }>
     * }
     */
    private static function mapReputation(array $business, array $externalReputation): array
    {
        return [
            'internal' => [
                'rating' => self::nullableFloat($business['rating_avg']),
                'reviewCount' => (int) $business['review_count'],
                'favoriteCount' => (int) $business['favorite_count'],
                'workCaseCount' => (int) $business['work_case_count'],
            ],
            'external' => array_map(
                static fn (array $reputation): array => [
                    'provider' => (string) $reputation['provider'],
                    'rating' => self::nullableFloat($reputation['rating']),
                    'reviewCount' => self::nullableInt($reputation['review_count']),
                    'url' => (string) $reputation['external_url'],
                ],
                $externalReputation,
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $externalReputation
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupExternalReputation(array $externalReputation): array
    {
        $grouped = [];

        foreach ($externalReputation as $reputation) {
            $businessId = (int) $reputation['business_id'];
            $grouped[$businessId][] = $reputation;
        }

        return $grouped;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
