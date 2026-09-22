<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BusinessRepository implements BusinessReadRepository, BusinessSuggestionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countPublic(string $publishedAt, array $criteria = []): int
    {
        [$conditions, $parameters] = $this->searchConditions($criteria, 'count');
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT COUNT(DISTINCT businesses.id)
                FROM businesses
                WHERE businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                  %s
                SQL,
            implode("\n", $conditions),
        ));
        $statement->execute([
            'status' => 'ACTIVE',
            'published_at' => $publishedAt,
            ...$parameters,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
        array $criteria = [],
    ): array {
        [$conditions, $parameters] = $this->searchConditions($criteria, 'page');
        $distanceSelect = '';

        if (($criteria['sort'] ?? null) === 'distance') {
            $distanceSelect = sprintf(
                ', (%s) AS distance_km',
                $this->primaryLocationDistanceExpression(
                    ':page_sort_lat_cos',
                    ':page_sort_lat_sin',
                    ':page_sort_lng',
                ),
            );
            $parameters['page_sort_lat_cos'] = $criteria['lat'];
            $parameters['page_sort_lat_sin'] = $criteria['lat'];
            $parameters['page_sort_lng'] = $criteria['lng'];
        }

        $orderBy = match ($criteria['sort'] ?? 'recommended') {
            'distance' => 'distance_km ASC, businesses.id DESC',
            'rating' => 'business_reputation_stats.rating_avg IS NULL ASC, business_reputation_stats.rating_avg DESC, businesses.id DESC',
            'reviewCount' => 'review_count DESC, businesses.id DESC',
            default => 'businesses.published_at DESC, businesses.id DESC',
        };
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT DISTINCT
                    businesses.id,
                    businesses.name,
                    businesses.slug,
                    businesses.short_description,
                    businesses.supports_shipping,
                    businesses.supports_visit,
                    businesses.supports_pickup,
                    businesses.published_at,
                    business_reputation_stats.rating_avg,
                    COALESCE(business_reputation_stats.review_count, 0) AS review_count,
                    COALESCE(business_reputation_stats.favorite_count, 0) AS favorite_count,
                    COALESCE(business_reputation_stats.work_case_count, 0) AS work_case_count
                    %s
                FROM businesses
                LEFT JOIN business_reputation_stats
                    ON business_reputation_stats.business_id = businesses.id
                WHERE businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                  %s
                ORDER BY %s
                LIMIT :limit OFFSET :offset
                SQL,
            $distanceSelect,
            implode("\n", $conditions),
            $orderBy,
        ));
        $statement->bindValue(':status', 'ACTIVE');
        $statement->bindValue(':published_at', $publishedAt);

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $businesses */
        $businesses = $statement->fetchAll();

        return $businesses;
    }

    public function activeCategoryServiceCombinationExists(int $categoryId, int $serviceId): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT 1
                FROM category_services
                INNER JOIN categories ON categories.id = category_services.category_id
                INNER JOIN services ON services.id = category_services.service_id
                WHERE category_services.category_id = :category_id
                  AND category_services.service_id = :service_id
                  AND categories.status = :category_status
                  AND services.status = :service_status
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'category_id' => $categoryId,
            'service_id' => $serviceId,
            'category_status' => 'ACTIVE',
            'service_status' => 'ACTIVE',
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function findPublicSummaryRelations(array $businessIds): array
    {
        $businessIds = array_values(array_unique($businessIds));

        if ($businessIds === []) {
            return [
                'locations' => [],
                'categories' => [],
                'services' => [],
                'brands' => [],
                'verifiedBusinessIds' => [],
            ];
        }

        $locations = $this->fetchByBusinessIds(
            <<<'SQL'
                SELECT
                    business_locations.business_id,
                    business_locations.region_id,
                    business_locations.road_address,
                    business_locations.latitude,
                    business_locations.longitude,
                    business_locations.is_primary,
                    business_locations.id
                FROM business_locations
                WHERE business_locations.business_id IN (%s)
                ORDER BY business_locations.business_id ASC,
                    business_locations.is_primary DESC,
                    business_locations.id ASC
                SQL,
            $businessIds,
        );
        $categories = $this->fetchByBusinessIds(
            <<<'SQL'
                SELECT
                    business_categories.business_id,
                    business_categories.category_id,
                    business_categories.is_primary,
                    categories.sort_order
                FROM business_categories
                INNER JOIN categories ON categories.id = business_categories.category_id
                WHERE business_categories.business_id IN (%s)
                  AND categories.status = :status
                ORDER BY business_categories.business_id ASC,
                    business_categories.is_primary DESC,
                    categories.sort_order ASC,
                    categories.id ASC
                SQL,
            $businessIds,
            ['status' => 'ACTIVE'],
        );
        $services = $this->fetchByBusinessIds(
            <<<'SQL'
                SELECT DISTINCT business_offerings.business_id, business_offerings.service_id
                FROM business_offerings
                INNER JOIN services ON services.id = business_offerings.service_id
                WHERE business_offerings.business_id IN (%s)
                  AND business_offerings.status = :offering_status
                  AND services.status = :service_status
                ORDER BY business_offerings.business_id ASC, business_offerings.service_id ASC
                SQL,
            $businessIds,
            ['offering_status' => 'ACTIVE', 'service_status' => 'ACTIVE'],
        );
        $brands = $this->fetchByBusinessIds(
            <<<'SQL'
                SELECT business_brands.business_id, business_brands.brand_id
                FROM business_brands
                INNER JOIN brands ON brands.id = business_brands.brand_id
                WHERE business_brands.business_id IN (%s)
                  AND brands.status = :status
                ORDER BY business_brands.business_id ASC, business_brands.brand_id ASC
                SQL,
            $businessIds,
            ['status' => 'ACTIVE'],
        );
        $verificationRows = $this->fetchByBusinessIds(
            <<<'SQL'
                SELECT DISTINCT business_verifications.business_id
                FROM business_verifications
                WHERE business_verifications.business_id IN (%s)
                  AND business_verifications.status = :status
                ORDER BY business_verifications.business_id ASC
                SQL,
            $businessIds,
            ['status' => 'APPROVED'],
        );

        return [
            'locations' => $locations,
            'categories' => $categories,
            'services' => $services,
            'brands' => $brands,
            'verifiedBusinessIds' => array_map(
                static fn (array $row): int => (int) $row['business_id'],
                $verificationRows,
            ),
        ];
    }

    public function findPublicByIds(array $businessIds, string $publishedAt): array
    {
        $businessIds = array_values(array_unique($businessIds));

        if ($businessIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [
            'status' => 'ACTIVE',
            'published_at' => $publishedAt,
        ];

        foreach ($businessIds as $index => $businessId) {
            $parameter = 'business_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $businessId;
        }

        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    businesses.id,
                    businesses.name,
                    businesses.slug,
                    businesses.short_description,
                    businesses.supports_shipping,
                    businesses.supports_visit,
                    businesses.supports_pickup,
                    businesses.published_at,
                    business_reputation_stats.rating_avg,
                    COALESCE(business_reputation_stats.review_count, 0) AS review_count,
                    COALESCE(business_reputation_stats.favorite_count, 0) AS favorite_count,
                    COALESCE(business_reputation_stats.work_case_count, 0) AS work_case_count
                FROM businesses
                LEFT JOIN business_reputation_stats
                    ON business_reputation_stats.business_id = businesses.id
                WHERE businesses.id IN (%s)
                  AND businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                SQL,
            implode(', ', $placeholders),
        ));
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublicDetailById(int $id, string $publishedAt): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    businesses.id,
                    businesses.name,
                    businesses.slug,
                    businesses.short_description,
                    businesses.description,
                    businesses.phone,
                    businesses.supports_shipping,
                    businesses.supports_visit,
                    businesses.supports_pickup,
                    businesses.published_at,
                    business_reputation_stats.rating_avg,
                    COALESCE(business_reputation_stats.review_count, 0) AS review_count,
                    COALESCE(business_reputation_stats.favorite_count, 0) AS favorite_count,
                    COALESCE(business_reputation_stats.work_case_count, 0) AS work_case_count
                FROM businesses
                LEFT JOIN business_reputation_stats
                    ON business_reputation_stats.business_id = businesses.id
                WHERE businesses.id = :id
                  AND businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'id' => $id,
            'status' => 'ACTIVE',
            'published_at' => $publishedAt,
        ]);

        $business = $statement->fetch();

        return is_array($business) ? $business : null;
    }

    public function findPublicDetailRelations(int $businessId): array
    {
        $location = $this->fetchOne(
            <<<'SQL'
                SELECT
                    business_locations.region_id,
                    regions.name AS region_name,
                    business_locations.postal_code,
                    business_locations.road_address,
                    business_locations.jibun_address,
                    business_locations.address_detail,
                    business_locations.latitude,
                    business_locations.longitude
                FROM business_locations
                INNER JOIN regions ON regions.id = business_locations.region_id
                WHERE business_locations.business_id = :business_id
                ORDER BY business_locations.is_primary DESC, business_locations.id ASC
                LIMIT 1
                SQL,
            $businessId,
        );

        return [
            'primaryLocation' => $location,
            'hours' => $this->fetchMany(
                <<<'SQL'
                    SELECT day_of_week, sequence, open_time, close_time, is_closed
                    FROM business_hours
                    WHERE business_id = :business_id
                    ORDER BY day_of_week ASC, sequence ASC, id ASC
                    SQL,
                $businessId,
            ),
            'categories' => $this->fetchMany(
                <<<'SQL'
                    SELECT categories.id, categories.name, business_categories.is_primary
                    FROM business_categories
                    INNER JOIN categories ON categories.id = business_categories.category_id
                    WHERE business_categories.business_id = :business_id
                      AND categories.status = :active_status
                    ORDER BY business_categories.is_primary DESC, categories.sort_order ASC, categories.id ASC
                    SQL,
                $businessId,
                ['active_status' => 'ACTIVE'],
            ),
            'offerings' => $this->fetchMany(
                <<<'SQL'
                    SELECT
                        business_offerings.id,
                        business_offerings.category_id,
                        categories.name AS category_name,
                        business_offerings.service_id,
                        services.name AS service_name,
                        business_offerings.title,
                        business_offerings.description,
                        business_offerings.price_min,
                        business_offerings.price_max,
                        business_offerings.price_note,
                        business_offerings.duration_min_days,
                        business_offerings.duration_max_days,
                        business_offerings.is_primary
                    FROM business_offerings
                    INNER JOIN categories ON categories.id = business_offerings.category_id
                    INNER JOIN services ON services.id = business_offerings.service_id
                    WHERE business_offerings.business_id = :business_id
                      AND business_offerings.status = :offering_status
                      AND categories.status = :category_status
                      AND services.status = :service_status
                    ORDER BY business_offerings.is_primary DESC, business_offerings.id ASC
                    SQL,
                $businessId,
                [
                    'offering_status' => 'ACTIVE',
                    'category_status' => 'ACTIVE',
                    'service_status' => 'ACTIVE',
                ],
            ),
            'brands' => $this->fetchMany(
                <<<'SQL'
                    SELECT brands.id, brands.name, brands.slug
                    FROM business_brands
                    INNER JOIN brands ON brands.id = business_brands.brand_id
                    WHERE business_brands.business_id = :business_id
                      AND brands.status = :active_status
                    ORDER BY brands.name ASC, brands.id ASC
                    SQL,
                $businessId,
                ['active_status' => 'ACTIVE'],
            ),
            'links' => $this->fetchMany(
                <<<'SQL'
                    SELECT link_type, label, url
                    FROM business_links
                    WHERE business_id = :business_id
                    ORDER BY is_primary DESC, sort_order ASC, id ASC
                    SQL,
                $businessId,
            ),
            'verificationTypes' => array_map(
                static fn (array $verification): string => (string) $verification['verification_type'],
                $this->fetchMany(
                    <<<'SQL'
                        SELECT DISTINCT verification_type
                        FROM business_verifications
                        WHERE business_id = :business_id
                          AND status = :approved_status
                        ORDER BY verification_type ASC
                        SQL,
                    $businessId,
                    ['approved_status' => 'APPROVED'],
                ),
            ),
        ];
    }

    public function findPublicSuggestions(
        string $keyword,
        string $publishedAt,
        int $limit,
    ): array {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    businesses.id,
                    businesses.name
                FROM businesses
                WHERE businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                  AND businesses.name LIKE :keyword ESCAPE '!'
                ORDER BY businesses.name ASC, businesses.id ASC
                LIMIT :limit
                SQL,
        );
        $statement->bindValue(':status', 'ACTIVE');
        $statement->bindValue(':published_at', $publishedAt);
        $statement->bindValue(':keyword', SqlLikePattern::contains($keyword));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $businesses */
        $businesses = $statement->fetchAll();

        return $businesses;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveExternalReputationByBusinessIds(array $businessIds): array
    {
        $businessIds = array_values(array_unique($businessIds));

        if ($businessIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = ['status' => 'ACTIVE'];

        foreach ($businessIds as $index => $businessId) {
            $parameter = 'business_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $businessId;
        }

        $statement = $this->connection->prepare(
            sprintf(
                <<<'SQL'
                SELECT
                    business_external_refs.business_id,
                    business_external_refs.provider,
                    business_external_refs.external_url,
                    business_external_ratings.rating,
                    business_external_ratings.review_count,
                    business_external_ratings.fetched_at
                FROM business_external_refs
                LEFT JOIN business_external_ratings
                    ON business_external_ratings.business_id = business_external_refs.business_id
                   AND business_external_ratings.provider = business_external_refs.provider
                WHERE business_external_refs.business_id IN (%s)
                  AND business_external_refs.status = :status
                ORDER BY business_external_refs.business_id ASC, business_external_refs.provider ASC
                SQL,
                implode(', ', $placeholders),
            ),
        );
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $reputation */
        $reputation = $statement->fetchAll();

        return $reputation;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function fetchMany(string $sql, int $businessId, array $parameters = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'business_id' => $businessId,
            ...$parameters,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, int $businessId, array $parameters = []): ?array
    {
        $rows = $this->fetchMany($sql, $businessId, $parameters);

        return $rows[0] ?? null;
    }

    /**
     * @param list<int> $businessIds
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function fetchByBusinessIds(
        string $sql,
        array $businessIds,
        array $parameters = [],
    ): array {
        $placeholders = [];

        foreach ($businessIds as $index => $businessId) {
            $parameter = 'business_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $businessId;
        }

        $statement = $this->connection->prepare(sprintf($sql, implode(', ', $placeholders)));
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{list<string>, array<string, mixed>}
     */
    private function searchConditions(array $criteria, string $prefix): array
    {
        $conditions = [];
        $parameters = [];

        if (($criteria['keyword'] ?? null) !== null) {
            $conditions[] = sprintf(
                "AND businesses.name LIKE :%s_keyword ESCAPE '!'",
                $prefix,
            );
            $parameters[$prefix . '_keyword'] = SqlLikePattern::contains($criteria['keyword']);
        }

        $categoryId = $criteria['categoryId'] ?? null;
        $serviceId = $criteria['serviceId'] ?? null;

        if ($categoryId !== null || $serviceId !== null) {
            $offeringConditions = [
                'business_offerings.business_id = businesses.id',
                "business_offerings.status = 'ACTIVE'",
            ];

            if ($categoryId !== null) {
                $offeringConditions[] = 'business_offerings.category_id = :' . $prefix . '_category_id';
                $parameters[$prefix . '_category_id'] = $categoryId;
            }

            if ($serviceId !== null) {
                $offeringConditions[] = 'business_offerings.service_id = :' . $prefix . '_service_id';
                $parameters[$prefix . '_service_id'] = $serviceId;
            }

            $conditions[] = sprintf(
                'AND EXISTS (SELECT 1 FROM business_offerings WHERE %s)',
                implode(' AND ', $offeringConditions),
            );
        }

        foreach ([
            'regionId' => ['business_locations', 'region_id'],
            'brandId' => ['business_brands', 'brand_id'],
        ] as $key => [$table, $column]) {
            if (($criteria[$key] ?? null) === null) {
                continue;
            }

            $parameter = $prefix . '_' . $column;
            $conditions[] = sprintf(
                'AND EXISTS (SELECT 1 FROM %s WHERE %s.business_id = businesses.id AND %s.%s = :%s)',
                $table,
                $table,
                $table,
                $column,
                $parameter,
            );
            $parameters[$parameter] = $criteria[$key];
        }

        if (($criteria['verifiedOnly'] ?? false) === true) {
            $conditions[] = <<<'SQL'
                AND EXISTS (
                    SELECT 1
                    FROM business_verifications
                    WHERE business_verifications.business_id = businesses.id
                      AND business_verifications.status = 'APPROVED'
                )
                SQL;
        }

        if (($criteria['radiusKm'] ?? null) !== null) {
            $latCosParameter = ':' . $prefix . '_radius_lat_cos';
            $latSinParameter = ':' . $prefix . '_radius_lat_sin';
            $lngParameter = ':' . $prefix . '_radius_lng';
            $radiusParameter = ':' . $prefix . '_radius_km';
            $conditions[] = sprintf(
                'AND EXISTS (
                    SELECT 1
                    FROM business_locations
                    WHERE business_locations.business_id = businesses.id
                      AND %s <= %s
                )',
                $this->distanceExpression($latCosParameter, $latSinParameter, $lngParameter),
                $radiusParameter,
            );
            $parameters[$prefix . '_radius_lat_cos'] = $criteria['lat'];
            $parameters[$prefix . '_radius_lat_sin'] = $criteria['lat'];
            $parameters[$prefix . '_radius_lng'] = $criteria['lng'];
            $parameters[$prefix . '_radius_km'] = $criteria['radiusKm'];
        }

        return [$conditions, $parameters];
    }

    private function primaryLocationDistanceExpression(
        string $latitudeCos,
        string $latitudeSin,
        string $longitude,
    ): string
    {
        return sprintf(
            '(SELECT %s
                FROM business_locations
                WHERE business_locations.business_id = businesses.id
                ORDER BY business_locations.is_primary DESC, business_locations.id ASC
                LIMIT 1)',
            $this->distanceExpression($latitudeCos, $latitudeSin, $longitude),
        );
    }

    private function distanceExpression(
        string $latitudeCos,
        string $latitudeSin,
        string $longitude,
    ): string
    {
        return sprintf(
            '6371 * ACOS(LEAST(1, GREATEST(-1,
                COS(RADIANS(%1$s))
                * COS(RADIANS(business_locations.latitude))
                * COS(RADIANS(business_locations.longitude) - RADIANS(%3$s))
                + SIN(RADIANS(%2$s))
                * SIN(RADIANS(business_locations.latitude))
            )))',
            $latitudeCos,
            $latitudeSin,
            $longitude,
        );
    }
}
