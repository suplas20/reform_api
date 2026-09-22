<?php

declare(strict_types=1);

namespace App\Repository;

interface ReviewWriteRepository
{
    /** @return array{id: mixed, request_hash: mixed, created_at: mixed}|null */
    public function findByIdempotencyKey(int $userId, string $idempotencyKey): ?array;

    public function createReview(
        int $userId,
        int $businessId,
        int $rating,
        ?string $content,
        ?string $idempotencyKey,
        ?string $requestHash,
        string $now,
    ): int;

    /** @param list<string> $tags */
    public function addTags(int $reviewId, array $tags): void;

    /** @param list<string> $mediaIds */
    public function addCompletedMedia(int $reviewId, int $userId, array $mediaIds, string $now): void;

    public function refreshBusinessStats(int $businessId, string $now): void;

    public function countByUserId(int $userId, string $type): int;

    /** @return list<array<string, mixed>> */
    public function findPageByUserId(int $userId, string $type, int $limit, int $offset): array;
}
