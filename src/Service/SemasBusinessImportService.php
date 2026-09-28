<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Integration\Semas\SemasOpenApiClient;
use App\Repository\SemasBusinessImportRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SemasBusinessImportService
{
    private const BATCH_SIZE = 500;
    private const MAX_API_REQUESTS_PER_RUN = 10000;

    /** @param list<string> $industryCodes */
    public function __construct(
        private readonly SemasOpenApiClient $client,
        private readonly SemasBusinessImportRepository $repository,
        private readonly SemasBusinessClassifier $classifier,
        private readonly TransactionManager $transactionManager,
        private readonly array $industryCodes,
        private readonly int $pageSize,
        private readonly int $requestIntervalMilliseconds = 100,
    ) {
        if ($this->industryCodes === []) {
            throw new InvalidArgumentException('At least one SEMAS industry code is required.');
        }

        foreach ($this->industryCodes as $industryCode) {
            if (preg_match('/^S206(?:01|02|03|99)$/', $industryCode) !== 1) {
                throw new InvalidArgumentException('Unsupported SEMAS industry code.');
            }
        }

        if ($this->pageSize < 1 || $this->pageSize > 1000) {
            throw new InvalidArgumentException('SEMAS page size must be between 1 and 1000.');
        }

        if ($this->requestIntervalMilliseconds < 0 || $this->requestIntervalMilliseconds > 5000) {
            throw new InvalidArgumentException('SEMAS request interval must be between 0 and 5000 milliseconds.');
        }
    }

    /**
     * @param null|callable(string, int, int, int): void $progress
     * @return array{pages: int, collected: int, rejected: int}
     */
    public function collect(?callable $progress = null): array
    {
        $stats = ['pages' => 0, 'collected' => 0, 'rejected' => 0];

        foreach ($this->industryCodes as $industryCode) {
            $page = 1;
            $totalPages = 1;

            while ($page <= $totalPages) {
                if ($stats['pages'] >= self::MAX_API_REQUESTS_PER_RUN) {
                    throw new RuntimeException(
                        'SEMAS import stopped at the 10,000-request development-account safety limit.',
                    );
                }

                $response = $this->client->fetchIndustryPage($industryCode, $page);
                $totalPages = max(1, (int) ceil($response['totalCount'] / $this->pageSize));

                if ($totalPages > 10000) {
                    throw new InvalidArgumentException('SEMAS API page count exceeds the importer safety limit.');
                }

                $records = [];

                foreach ($response['items'] as $item) {
                    try {
                        $records[] = $this->normalizeRecord($item, $industryCode);
                    } catch (InvalidArgumentException) {
                        ++$stats['rejected'];
                    }
                }

                if ($records !== []) {
                    $now = gmdate('Y-m-d H:i:s');
                    $this->transactionManager->run(function () use ($records, $response, $now): void {
                        foreach ($records as $record) {
                            $this->repository->upsertCollected(
                                $record,
                                $response['dataReferenceMonth'],
                                $now,
                            );
                        }
                    });
                }

                ++$stats['pages'];
                $stats['collected'] += count($records);

                if ($progress !== null) {
                    $progress($industryCode, $page, $totalPages, count($records));
                }

                if ($response['items'] === [] && $page < $totalPages) {
                    throw new RuntimeException(sprintf(
                        'SEMAS API returned an empty page before completion (%s page %d/%d).',
                        $industryCode,
                        $page,
                        $totalPages,
                    ));
                }

                if ($page >= $totalPages || $response['items'] === []) {
                    break;
                }

                ++$page;

                if ($this->requestIntervalMilliseconds > 0) {
                    usleep($this->requestIntervalMilliseconds * 1000);
                }
            }
        }

        return $stats;
    }

    /** @return array{classified: int, matched: int, unmatched: int} */
    public function classify(): array
    {
        $stats = ['classified' => 0, 'matched' => 0, 'unmatched' => 0];
        $afterId = 0;

        while (true) {
            $candidates = $this->repository->findClassificationBatch($afterId, self::BATCH_SIZE);

            if ($candidates === []) {
                break;
            }

            $now = gmdate('Y-m-d H:i:s');
            $this->transactionManager->run(function () use ($candidates, $now, &$stats): void {
                foreach ($candidates as $candidate) {
                    $keywords = $this->classifier->matchingKeywords($candidate);
                    $this->repository->replaceClassification((int) $candidate['id'], $keywords, $now);
                    ++$stats['classified'];

                    if ($keywords === []) {
                        ++$stats['unmatched'];
                    } else {
                        ++$stats['matched'];
                    }
                }
            });
            $afterId = (int) $candidates[array_key_last($candidates)]['id'];
        }

        return $stats;
    }

    /** @return array{synced: int, failed: int} */
    public function sync(): array
    {
        $stats = ['synced' => 0, 'failed' => 0];
        $afterId = 0;

        while (true) {
            $candidates = $this->repository->findMatchedBatch($afterId, self::BATCH_SIZE);

            if ($candidates === []) {
                break;
            }

            foreach ($candidates as $candidate) {
                $afterId = (int) $candidate['id'];

                try {
                    $taxonomy = $this->classifier->taxonomy(
                        $candidate['matching_keywords'],
                        (string) $candidate['industry_small_code'],
                    );
                    $now = gmdate('Y-m-d H:i:s');
                    $this->transactionManager->run(fn (): int => $this->repository->syncBusiness(
                        $candidate,
                        $taxonomy['categorySlugs'],
                        $taxonomy['serviceSlugs'],
                        $now,
                    ));
                    ++$stats['synced'];
                } catch (Throwable $exception) {
                    ++$stats['failed'];
                    throw new RuntimeException(
                        sprintf(
                            'Failed to synchronize SEMAS business %s: %s',
                            (string) ($candidate['external_id'] ?? 'unknown'),
                            $exception->getMessage(),
                        ),
                        previous: $exception,
                    );
                }
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function normalizeRecord(array $item, string $requestedIndustryCode): array
    {
        $record = [
            'external_id' => $this->requiredString($item, 'bizesId', 255),
            'business_name' => $this->requiredString($item, 'bizesNm', 200),
            'branch_name' => $this->optionalString($item, 'brchNm', 200),
            'industry_large_code' => $this->requiredString($item, 'indsLclsCd', 20),
            'industry_large_name' => $this->requiredString($item, 'indsLclsNm', 100),
            'industry_medium_code' => $this->requiredString($item, 'indsMclsCd', 20),
            'industry_medium_name' => $this->requiredString($item, 'indsMclsNm', 100),
            'industry_small_code' => $this->requiredString($item, 'indsSclsCd', 20),
            'industry_small_name' => $this->requiredString($item, 'indsSclsNm', 100),
            'standard_industry_code' => $this->optionalString($item, 'ksicCd', 30),
            'standard_industry_name' => $this->optionalString($item, 'ksicNm', 200),
            'province_code' => $this->requiredString($item, 'ctprvnCd', 20),
            'province_name' => $this->requiredString($item, 'ctprvnNm', 100),
            'district_code' => $this->requiredString($item, 'signguCd', 20),
            'district_name' => $this->requiredString($item, 'signguNm', 100),
            'administrative_dong_code' => $this->optionalString($item, 'adongCd', 30),
            'administrative_dong_name' => $this->optionalString($item, 'adongNm', 100),
            'legal_dong_code' => $this->optionalString($item, 'ldongCd', 30),
            'legal_dong_name' => $this->optionalString($item, 'ldongNm', 100),
            'lot_number_code' => $this->optionalString($item, 'lnoCd', 40),
            'lot_number_address' => $this->optionalString($item, 'lnoAdr', 300),
            'road_name_code' => $this->optionalString($item, 'rdnmCd', 30),
            'road_name' => $this->optionalString($item, 'rdnm', 200),
            'building_management_number' => $this->optionalString($item, 'bldMngNo', 40),
            'building_name' => $this->optionalString($item, 'bldNm', 200),
            'road_address' => $this->optionalString($item, 'rdnmAdr', 300),
            'postal_code' => $this->optionalString($item, 'newZipcd', 20),
            'dong_number' => $this->optionalString($item, 'dongNo', 50),
            'floor_number' => $this->optionalString($item, 'flrNo', 50),
            'unit_number' => $this->optionalString($item, 'hoNo', 50),
            'longitude' => $this->coordinate($item['lon'] ?? null, -180.0, 180.0),
            'latitude' => $this->coordinate($item['lat'] ?? null, -90.0, 90.0),
        ];

        if (
            $record['industry_large_code'] !== 'S2'
            || $record['industry_medium_code'] !== 'S206'
            || $record['industry_small_code'] !== $requestedIndustryCode
        ) {
            throw new InvalidArgumentException('SEMAS API row has an unexpected industry classification.');
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $record['external_id']) !== 1) {
            throw new InvalidArgumentException('SEMAS business identifier has an invalid format.');
        }

        if (
            preg_match('/^\d{2}$/', $record['province_code']) !== 1
            || preg_match('/^\d{5}$/', $record['district_code']) !== 1
        ) {
            throw new InvalidArgumentException('SEMAS region code has an invalid format.');
        }

        return $record;
    }

    /** @param array<string, mixed> $item */
    private function requiredString(array $item, string $key, int $maximumLength): string
    {
        $value = $this->optionalString($item, $key, $maximumLength);

        if ($value === null) {
            throw new InvalidArgumentException(sprintf('Required SEMAS field is missing: %s', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $item */
    private function optionalString(array $item, string $key, int $maximumLength): ?string
    {
        if (!array_key_exists($key, $item) || $item[$key] === null) {
            return null;
        }

        if (!is_scalar($item[$key])) {
            throw new InvalidArgumentException(sprintf('SEMAS field is not scalar: %s', $key));
        }

        $value = trim((string) $item[$key]);

        if ($value === '') {
            return null;
        }

        $length = preg_match_all('/./us', $value, $matches);

        if ($length === false) {
            throw new InvalidArgumentException(sprintf('SEMAS field is not valid UTF-8: %s', $key));
        }

        if ($length > $maximumLength) {
            throw new InvalidArgumentException(sprintf('SEMAS field is too long: %s', $key));
        }

        return $value;
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('SEMAS coordinate is not numeric.');
        }

        $coordinate = (float) $value;

        if (!is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) {
            throw new InvalidArgumentException('SEMAS coordinate is outside the allowed range.');
        }

        return number_format($coordinate, 9, '.', '');
    }
}
