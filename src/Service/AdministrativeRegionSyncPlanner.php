<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AdministrativeRegionSyncPlanner
{
    /**
     * References are either existing region IDs (id:123) or new statistical codes.
     * @param list<array<string, mixed>> $source
     * @param list<array<string, mixed>> $regions
     * @param list<array<string, mixed>> $currentDongs
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public function plan(array $source, array $regions, array $currentDongs, array $rules): array
    {
        $nodes = [];
        $ignored = 0;
        foreach ($source as $row) {
            $code = (string) ($row['administrative_code'] ?? '');
            if ($code === '') {
                ++$ignored;
                continue;
            }
            if (!in_array(strlen($code), [2, 5, 8], true) || !ctype_digit($code)
                || !is_string($row['name']) || trim($row['name']) === ''
                || str_contains($row['name'], "\n")) {
                throw new RuntimeException('Unexpected coded source row; synchronization stopped.');
            }
            $node = array_intersect_key($row, array_flip(['administrative_code', 'name', 'top_level_code', 'parent_code']));
            if (isset($nodes[$code]) && $nodes[$code] !== $node) {
                throw new RuntimeException('Conflicting names or parents for statistical code ' . $code);
            }
            $nodes[$code] = $node;
        }
        ksort($nodes);
        $byCode = $byName = [];
        foreach ($regions as $region) {
            $code = $region['code'];
            $key = ($region['parent_id'] ?? 'root') . ':' . $region['name'];
            if (($code !== null && isset($byCode[$code])) || isset($byName[$key])) {
                throw new RuntimeException('Ambiguous existing region identity; synchronization stopped.');
            }
            if ($code !== null) {
                $byCode[$code] = $region;
            }
            $byName[$key] = $region;
        }
        $currentParents = [];
        foreach ($currentDongs as $dong) {
            $currentParents[$dong['administrative_dong_name']]['KR-' . $dong['district_code']] = true;
        }
        $mapping = $create = $skipped = $aggregates = [];
        foreach ($nodes as $code => $node) {
            $code = (string) $code;
            if (strlen($code) !== 2) {
                continue;
            }
            $targetCode = $rules['root_targets'][$code] ?? null;
            $target = $targetCode === null ? ($byName['root:' . $node['name']] ?? null) : ($byCode[$targetCode] ?? null);
            if ($target === null || (int) $target['level'] !== 1 || $target['status'] !== 'ACTIVE') {
                throw new RuntimeException('Verified province target missing for ' . $code);
            }
            $mapping[$code] = 'id:' . $target['id'];
        }
        foreach ($nodes as $code => $node) {
            $code = (string) $code;
            if (strlen($code) !== 5) {
                continue;
            }
            if (!isset($mapping[$node['top_level_code']]) || $node['parent_code'] !== $code) {
                throw new RuntimeException('Unexpected statistical district hierarchy: ' . $code);
            }
            $base = substr($code, 0, 4) . '0';
            if ($code !== $base && isset($nodes[$base])) {
                $aggregates[$base] = true;
            }
        }
        foreach ($nodes as $code => $node) {
            $code = (string) $code;
            if (strlen($code) !== 5 || isset($aggregates[$code]) || isset($rules['replaced_districts'][$code])) {
                continue;
            }
            $parent = $mapping[$node['top_level_code']];
            $base = substr($code, 0, 4) . '0';
            $name = $rules['district_aliases'][$code]
                ?? ($base !== $code && isset($nodes[$base]) ? $nodes[$base]['name'] . ' ' . $node['name'] : $node['name']);
            $mapping[$code] = $this->resolve($code, $name, $parent, 2, $byCode, $byName, $create);
        }
        foreach ($nodes as $code => $node) {
            $code = (string) $code;
            if (strlen($code) !== 8) {
                continue;
            }
            $district = (string) $node['parent_code'];
            if (!isset($nodes[$district]) || strlen($district) !== 5
                || $nodes[$district]['top_level_code'] !== $node['top_level_code']) {
                throw new RuntimeException('Missing or inconsistent dong parent: ' . $code);
            }
            $parent = $mapping[$district] ?? null;
            if (in_array($code, $rules['retired_dongs'] ?? [], true)) {
                $skipped[] = ['code' => $code, 'name' => $node['name'], 'sourceParent' => $district,
                    'reason' => 'SPLIT_SINCE_SOURCE_REVISION'];
                continue;
            }
            if (isset($rules['replaced_districts'][$district])) {
                $candidates = array_values(array_filter(
                    $rules['replaced_districts'][$district],
                    static fn (string $target): bool => isset($currentParents[$node['name']][$target]),
                ));
                foreach ($rules['verified_dong_parents'][$district] ?? [] as $target => $names) {
                    if (in_array($node['name'], $names, true)) {
                        if (!in_array($target, $rules['replaced_districts'][$district], true)) {
                            throw new RuntimeException('Verified dong target is not an allowed replacement district.');
                        }
                        $candidates[] = $target;
                    }
                }
                $candidates = array_values(array_unique($candidates));
                if (count($candidates) > 1) {
                    throw new RuntimeException('Conflicting current district matches for dong ' . $code);
                }
                if (count($candidates) === 1 && isset($byCode[$candidates[0]])) {
                    $target = $byCode[$candidates[0]];
                    if ((int) $target['level'] !== 2 || $target['status'] !== 'ACTIVE'
                        || 'id:' . $target['parent_id'] !== $mapping[$node['top_level_code']]) {
                        throw new RuntimeException('Replacement district has an inconsistent province.');
                    }
                    $parent = 'id:' . $target['id'];
                }
            }
            if ($parent === null) {
                $skipped[] = ['code' => $code, 'name' => $node['name'], 'sourceParent' => $district,
                    'reason' => 'CURRENT_PARENT_UNCONFIRMED'];
                continue;
            }
            $mapping[$code] = $this->resolve($code, $node['name'], $parent, 3, $byCode, $byName, $create);
        }
        return [
            'sourceRows' => count($source), 'uniqueCodes' => count($nodes), 'ignoredUncodedRows' => $ignored,
            'aggregateCities' => count($aggregates), 'created' => count($create),
            'createdDistricts' => count(array_filter($create, static fn (array $r): bool => $r['level'] === 2)),
            'createdDongs' => count(array_filter($create, static fn (array $r): bool => $r['level'] === 3)),
            'mappedCodes' => count($mapping), 'skippedDongs' => $skipped,
            'create' => $create, 'mapping' => $mapping,
        ];
    }

    /** @param array<string, array<string, mixed>> $byCode @param array<string, array<string, mixed>> $byName
     * @param list<array<string, mixed>> $create */
    private function resolve(string $sourceCode, string $name, string $parent, int $level, array $byCode, array $byName, array &$create): string
    {
        $code = 'KOSTAT-' . $sourceCode;
        $existing = $byCode[$code] ?? (str_starts_with($parent, 'id:') ? ($byName[substr($parent, 3) . ':' . $name] ?? null) : null);
        if ($existing !== null) {
            if ($existing['name'] !== $name || (int) $existing['level'] !== $level
                || $parent !== 'id:' . $existing['parent_id'] || $existing['status'] !== 'ACTIVE') {
                throw new RuntimeException('Existing region conflicts with planned hierarchy: ' . $sourceCode);
            }
            return 'id:' . $existing['id'];
        }
        foreach ($create as $planned) {
            if ($planned['parent'] === $parent && $planned['name'] === $name) {
                throw new RuntimeException('Multiple statistical codes resolve to the same region name.');
            }
        }
        $create[] = ['code' => $code, 'name' => $name, 'parent' => $parent, 'level' => $level];
        return $code;
    }
}
