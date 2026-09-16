<?php

declare(strict_types=1);

namespace App\Repository;

interface BusinessSuggestionRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findPublicSuggestions(
        string $keyword,
        string $publishedAt,
        int $limit,
    ): array;
}
