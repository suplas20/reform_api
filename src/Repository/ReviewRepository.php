<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReviewRepository implements ReviewReadRepository, ReviewWriteRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function publicBusinessExists(int $businessId, string $publishedAt): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT 1
                FROM businesses
                WHERE businesses.id = :business_id
                  AND businesses.status = :status
                  AND businesses.deleted_at IS NULL
                  AND businesses.published_at IS NOT NULL
                  AND businesses.published_at <= :published_at
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'ACTIVE',
            'published_at' => $publishedAt,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function countPublicByBusinessId(int $businessId, array $criteria): int
    {
        [$conditions, $parameters] = $this->reviewConditions($criteria);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.business_id = :business_id
                  AND reviews.status = :status
                  AND reviews.deleted_at IS NULL
                  %s
                SQL,
            implode("\n", $conditions),
        ));
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'ACTIVE',
            ...$parameters,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findPublicPageByBusinessId(
        int $businessId,
        int $limit,
        int $offset,
        array $criteria,
    ): array {
        [$conditions, $parameters] = $this->reviewConditions($criteria);
        $orderBy = match ($criteria['sort'] ?? 'latest') {
            'ratingDesc' => 'reviews.rating DESC, reviews.created_at DESC, reviews.id DESC',
            'ratingAsc' => 'reviews.rating ASC, reviews.created_at DESC, reviews.id DESC',
            default => 'reviews.created_at DESC, reviews.id DESC',
        };
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT
                    reviews.id,
                    reviews.rating,
                    reviews.content,
                    reviews.created_at,
                    reviews.updated_at,
                    users.id AS user_id,
                    users.name AS user_name,
                    users.profile_image
                FROM reviews
                INNER JOIN users ON users.id = reviews.user_id
                WHERE reviews.business_id = :business_id
                  AND reviews.status = :status
                  AND reviews.deleted_at IS NULL
                  %s
                ORDER BY %s
                LIMIT :limit OFFSET :offset
                SQL,
            implode("\n", $conditions),
            $orderBy,
        ));
        $statement->bindValue(':business_id', $businessId, PDO::PARAM_INT);
        $statement->bindValue(':status', 'ACTIVE');

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    public function summarizePublicByBusinessId(int $businessId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    AVG(reviews.rating) AS average_rating,
                    COUNT(*) AS review_count,
                    SUM(CASE WHEN reviews.rating = 1 THEN 1 ELSE 0 END) AS rating_1,
                    SUM(CASE WHEN reviews.rating = 2 THEN 1 ELSE 0 END) AS rating_2,
                    SUM(CASE WHEN reviews.rating = 3 THEN 1 ELSE 0 END) AS rating_3,
                    SUM(CASE WHEN reviews.rating = 4 THEN 1 ELSE 0 END) AS rating_4,
                    SUM(CASE WHEN reviews.rating = 5 THEN 1 ELSE 0 END) AS rating_5
                FROM reviews
                WHERE reviews.business_id = :business_id
                  AND reviews.status = :status
                  AND reviews.deleted_at IS NULL
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'ACTIVE',
        ]);
        $summary = $statement->fetch();

        /** @var array{average_rating: mixed, review_count: mixed, rating_1: mixed, rating_2: mixed, rating_3: mixed, rating_4: mixed, rating_5: mixed} */
        return $summary;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{list<string>, array<string, int>}
     */
    private function reviewConditions(array $criteria): array
    {
        $conditions = [];
        $parameters = [];

        if (($criteria['rating'] ?? null) !== null) {
            $conditions[] = 'AND reviews.rating = :rating';
            $parameters['rating'] = $criteria['rating'];
        }

        if (($criteria['hasImages'] ?? false) === true) {
            $conditions[] = <<<'SQL'
                AND EXISTS (
                    SELECT 1
                    FROM review_images
                    WHERE review_images.review_id = reviews.id
                )
                SQL;
        }

        return [$conditions, $parameters];
    }

    public function findByIdempotencyKey(int $userId, string $idempotencyKey): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, request_hash, created_at
                FROM reviews
                WHERE user_id = :user_id AND idempotency_key = :idempotency_key
                LIMIT 1
                SQL,
        );
        $statement->execute(['user_id' => $userId, 'idempotency_key' => $idempotencyKey]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function createReview(
        int $userId,
        int $businessId,
        int $rating,
        ?string $content,
        ?string $idempotencyKey,
        ?string $requestHash,
        string $now,
    ): int
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO reviews
                    (business_id, user_id, idempotency_key, request_hash, rating, content, status, created_at, updated_at)
                VALUES
                    (:business_id, :user_id, :idempotency_key, :request_hash, :rating, :content, :status, :created_at, :updated_at)
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'rating' => $rating,
            'content' => $content,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function addTags(int $reviewId, array $tags): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO review_tags (review_id, tag, sort_order) VALUES (:review_id, :tag, :sort_order)',
        );

        foreach ($tags as $index => $tag) {
            $statement->execute(['review_id' => $reviewId, 'tag' => $tag, 'sort_order' => $index]);
        }
    }

    public function addCompletedMedia(int $reviewId, int $userId, array $mediaIds, string $now): void
    {
        $find = $this->connection->prepare(
            <<<'SQL'
                SELECT storage_path FROM media_uploads
                WHERE id = :id AND user_id = :user_id AND purpose = :purpose AND status = :status
                LIMIT 1
                SQL,
        );
        $insert = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO review_images (review_id, storage_type, file_path, sort_order, created_at)
                VALUES (:review_id, :storage_type, :file_path, :sort_order, :created_at)
                SQL,
        );

        foreach ($mediaIds as $index => $mediaId) {
            $find->execute(['id' => $mediaId, 'user_id' => $userId, 'purpose' => 'review', 'status' => 'COMPLETED']);
            $path = $find->fetchColumn();

            if (!is_string($path)) {
                throw new \InvalidArgumentException('Invalid review media.');
            }

            $insert->execute([
                'review_id' => $reviewId,
                'storage_type' => 'LOCAL',
                'file_path' => $path,
                'sort_order' => $index,
                'created_at' => $now,
            ]);
        }
    }

    public function refreshBusinessStats(int $businessId, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO business_reputation_stats
                    (business_id, review_count, rating_avg, favorite_count, work_case_count, updated_at)
                SELECT :business_id,
                    COUNT(*), AVG(rating), 0, 0, :updated_at
                FROM reviews
                WHERE business_id = :review_business_id AND status = :review_status AND deleted_at IS NULL
                ON DUPLICATE KEY UPDATE
                    review_count = VALUES(review_count),
                    rating_avg = VALUES(rating_avg),
                    updated_at = VALUES(updated_at)
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'updated_at' => $now,
            'review_business_id' => $businessId,
            'review_status' => 'ACTIVE',
        ]);
    }

    public function countByUserId(int $userId, string $type): int
    {
        $join = $type === 'helpful'
            ? 'INNER JOIN review_helpful_users ON review_helpful_users.review_id = reviews.id AND review_helpful_users.user_id = :helpful_user_id'
            : '';
        $condition = $type === 'written' ? ' AND reviews.user_id = :written_user_id' : '';
        $statement = $this->connection->prepare(sprintf(
            'SELECT COUNT(*) FROM reviews %s WHERE reviews.status = :status AND reviews.deleted_at IS NULL%s',
            $join,
            $condition,
        ));
        $parameters = ['status' => 'ACTIVE'];

        if ($type === 'helpful') {
            $parameters['helpful_user_id'] = $userId;
        } else {
            $parameters['written_user_id'] = $userId;
        }

        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function findPageByUserId(int $userId, string $type, int $limit, int $offset): array
    {
        $join = $type === 'helpful'
            ? 'INNER JOIN review_helpful_users ON review_helpful_users.review_id = reviews.id AND review_helpful_users.user_id = :helpful_user_id'
            : '';
        $condition = $type === 'written' ? ' AND reviews.user_id = :written_user_id' : '';
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT reviews.id, reviews.business_id, reviews.rating, reviews.content,
                    reviews.created_at, reviews.updated_at, businesses.name AS business_name
                FROM reviews
                %s
                INNER JOIN businesses ON businesses.id = reviews.business_id
                WHERE reviews.status = :status AND reviews.deleted_at IS NULL%s
                ORDER BY reviews.created_at DESC, reviews.id DESC
                LIMIT :limit OFFSET :offset
                SQL,
            $join,
            $condition,
        ));
        $statement->bindValue(':status', 'ACTIVE');
        $statement->bindValue($type === 'helpful' ? ':helpful_user_id' : ':written_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
