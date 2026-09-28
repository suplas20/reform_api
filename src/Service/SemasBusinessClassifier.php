<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

final class SemasBusinessClassifier
{
    /** @var list<string> */
    private readonly array $keywords;

    /** @param list<string> $keywords */
    public function __construct(array $keywords)
    {
        $normalized = [];

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);

            if ($keyword === '' || $this->characterLength($keyword) > 100) {
                throw new InvalidArgumentException('SEMAS classification keywords must be 1 to 100 characters.');
            }

            $normalized[$keyword] = true;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('At least one SEMAS classification keyword is required.');
        }

        $this->keywords = array_keys($normalized);
    }

    /**
     * Only provider business-name fields are searched. Industry labels are deliberately excluded,
     * otherwise every row in a broad source category would become a false positive.
     *
     * @param array<string, mixed> $candidate
     * @return list<string>
     */
    public function matchingKeywords(array $candidate): array
    {
        $haystack = $this->normalize(implode(' ', [
            (string) ($candidate['business_name'] ?? ''),
            (string) ($candidate['branch_name'] ?? ''),
        ]));
        $matches = [];

        foreach ($this->keywords as $keyword) {
            if (str_contains($haystack, $this->normalize($keyword))) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    /**
     * @param list<string> $matchingKeywords
     * @return array{categorySlugs: list<string>, serviceSlugs: list<string>}
     */
    public function taxonomy(array $matchingKeywords, string $smallIndustryCode): array
    {
        $matches = array_fill_keys($matchingKeywords, true);
        $categorySlugs = [];

        if (isset($matches['가방'])) {
            $categorySlugs[] = 'bag';
        }

        if (isset($matches['구두']) || isset($matches['신발'])) {
            $categorySlugs[] = 'shoes';
        }

        if (isset($matches['옷수선']) || isset($matches['의류수선'])) {
            $categorySlugs[] = 'clothing';
        }

        if (
            isset($matches['가구'])
            || isset($matches['소파'])
            || isset($matches['쇼파'])
            || isset($matches['의자'])
            || isset($matches['천갈이'])
        ) {
            $categorySlugs[] = 'furniture-sofa';
        }

        if ($categorySlugs === []) {
            $categorySlugs[] = match ($smallIndustryCode) {
                'S20601' => 'clothing',
                'S20602' => 'bag',
                default => 'other',
            };
        }

        if (isset($matches['가죽']) && $categorySlugs === ['other']) {
            $categorySlugs = ['bag'];
        }

        $serviceSlugs = ['repair'];

        if (isset($matches['복원'])) {
            $serviceSlugs[] = 'restoration';
        }

        if (isset($matches['염색'])) {
            $serviceSlugs[] = 'dyeing';
        }

        if (isset($matches['천갈이'])) {
            $serviceSlugs[] = 'reupholstery';
        }

        if (
            isset($matches['리폼'])
            || isset($matches['리메이크'])
            || isset($matches['커스텀'])
            || isset($matches['업사이클'])
        ) {
            $serviceSlugs[] = 'custom-remodeling';
        }

        return [
            'categorySlugs' => array_values(array_unique($categorySlugs)),
            'serviceSlugs' => array_values(array_unique($serviceSlugs)),
        ];
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }

    private function characterLength(string $value): int
    {
        $count = preg_match_all('/./us', $value, $matches);

        if ($count === false) {
            throw new InvalidArgumentException('SEMAS keyword must be valid UTF-8.');
        }

        return $count;
    }
}
