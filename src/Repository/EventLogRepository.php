<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class EventLogRepository implements EventLogStore
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        ?int $userId,
        ?string $sessionKey,
        string $eventName,
        ?string $entityType,
        ?int $entityId,
        ?string $metadata,
        string $createdAt,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO event_logs (
                    user_id,
                    session_key,
                    event_name,
                    entity_type,
                    entity_id,
                    metadata,
                    created_at
                ) VALUES (
                    :user_id,
                    :session_key,
                    :event_name,
                    :entity_type,
                    :entity_id,
                    :metadata,
                    :created_at
                )
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'session_key' => $sessionKey,
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => $createdAt,
        ]);
    }
}
