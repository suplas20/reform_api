<?php

declare(strict_types=1);

namespace App\Integration\AdministrativeRegions;

interface AdministrativeRegionSource
{
    /** @return array{items: list<array<string, mixed>>, totalCount: int} */
    public function fetchPage(int $page): array;
}
