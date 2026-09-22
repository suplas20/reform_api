<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FavoriteRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countByUserId(int $userId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM user_favorites WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<int> */
    public function findBusinessIdsByUserId(int $userId, int $limit, int $offset): array
    {
        $statement = $this->connection->prepare(
            'SELECT business_id FROM user_favorites WHERE user_id = :user_id ORDER BY created_at DESC, business_id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function add(int $userId, int $businessId, string $now): void
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO user_favorites (user_id, business_id, created_at) VALUES (:user_id, :business_id, :created_at)',
        );
        $statement->execute(['user_id' => $userId, 'business_id' => $businessId, 'created_at' => $now]);
    }

    public function remove(int $userId, int $businessId): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM user_favorites WHERE user_id = :user_id AND business_id = :business_id',
        );
        $statement->execute(['user_id' => $userId, 'business_id' => $businessId]);
    }

    public function refreshBusinessCount(int $businessId, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO business_reputation_stats
                    (business_id, review_count, rating_avg, favorite_count, work_case_count, updated_at)
                VALUES
                    (:business_id, 0, NULL,
                     (SELECT COUNT(*) FROM user_favorites WHERE business_id = :favorite_business_id),
                     0, :updated_at)
                ON DUPLICATE KEY UPDATE
                    favorite_count = (SELECT COUNT(*) FROM user_favorites WHERE business_id = :update_business_id),
                    updated_at = :update_updated_at
                SQL,
        );
        $statement->execute([
            'business_id' => $businessId,
            'favorite_business_id' => $businessId,
            'updated_at' => $now,
            'update_business_id' => $businessId,
            'update_updated_at' => $now,
        ]);
    }
}
