<?php

declare(strict_types=1);

namespace App\Repository;

interface EventLogStore
{
    public function create(
        ?int $userId,
        ?string $sessionKey,
        string $eventName,
        ?string $entityType,
        ?int $entityId,
        ?string $metadata,
        string $createdAt,
    ): void;
}
