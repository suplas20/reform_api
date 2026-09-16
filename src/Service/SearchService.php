<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\BrandSuggestionRepository;
use App\Repository\BusinessSuggestionRepository;
use App\Repository\ServiceSuggestionRepository;

final class SearchService implements SearchDataProvider
{
    public function __construct(
        private readonly BusinessSuggestionRepository $businessRepository,
        private readonly ServiceSuggestionRepository $serviceRepository,
        private readonly BrandSuggestionRepository $brandRepository,
    ) {
    }

    public function getSuggestions(string $keyword, int $limit): array
    {
        $map = static fn (array $result): array => [
            'id' => (int) $result['id'],
            'name' => (string) $result['name'],
        ];

        return [
            'businesses' => array_map(
                $map,
                $this->businessRepository->findPublicSuggestions(
                    $keyword,
                    gmdate('Y-m-d H:i:s'),
                    $limit,
                ),
            ),
            'services' => array_map(
                $map,
                $this->serviceRepository->findActiveSuggestions($keyword, $limit),
            ),
            'brands' => array_map(
                $map,
                $this->brandRepository->findActiveSuggestions($keyword, $limit),
            ),
        ];
    }
}
