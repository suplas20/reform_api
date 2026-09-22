<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
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
    public function getPublicList(int $page, int $perPage, array $criteria = []): array
    {
        if (
            ($criteria['categoryId'] ?? null) !== null
            && ($criteria['serviceId'] ?? null) !== null
            && !$this->businessRepository->activeCategoryServiceCombinationExists(
                $criteria['categoryId'],
                $criteria['serviceId'],
            )
        ) {
            throw new ValidationException(
                'FILTER_COMBINATION_INVALID',
                '선택한 카테고리에서 지원하지 않는 작업입니다.',
            );
        }

        $publishedAt = gmdate('Y-m-d H:i:s');
        $total = $this->businessRepository->countPublic($publishedAt, $criteria);
        $businesses = $this->businessRepository->findPublicPage(
            $publishedAt,
            $perPage,
            ($page - 1) * $perPage,
            $criteria,
        );
        $businessIds = array_map(
            static fn (array $business): int => (int) $business['id'],
            $businesses,
        );
        $externalReputation = $this->groupExternalReputation(
            $this->businessRepository->findActiveExternalReputationByBusinessIds($businessIds),
        );
        $summaryRelations = $this->groupSummaryRelations(
            $this->businessRepository->findPublicSummaryRelations($businessIds),
        );

        return [
            'items' => array_map(
                static fn (array $business): array => self::mapSummary(
                    $business,
                    $externalReputation[(int) $business['id']] ?? [],
                    $summaryRelations[(int) $business['id']] ?? self::emptySummaryRelations(),
                ),
                $businesses,
            ),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * @return array<string, mixed>
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
        $relations = $this->businessRepository->findPublicDetailRelations($id);

        return [
            'id' => (int) $business['id'],
            'name' => (string) $business['name'],
            'slug' => (string) $business['slug'],
            'shortDescription' => self::nullableString($business['short_description']),
            'description' => self::nullableString($business['description']),
            'phone' => self::nullableString($business['phone']),
            'supportModes' => [
                'visit' => (bool) $business['supports_visit'],
                'shipping' => (bool) $business['supports_shipping'],
                'pickup' => (bool) $business['supports_pickup'],
            ],
            'verification' => [
                'isVerified' => $relations['verificationTypes'] !== [],
                'types' => $relations['verificationTypes'],
            ],
            'primaryLocation' => self::mapPrimaryLocation($relations['primaryLocation']),
            'hours' => self::mapHours($relations['hours']),
            'categories' => array_map(
                static fn (array $category): array => [
                    'id' => (int) $category['id'],
                    'name' => (string) $category['name'],
                    'isPrimary' => (bool) $category['is_primary'],
                ],
                $relations['categories'],
            ),
            'offerings' => array_map(
                static fn (array $offering): array => [
                    'id' => (int) $offering['id'],
                    'categoryId' => (int) $offering['category_id'],
                    'categoryName' => (string) $offering['category_name'],
                    'serviceId' => (int) $offering['service_id'],
                    'serviceName' => (string) $offering['service_name'],
                    'title' => self::nullableString($offering['title']),
                    'description' => self::nullableString($offering['description']),
                    'priceMin' => self::nullableFloat($offering['price_min']),
                    'priceMax' => self::nullableFloat($offering['price_max']),
                    'priceNote' => self::nullableString($offering['price_note']),
                    'durationMinDays' => self::nullableInt($offering['duration_min_days']),
                    'durationMaxDays' => self::nullableInt($offering['duration_max_days']),
                    'isPrimary' => (bool) $offering['is_primary'],
                ],
                $relations['offerings'],
            ),
            'brands' => array_map(
                static fn (array $brand): array => [
                    'id' => (int) $brand['id'],
                    'name' => (string) $brand['name'],
                    'slug' => (string) $brand['slug'],
                ],
                $relations['brands'],
            ),
            'images' => [],
            'reputation' => self::mapDetailReputation($business, $externalReputation),
            'links' => array_map(
                static fn (array $link): array => [
                    'type' => (string) $link['link_type'],
                    'label' => self::nullableString($link['label']),
                    'url' => (string) $link['url'],
                ],
                $relations['links'],
            ),
            'stats' => [
                'workCaseCount' => (int) $business['work_case_count'],
                'favoriteCount' => (int) $business['favorite_count'],
            ],
            'isFavorite' => false,
        ];
    }

    /**
     * @param list<int> $businessIds
     * @return list<array<string, mixed>>
     */
    public function getPublicSummariesByIds(array $businessIds): array
    {
        $businessIds = array_values(array_unique($businessIds));
        $rows = $this->businessRepository->findPublicByIds($businessIds, gmdate('Y-m-d H:i:s'));
        $rowsById = [];

        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $externalReputation = $this->groupExternalReputation(
            $this->businessRepository->findActiveExternalReputationByBusinessIds($businessIds),
        );
        $relations = $this->groupSummaryRelations(
            $this->businessRepository->findPublicSummaryRelations($businessIds),
        );
        $items = [];

        foreach ($businessIds as $businessId) {
            if (!isset($rowsById[$businessId])) {
                continue;
            }

            $items[] = self::mapSummary(
                $rowsById[$businessId],
                $externalReputation[$businessId] ?? [],
                $relations[$businessId] ?? self::emptySummaryRelations(),
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $business
     * @param list<array<string, mixed>> $externalReputation
     * @return array<string, mixed>
     */
    private static function mapSummary(
        array $business,
        array $externalReputation,
        array $relations,
    ): array
    {
        $location = $relations['location'];

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
            'categoryId' => $relations['categoryId'],
            'regionId' => $location === null ? null : (int) $location['region_id'],
            'serviceIds' => $relations['serviceIds'],
            'brandIds' => $relations['brandIds'],
            'description' => self::nullableString($business['short_description']),
            'rating' => self::nullableFloat($business['rating_avg']),
            'reviewCount' => (int) $business['review_count'],
            'thumbnailUrl' => null,
            'verified' => $relations['verified'],
            'address' => $location === null ? null : (string) $location['road_address'],
            'distanceKm' => isset($business['distance_km']) ? (float) $business['distance_km'] : null,
            'priceLevel' => null,
            'options' => (bool) $business['supports_pickup'] ? ['pickup'] : [],
            'latitude' => $location === null ? null : self::nullableFloat($location['latitude']),
            'longitude' => $location === null ? null : self::nullableFloat($location['longitude']),
            'isFavorite' => false,
        ];
    }

    /**
     * @param array{
     *     locations: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     services: list<array<string, mixed>>,
     *     brands: list<array<string, mixed>>,
     *     verifiedBusinessIds: list<int>
     * } $relations
     * @return array<int, array<string, mixed>>
     */
    private function groupSummaryRelations(array $relations): array
    {
        $grouped = [];

        foreach ($relations['locations'] as $location) {
            $businessId = (int) $location['business_id'];
            $grouped[$businessId] ??= self::emptySummaryRelations();
            $grouped[$businessId]['location'] ??= $location;
        }

        foreach ($relations['categories'] as $category) {
            $businessId = (int) $category['business_id'];
            $grouped[$businessId] ??= self::emptySummaryRelations();
            $grouped[$businessId]['categoryId'] ??= (int) $category['category_id'];
        }

        foreach ($relations['services'] as $service) {
            $businessId = (int) $service['business_id'];
            $grouped[$businessId] ??= self::emptySummaryRelations();
            $grouped[$businessId]['serviceIds'][] = (int) $service['service_id'];
        }

        foreach ($relations['brands'] as $brand) {
            $businessId = (int) $brand['business_id'];
            $grouped[$businessId] ??= self::emptySummaryRelations();
            $grouped[$businessId]['brandIds'][] = (int) $brand['brand_id'];
        }

        foreach ($relations['verifiedBusinessIds'] as $businessId) {
            $grouped[$businessId] ??= self::emptySummaryRelations();
            $grouped[$businessId]['verified'] = true;
        }

        return $grouped;
    }

    /** @return array{location: ?array, categoryId: ?int, serviceIds: list<int>, brandIds: list<int>, verified: bool} */
    private static function emptySummaryRelations(): array
    {
        return [
            'location' => null,
            'categoryId' => null,
            'serviceIds' => [],
            'brandIds' => [],
            'verified' => false,
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
     * @param array<string, mixed>|null $location
     * @return array<string, mixed>|null
     */
    private static function mapPrimaryLocation(?array $location): ?array
    {
        if ($location === null) {
            return null;
        }

        return [
            'regionId' => (int) $location['region_id'],
            'regionName' => (string) $location['region_name'],
            'postalCode' => self::nullableString($location['postal_code']),
            'roadAddress' => (string) $location['road_address'],
            'jibunAddress' => self::nullableString($location['jibun_address']),
            'addressDetail' => self::nullableString($location['address_detail']),
            'latitude' => self::nullableFloat($location['latitude']),
            'longitude' => self::nullableFloat($location['longitude']),
            'distanceKm' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function mapHours(array $rows): array
    {
        $hours = [];

        foreach ($rows as $row) {
            $day = (int) $row['day_of_week'];
            $hours[$day] ??= [
                'dayOfWeek' => $day,
                'periods' => [],
                'isClosed' => false,
            ];

            if ((bool) $row['is_closed']) {
                $hours[$day]['isClosed'] = true;
                $hours[$day]['periods'] = [];
                continue;
            }

            if ($hours[$day]['isClosed']) {
                continue;
            }

            if ($row['open_time'] !== null && $row['close_time'] !== null) {
                $hours[$day]['periods'][] = [
                    'openTime' => substr((string) $row['open_time'], 0, 5),
                    'closeTime' => substr((string) $row['close_time'], 0, 5),
                ];
            }
        }

        return array_values($hours);
    }

    /**
     * @param array<string, mixed> $business
     * @param list<array<string, mixed>> $externalReputation
     * @return array<string, mixed>
     */
    private static function mapDetailReputation(array $business, array $externalReputation): array
    {
        return [
            'internal' => [
                'rating' => self::nullableFloat($business['rating_avg']),
                'reviewCount' => (int) $business['review_count'],
            ],
            'external' => array_map(
                static function (array $reputation): array {
                    $item = [
                        'provider' => (string) $reputation['provider'],
                        'rating' => self::nullableFloat($reputation['rating']),
                        'reviewCount' => self::nullableInt($reputation['review_count']),
                        'url' => (string) $reputation['external_url'],
                    ];

                    if (($reputation['fetched_at'] ?? null) !== null) {
                        $item['fetchedAt'] = self::utcDateTime((string) $reputation['fetched_at']);
                    }

                    return $item;
                },
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
