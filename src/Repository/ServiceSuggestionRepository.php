<?php

declare(strict_types=1);

namespace App\Repository;

interface ServiceSuggestionRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveSuggestions(string $keyword, int $limit): array;
}
