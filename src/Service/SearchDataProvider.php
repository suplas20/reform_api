<?php

declare(strict_types=1);

namespace App\Service;

interface SearchDataProvider
{
    /**
     * @return array{
     *     businesses: list<array{id: int, name: string}>,
     *     services: list<array{id: int, name: string}>,
     *     brands: list<array{id: int, name: string}>
     * }
     */
    public function getSuggestions(string $keyword, int $limit): array;
}
