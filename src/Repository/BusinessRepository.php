<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BusinessRepository implements BusinessReadRepository, BusinessSuggestionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countPublic(string $publishedAt): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM businesses
                WHERE businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                SQL,
        );
        $statement->execute([
            'status' => 'ACTIVE',
            'published_at' => $publishedAt,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findPublicPage(
        string $publishedAt,
        int $limit,
        int $offset,
    ): array {
        $statement = $this->connection->prepare(
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
                WHERE businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                ORDER BY businesses.published_at DESC, businesses.id DESC
                LIMIT :limit OFFSET :offset
                SQL,
        );
        $statement->bindValue(':status', 'ACTIVE');
        $statement->bindValue(':published_at', $publishedAt);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $businesses */
        $businesses = $statement->fetchAll();

        return $businesses;
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
}
