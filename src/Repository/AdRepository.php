<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AdRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByPlacement(string $placement, string $now): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, placement, advertiser, headline, body, image_url, call_to_action, target_url
                FROM ads
                WHERE placement = :placement
                  AND status = :status
                  AND (starts_at IS NULL OR starts_at <= :starts_now)
                  AND (ends_at IS NULL OR ends_at > :ends_now)
                ORDER BY id DESC
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'placement' => $placement,
            'status' => 'ACTIVE',
            'starts_now' => $now,
            'ends_now' => $now,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function activeExists(int $adId, string $now): bool
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT 1 FROM ads
                WHERE id = :id AND status = :status
                  AND (starts_at IS NULL OR starts_at <= :starts_now)
                  AND (ends_at IS NULL OR ends_at > :ends_now)
                LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $adId, 'status' => 'ACTIVE', 'starts_now' => $now, 'ends_now' => $now]);

        return $statement->fetchColumn() !== false;
    }

    public function recordEvent(int $adId, ?int $userId, string $eventId, string $eventType, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT IGNORE INTO ad_events (ad_id, user_id, event_id, event_type, created_at)
                VALUES (:ad_id, :user_id, :event_id, :event_type, :created_at)
                SQL,
        );
        $statement->execute([
            'ad_id' => $adId,
            'user_id' => $userId,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'created_at' => $now,
        ]);
    }
}
