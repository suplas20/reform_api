<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use InvalidArgumentException;

final class BusinessRepository implements BusinessReadRepository, BusinessSuggestionRepository
{
    private const EARTH_RADIUS_METERS = 6370986.0;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findActiveRegionAncestors(int $regionId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                WITH RECURSIVE region_ancestors AS (
                    SELECT id, parent_id, name, level, center_latitude, center_longitude, center_source, 0 AS depth
                    FROM regions
                    WHERE id = :region_id AND status = :selected_status
                    UNION ALL
                    SELECT parent.id, parent.parent_id, parent.name, parent.level,
                           parent.center_latitude, parent.center_longitude, parent.center_source, child.depth + 1
                    FROM regions AS parent
                    INNER JOIN region_ancestors AS child ON parent.id = child.parent_id
                    WHERE parent.status = :parent_status
                      AND parent.level < child.level AND child.depth < 2
                )
                SELECT id, parent_id, name, level, center_latitude, center_longitude, center_source
                FROM region_ancestors ORDER BY depth
                SQL,
        );
        $statement->execute(['region_id' => $regionId, 'selected_status' => 'ACTIVE', 'parent_status' => 'ACTIVE']);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'name' => (string) $row['name'], 'level' => (int) $row['level'],
            'center_latitude' => $row['center_latitude'] === null ? null : (float) $row['center_latitude'],
            'center_longitude' => $row['center_longitude'] === null ? null : (float) $row['center_longitude'],
            'center_source' => $row['center_source'],
        ], $statement->fetchAll());
    }

    public function findActiveCityDistrictIds(int $provinceId, string $cityName): array
    {
        $statement = $this->connection->prepare(
            "SELECT id FROM regions
             WHERE parent_id = :province_id AND level = :level AND status = :status
               AND (name = :city_name OR name LIKE :district_prefix ESCAPE '!')
             ORDER BY id",
        );
        $statement->execute(['province_id' => $provinceId, 'level' => 2, 'status' => 'ACTIVE',
            'city_name' => $cityName,
            'district_prefix' => strtr($cityName, ['!' => '!!', '%' => '!%', '_' => '!_']) . ' %']);
        return array_map(static fn (mixed $id): int => (int) $id, $statement->fetchAll(PDO::FETCH_COLUMN));
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

        if (($criteria['lat'] ?? null) !== null && ($criteria['lng'] ?? null) !== null) {
            $distanceSelect = sprintf(
                ', (%s) AS distance_meters',
                $this->primaryLocationDistanceExpression(
                    ':page_distance_lat',
                    ':page_distance_lng',
                ),
            );
            $parameters['page_distance_lat'] = $criteria['lat'];
            $parameters['page_distance_lng'] = $criteria['lng'];
        }

        $orderBy = match ($criteria['sort'] ?? 'recommended') {
            'distance' => 'distance_meters IS NULL ASC, distance_meters ASC, businesses.id DESC',
            'rating' => 'business_reputation_stats.rating_avg IS NULL ASC, business_reputation_stats.rating_avg DESC, businesses.id DESC',
            'name' => 'businesses.name ASC, businesses.id DESC',
            'ratingAsc' => 'business_reputation_stats.rating_avg IS NULL ASC, business_reputation_stats.rating_avg ASC, businesses.id DESC',
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

        if (($criteria['regionId'] ?? null) !== null || isset($criteria['regionIds'])) {
            $regionIds = $criteria['regionIds'] ?? [$criteria['regionId']];
            if (!is_array($regionIds) || $regionIds === [] || count($regionIds) > 100) {
                throw new InvalidArgumentException('Region scope must contain 1 to 100 positive integer IDs.');
            }
            foreach ($regionIds as $regionId) {
                if (!is_int($regionId) || $regionId < 1) {
                    throw new InvalidArgumentException('Region scope IDs must be positive integers.');
                }
            }
            $regionIds = array_values(array_unique($regionIds));
            $scopePlaceholders = [];
            // Native prepares require separate placeholders for each occurrence.
            foreach (['self', 'parent', 'ancestor'] as $scope) {
                $scopePlaceholders[$scope] = [];
                foreach ($regionIds as $index => $regionId) {
                    $parameter = $prefix . '_region_' . $scope . '_' . $index;
                    $scopePlaceholders[$scope][] = ':' . $parameter;
                    $parameters[$parameter] = $regionId;
                }
            }
            $conditions[] = sprintf(
                <<<'SQL'
                    AND EXISTS (
                        SELECT 1
                        FROM business_locations
                        INNER JOIN regions AS location_regions
                            ON location_regions.id = business_locations.region_id
                        LEFT JOIN regions AS location_parent
                            ON location_parent.id = location_regions.parent_id
                        WHERE business_locations.business_id = businesses.id
                          AND (
                              business_locations.region_id IN (%1$s)
                              OR location_regions.parent_id IN (%2$s)
                              OR location_parent.parent_id IN (%3$s)
                          )
                    )
                    SQL,
                implode(', ', $scopePlaceholders['self']),
                implode(', ', $scopePlaceholders['parent']),
                implode(', ', $scopePlaceholders['ancestor']),
            );
        }

        if (($criteria['brandId'] ?? null) !== null) {
            $conditions[] = sprintf(
                'AND EXISTS (SELECT 1 FROM business_brands WHERE business_brands.business_id = businesses.id AND business_brands.brand_id = :%s_brand_id)',
                $prefix,
            );
            $parameters[$prefix . '_brand_id'] = $criteria['brandId'];
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
            $latParameter = ':' . $prefix . '_radius_lat';
            $lngParameter = ':' . $prefix . '_radius_lng';
            [$bounds, $boundParameters] = $this->boundingBoxConditions($criteria, $prefix);
            $candidateConditions = implode(' AND ', $bounds);
            // Native PDO prepares require a distinct placeholder for each occurrence.
            [$distanceBounds, $distanceBoundParameters] = $this->boundingBoxConditions($criteria, $prefix . '_guard');
            $conditions[] = sprintf(
                'AND businesses.id IN (
                    SELECT business_locations.business_id
                    FROM business_locations
                    WHERE %1$s
                      AND business_locations.id = (
                          SELECT primary_location.id
                          FROM business_locations AS primary_location
                          WHERE primary_location.business_id = business_locations.business_id
                          ORDER BY primary_location.is_primary DESC, primary_location.id ASC
                          LIMIT 1
                      )
                      AND CASE WHEN %4$s THEN %2$s ELSE NULL END <= %3$s
                )',
                $candidateConditions,
                $this->distanceExpression($latParameter, $lngParameter),
                ':' . $prefix . '_radius_meters',
                implode(' AND ', $distanceBounds),
            );
            $parameters[$prefix . '_radius_lat'] = $criteria['lat'];
            $parameters[$prefix . '_radius_lng'] = $criteria['lng'];
            // No point on the sphere can be farther than pi * R. Clamp before multiplying
            // to preserve existing positive-radius inputs without float overflow.
            $maximumDistance = M_PI * self::EARTH_RADIUS_METERS;
            $parameters[$prefix . '_radius_meters'] = $criteria['radiusKm'] >= $maximumDistance / 1000
                ? ceil($maximumDistance)
                : $criteria['radiusKm'] * 1000;
            $parameters += $boundParameters;
            $parameters += $distanceBoundParameters;
        }

        return [$conditions, $parameters];
    }

    private function primaryLocationDistanceExpression(
        string $latitude,
        string $longitude,
    ): string
    {
        return sprintf(
            '(SELECT %s
                FROM business_locations
                WHERE business_locations.business_id = businesses.id
                ORDER BY business_locations.is_primary DESC, business_locations.id ASC
                LIMIT 1)',
            $this->distanceExpression($latitude, $longitude),
        );
    }

    private function distanceExpression(
        string $latitude,
        string $longitude,
    ): string
    {
        return sprintf(
            'CASE WHEN business_locations.latitude BETWEEN -90 AND 90
                       AND business_locations.longitude BETWEEN -180 AND 180
                  THEN ST_DISTANCE_SPHERE(
                      POINT(business_locations.longitude, business_locations.latitude),
                      POINT(%2$s, %1$s), 6370986
                  ) ELSE NULL END',
            $latitude,
            $longitude,
        );
    }

    /**
     * Bounding box of the spherical search circle, including pole and dateline crossings.
     * Keep columns unwrapped so the existing (latitude, longitude) index remains usable.
     * @param array<string, mixed> $criteria
     * @return array{list<string>, array<string, float>}
     */
    private function boundingBoxConditions(array $criteria, string $prefix): array
    {
        $angularRadius = min($criteria['radiusKm'] / (self::EARTH_RADIUS_METERS / 1000), M_PI);
        $latitude = deg2rad($criteria['lat']);
        // Expand slightly for decimal-coordinate and floating-point boundary rounding.
        $epsilon = 0.00000001;
        $minimumLatitude = max(-90.0, rad2deg($latitude - $angularRadius) - $epsilon);
        $maximumLatitude = min(90.0, rad2deg($latitude + $angularRadius) + $epsilon);
        $conditions = [
            'business_locations.latitude BETWEEN :' . $prefix . '_min_lat AND :' . $prefix . '_max_lat',
            'business_locations.longitude BETWEEN -180 AND 180',
        ];
        $parameters = [
            $prefix . '_min_lat' => $minimumLatitude,
            $prefix . '_max_lat' => $maximumLatitude,
        ];
        if ($minimumLatitude <= -90.0 || $maximumLatitude >= 90.0) {
            return [$conditions, $parameters];
        }

        $longitudeDelta = rad2deg(asin(min(1.0, sin($angularRadius) / cos($latitude)))) + $epsilon;
        $west = $criteria['lng'] - $longitudeDelta;
        $east = $criteria['lng'] + $longitudeDelta;
        $wrapsDateline = $west < -180.0 || $east > 180.0;
        $parameters[$prefix . '_west_lng'] = $west < -180.0 ? $west + 360.0 : $west;
        $parameters[$prefix . '_east_lng'] = $east > 180.0 ? $east - 360.0 : $east;
        $conditions[] = $wrapsDateline
            ? '(business_locations.longitude >= :' . $prefix . '_west_lng OR business_locations.longitude <= :' . $prefix . '_east_lng)'
            : 'business_locations.longitude BETWEEN :' . $prefix . '_west_lng AND :' . $prefix . '_east_lng';

        return [$conditions, $parameters];
    }
}
