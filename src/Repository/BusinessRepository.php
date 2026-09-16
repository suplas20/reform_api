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
                    business_external_ratings.review_count
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
