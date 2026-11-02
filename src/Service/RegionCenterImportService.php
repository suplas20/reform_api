<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Repository\RegionCenterRepository;
use RuntimeException;

final class RegionCenterImportService
{
    public function __construct(
        private readonly RegionCenterRepository $repository,
        private readonly TransactionManager $transactions,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(string $path, string $sha256, string $source, bool $apply): array
    {
        if (!is_file($path) || !hash_equals($sha256, hash_file('sha256', $path))) {
            throw new RuntimeException('Region center reference checksum failed.');
        }
        $file = fopen($path, 'r');
        if ($file === false) {
            throw new RuntimeException('Region center reference could not be opened.');
        }
        try {
            $header = fgetcsv($file, escape: '');
            if ($header !== ['source_code', 'province', 'district', 'dong', 'latitude', 'longitude']) {
                throw new RuntimeException('Region center reference columns changed.');
            }
            $centers = [];
            while (($row = fgetcsv($file, escape: '')) !== false) {
                if (count($row) !== count($header) || count($centers) >= 10000) {
                    throw new RuntimeException('Region center reference has invalid dimensions.');
                }
                $centers[] = array_combine($header, $row);
            }
        } finally {
            fclose($file);
        }
        $operation = function () use ($centers, $source, $apply): array {
            $plan = $this->plan($centers, $this->repository->regions(), $source);
            if ($apply) {
                foreach ($plan['updates'] as $update) {
                    $this->repository->update($update['id'], $update['center'], $source, gmdate('Y-m-d H:i:s'));
                }
            }
            $result = ['applied' => $apply, 'source' => $source, 'sourceRows' => count($centers),
                'matched' => $plan['matched'], 'updated' => count($plan['updates']), 'unmatched' => $plan['unmatched']];
            return $result;
        };
        return $apply ? $this->transactions->run($operation) : $operation();
    }

    /**
     * @param list<array<string, mixed>> $centers
     * @param list<array<string, mixed>> $regions
     * @return array<string, mixed>
     */
    public function plan(array $centers, array $regions, string $source): array
    {
        $byCode = $byName = $byOrdinalName = [];
        foreach ($centers as $center) {
            if (!is_string($center['source_code']) || preg_match('/\A\d{10}\z/', $center['source_code']) !== 1
                || isset($byCode[$center['source_code']])) {
                throw new RuntimeException('Duplicate or invalid region center source code.');
            }
            foreach (['latitude' => 90, 'longitude' => 180] as $field => $maximum) {
                $number = filter_var($center[$field], FILTER_VALIDATE_FLOAT);
                if ($number === false || !is_finite($number) || abs($number) > $maximum) {
                    throw new RuntimeException('Invalid region center coordinate.');
                }
            }
            $level = $center['dong'] !== '' ? 3 : ($center['district'] !== '' ? 2 : 1);
            $name = $center['dong'] ?: ($center['district'] ?: $center['province']);
            $key = $this->key($level, $center['province'], $level === 3 ? $center['district'] : '', $name);
            $byCode[$center['source_code']] = $center;
            $byName[$key][] = $center;
            // KMA uses e.g. 창신제1동 / 홍제제1동; preserve the DB's stem 홍제.
            $ordinalName = preg_replace('/제(?=\d)/u', '', $name);
            if ($ordinalName !== $name) {
                $byOrdinalName[$this->key($level, $center['province'], $level === 3 ? $center['district'] : '', $ordinalName)][] = $center;
            }
        }
        $matched = 0;
        $updates = $unmatched = [];
        foreach ($regions as $region) {
            if ($region['status'] !== 'ACTIVE') {
                continue;
            }
            $level = (int) $region['level'];
            $province = match ($level) { 1 => $region['name'], 2 => $region['parent_name'], 3 => $region['ancestor_name'], default => null };
            $key = $this->key($level, (string) $province, $level === 3 ? (string) $region['parent_name'] : '', $region['name']);
            $candidates = $byName[$key] ?? $byOrdinalName[$key] ?? [];
            if (is_string($region['code']) && preg_match('/\AKR-(\d{2}|\d{5}|\d{8})\z/', $region['code'], $parts) === 1) {
                $code = str_pad($parts[1], 10, '0');
                if (isset($byCode[$code])) {
                    $candidate = $byCode[$code];
                    // Check names as well as codes so a reused code cannot silently relabel a place.
                    $candidateLevel = $candidate['dong'] !== '' ? 3 : ($candidate['district'] !== '' ? 2 : 1);
                    $candidateName = $candidate['dong'] ?: ($candidate['district'] ?: $candidate['province']);
                    if ($candidateLevel === $level && $this->normalize($candidate['province']) === $this->normalize((string) $province)
                        && $this->normalize($candidateName) === $this->normalize($region['name'])) {
                        $candidates = [$candidate];
                    }
                }
            }
            if (count($candidates) !== 1) {
                $unmatched[] = ['id' => (int) $region['id'], 'code' => $region['code'], 'name' => $region['name'],
                    'reason' => count($candidates) === 0 ? 'REFERENCE_NOT_FOUND' : 'AMBIGUOUS_REFERENCE'];
                continue;
            }
            ++$matched;
            $center = $candidates[0];
            if ($region['center_latitude'] !== null && $region['center_longitude'] !== null
                && abs((float) $region['center_latitude'] - (float) $center['latitude']) < 0.00000005
                && abs((float) $region['center_longitude'] - (float) $center['longitude']) < 0.00000005
                && $region['center_source'] === $source && $region['center_source_code'] === $center['source_code']) {
                continue;
            }
            $updates[] = ['id' => (int) $region['id'], 'center' => $center];
        }
        return ['matched' => $matched, 'updates' => $updates, 'unmatched' => $unmatched];
    }

    private function normalize(string $name): string
    {
        return strtr((string) preg_replace('/\s+/u', '', $name), ['ㆍ' => '·', '.' => '·', ',' => '·']);
    }

    private function key(int $level, string $province, string $district, string $name): string
    {
        return implode('|', [$level, $this->normalize($province), $this->normalize($district), $this->normalize($name)]);
    }
}
