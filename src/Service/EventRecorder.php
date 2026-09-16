<?php

declare(strict_types=1);

namespace App\Service;

interface EventRecorder
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function record(
        ?int $userId,
        ?string $sessionKey,
        string $eventName,
        ?string $entityType,
        ?int $entityId,
        ?array $metadata,
    ): void;
}
