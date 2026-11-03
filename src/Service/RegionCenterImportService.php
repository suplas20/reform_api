<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Repository\RegionCenterRepository;
use RuntimeException;

final class RegionCenterImportService
{
    private const COLUMNS = ['province', 'district', 'dong', 'office_name', 'road_address',
        'latitude', 'longitude', 'source', 'source_url', 'verified_at'];

    public function __construct(
        private readonly RegionCenterRepository $repository,
        private readonly TransactionManager $transactions,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(string $path, string $sha256, string $source, bool $apply): array
    {
        if ($source !== 'COMMUNITY-OFFICES' || !is_file($path)
            || !hash_equals($sha256, hash_file('sha256', $path))) {
            throw new RuntimeException('Community office reference or checksum is invalid.');
        }
        $file = fopen($path, 'r');
        if ($file === false) {
            throw new RuntimeException('Community office reference could not be opened.');
        }
        try {
            if (fgetcsv($file, escape: '') !== self::COLUMNS) {
                throw new RuntimeException('Community office reference columns changed.');
            }
            $centers = [];
            while (($row = fgetcsv($file, escape: '')) !== false) {
                if (count($row) !== count(self::COLUMNS) || count($centers) >= 10000) {
                    throw new RuntimeException('Community office reference has invalid dimensions.');
                }
                $centers[] = array_combine(self::COLUMNS, $row);
            }
        } finally {
            fclose($file);
        }
        if ($centers === []) {
            throw new RuntimeException('Community office reference must not be empty.');
        }
        $operation = function () use ($centers, $apply): array {
            $plan = $this->plan($centers, $this->repository->regions());
            if ($apply) {
                if ($plan['matched'] === 0) {
                    throw new RuntimeException('No registered towns match the community office reference.');
                }
                $this->repository->clearUnreferencedCenters($plan['verifiedRegionIds']);
                foreach ($plan['updates'] as $update) {
                    $this->repository->update($update['id'], $update['center'], $update['center']['source'], gmdate('Y-m-d H:i:s'));
                }
            }
            return ['applied' => $apply, 'source' => 'COMMUNITY-OFFICES', 'sourceRows' => count($centers),
                'matched' => $plan['matched'], 'updated' => count($plan['updates']),
                'retiredNonOfficeCenters' => $plan['retiredNonOfficeCenters'],
                'retiredOfficeCenters' => $plan['retiredOfficeCenters'],
                'unmatched' => $plan['unmatched'], 'unusedOffices' => $plan['unusedOffices']];
        };
        return $apply ? $this->transactions->run($operation) : $operation();
    }

    /** @param list<array<string, mixed>> $centers @param list<array<string, mixed>> $regions
     * @return array<string, mixed> */
    public function plan(array $centers, array $regions): array
    {
        $byName = [];
        foreach ($centers as $center) {
            foreach (self::COLUMNS as $column) {
                if (!isset($center[$column]) || !is_string($center[$column]) || trim($center[$column]) === '') {
                    throw new RuntimeException('Community office reference field is missing.');
                }
            }
            if (preg_match('/\AOFFICE-[A-Z0-9-]{1,33}\z/', $center['source']) !== 1
                || preg_match('/(?:주민센터|행정복지센터|읍사무소|면사무소|동사무소)\z/u', $center['office_name']) !== 1) {
                throw new RuntimeException('Only community office references are accepted.');
            }
            $host = parse_url($center['source_url'], PHP_URL_HOST);
            if (parse_url($center['source_url'], PHP_URL_SCHEME) !== 'https' || !is_string($host)
                || !(str_ends_with($host, '.go.kr') || str_ends_with($host, '.seoul.kr'))) {
                throw new RuntimeException('Community office reference needs an official source URL.');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $center['verified_at']);
            if ($date === false || $date->format('Y-m-d') !== $center['verified_at']) {
                throw new RuntimeException('Community office verification date is invalid.');
            }
            foreach (['latitude' => [32, 39], 'longitude' => [124, 132]] as $field => [$minimum, $maximum]) {
                $number = filter_var($center[$field], FILTER_VALIDATE_FLOAT);
                if ($number === false || !is_finite($number) || $number < $minimum || $number > $maximum) {
                    throw new RuntimeException('Invalid community office coordinate.');
                }
                $center[$field] = $number;
            }
            $officeDong = preg_replace('/(?:행정복지센터|주민센터|사무소)\z/u', '', $center['office_name']);
            if ($this->normalize((string) $officeDong) !== $this->normalize($center['dong'])) {
                throw new RuntimeException('Community office name does not match its administrative town.');
            }
            $key = $this->key($center['province'], $center['district'], $center['dong']);
            // Internal reference identifier, not an administrative code from another provider.
            $center['source_code'] = substr(hash('sha256', $key), 0, 30);
            $byName[$key][] = $center;
            $ordinalName = preg_replace('/제(?=\d)/u', '', $center['dong']);
            if ($ordinalName !== $center['dong']) {
                $byName[$this->key($center['province'], $center['district'], (string) $ordinalName)][] = $center;
            }
        }
        $matched = $retired = 0;
        $updates = $unmatched = $usedCodes = $verifiedRegionIds = [];
        foreach ($regions as $region) {
            if (!str_starts_with((string) $region['center_source'], 'OFFICE-')
                && ($region['center_latitude'] !== null || $region['center_longitude'] !== null || $region['center_source'] !== null)) {
                ++$retired;
            }
            if ($region['status'] !== 'ACTIVE' || (int) $region['level'] !== 3) {
                continue;
            }
            $key = $this->key((string) $region['ancestor_name'], (string) $region['parent_name'], $region['name']);
            $candidates = $byName[$key] ?? [];
            if (count($candidates) !== 1) {
                $unmatched[] = ['id' => (int) $region['id'], 'code' => $region['code'], 'name' => $region['name'],
                    'province' => $region['ancestor_name'], 'district' => $region['parent_name'],
                    'reason' => $candidates === [] ? 'OFFICE_NOT_VERIFIED' : 'AMBIGUOUS_OFFICE'];
                continue;
            }
            ++$matched;
            $verifiedRegionIds[] = (int) $region['id'];
            $center = $candidates[0];
            $usedCodes[$center['source_code']] = true;
            if ($region['center_latitude'] !== null && $region['center_longitude'] !== null
                && abs((float) $region['center_latitude'] - $center['latitude']) < 0.00000005
                && abs((float) $region['center_longitude'] - $center['longitude']) < 0.00000005
                && $region['center_source'] === $center['source'] && $region['center_source_code'] === $center['source_code']) {
                continue;
            }
            $updates[] = ['id' => (int) $region['id'], 'center' => $center];
        }
        $unusedOffices = [];
        $reportedCodes = [];
        foreach ($byName as $offices) {
            $code = $offices[0]['source_code'];
            if (!isset($usedCodes[$code]) && !isset($reportedCodes[$code])) {
                $reportedCodes[$code] = true;
                $unusedOffices[] = ['province' => $offices[0]['province'], 'district' => $offices[0]['district'],
                    'dong' => $offices[0]['dong'], 'reason' => 'REGISTERED_TOWN_NOT_FOUND_OR_AMBIGUOUS'];
            }
        }
        $verifiedSet = array_fill_keys($verifiedRegionIds, true);
        $retiredOffices = count(array_filter($regions, static fn (array $region): bool =>
            str_starts_with((string) $region['center_source'], 'OFFICE-') && !isset($verifiedSet[(int) $region['id']])));
        return ['matched' => $matched, 'updates' => $updates, 'unmatched' => $unmatched,
            'verifiedRegionIds' => $verifiedRegionIds, 'retiredNonOfficeCenters' => $retired,
            'retiredOfficeCenters' => $retiredOffices, 'unusedOffices' => $unusedOffices];
    }

    private function normalize(string $name): string
    {
        $name = strtr((string) preg_replace('/\s+/u', '', $name), ['ㆍ' => '·', '.' => '·', ',' => '·']);
        return $name;
    }

    private function key(string $province, string $district, string $dong): string
    {
        return implode('|', [$this->normalize($province), $this->normalize($district), $this->normalize($dong)]);
    }
}
