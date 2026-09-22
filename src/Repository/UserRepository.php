<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, name, nickname, tagline, email, phone, profile_image, created_at
                FROM users
                WHERE id = :id AND status = :status AND deleted_at IS NULL
                LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $userId, 'status' => 'ACTIVE']);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<string> */
    public function findSocialProviders(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT provider FROM user_social_accounts WHERE user_id = :user_id ORDER BY provider ASC',
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findCompletedProfileMediaPath(int $userId, string $mediaId): ?string
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT storage_path
                FROM media_uploads
                WHERE id = :id
                  AND user_id = :user_id
                  AND purpose = :purpose
                  AND status = :status
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'id' => $mediaId,
            'user_id' => $userId,
            'purpose' => 'profile',
            'status' => 'COMPLETED',
        ]);
        $path = $statement->fetchColumn();

        return is_string($path) ? $path : null;
    }

    /** @param array<string, string|null> $changes */
    public function updateProfile(int $userId, array $changes, string $now): void
    {
        $columnMap = [
            'name' => 'name',
            'nickname' => 'nickname',
            'email' => 'email',
            'phone' => 'phone',
            'tagline' => 'tagline',
            'profileImage' => 'profile_image',
        ];
        $assignments = ['updated_at = :updated_at'];
        $parameters = ['id' => $userId, 'updated_at' => $now];

        foreach ($changes as $key => $value) {
            if (!isset($columnMap[$key])) {
                continue;
            }

            $assignments[] = $columnMap[$key] . ' = :' . $key;
            $parameters[$key] = $value;
        }

        $statement = $this->connection->prepare(sprintf(
            'UPDATE users SET %s WHERE id = :id AND status = :active_status AND deleted_at IS NULL',
            implode(', ', $assignments),
        ));
        $parameters['active_status'] = 'ACTIVE';
        $statement->execute($parameters);
    }

    /** @return array{favorites: int, reviews: int, inquiries: int, unreadNotifications: int} */
    public function activityCounts(int $userId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM user_favorites WHERE user_id = :favorites_user_id) AS favorites,
                    (SELECT COUNT(*) FROM reviews WHERE user_id = :reviews_user_id AND status = :review_status AND deleted_at IS NULL) AS reviews,
                    (SELECT COUNT(*) FROM inquiries WHERE user_id = :inquiries_user_id) AS inquiries,
                    (SELECT COUNT(*) FROM notifications WHERE user_id = :notifications_user_id AND read_at IS NULL) AS unread_notifications
                SQL,
        );
        $statement->execute([
            'favorites_user_id' => $userId,
            'reviews_user_id' => $userId,
            'review_status' => 'ACTIVE',
            'inquiries_user_id' => $userId,
            'notifications_user_id' => $userId,
        ]);
        $row = $statement->fetch();

        return [
            'favorites' => (int) $row['favorites'],
            'reviews' => (int) $row['reviews'],
            'inquiries' => (int) $row['inquiries'],
            'unreadNotifications' => (int) $row['unread_notifications'],
        ];
    }

    public function deactivate(int $userId, string $now): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET status = :status, deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL',
        );
        $statement->execute(['id' => $userId, 'status' => 'DELETED', 'deleted_at' => $now, 'updated_at' => $now]);

        $revoke = $this->connection->prepare(
            'UPDATE auth_sessions SET revoked_at = :revoked_at WHERE user_id = :user_id AND revoked_at IS NULL',
        );
        $revoke->execute(['user_id' => $userId, 'revoked_at' => $now]);
    }
}
