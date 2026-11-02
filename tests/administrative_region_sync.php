<?php

declare(strict_types=1);

use App\Service\AdministrativeRegionSyncPlanner;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertRegionSync(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function syncNode(string $code, string $name, string $root, ?string $parent): array
{
    return ['administrative_code' => $code, 'name' => $name, 'top_level_code' => $root, 'parent_code' => $parent];
}

function syncRegion(int $id, ?int $parent, string $code, string $name, int $level): array
{
    return ['id' => $id, 'parent_id' => $parent, 'code' => $code, 'name' => $name, 'level' => $level, 'status' => 'ACTIVE'];
}

function syncMustFail(callable $operation): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new LogicException('Conflicting synchronization must stop.');
}

$planner = new AdministrativeRegionSyncPlanner();
$rules = ['root_targets' => [], 'district_aliases' => [], 'replaced_districts' => []];
$existing = [syncRegion(1, null, 'KR-41', '경기도', 1), syncRegion(2, 1, 'KR-41111', '수원시 장안구', 2)];
$source = [syncNode('31', '경기도', '31', null), syncNode('31010', '수원시', '31', '31010'),
    syncNode('31011', '장안구', '31', '31011'), syncNode('31011510', '파장동', '31', '31011'),
    syncNode('31011510', '파장동', '31', '31011'), syncNode('', '폐지 설명', '31', null)];
$plan = $planner->plan($source, $existing, [], $rules);
assertRegionSync($plan['created'] === 1 && $plan['createdDongs'] === 1, 'Duplicate source rows must create one dong.');
assertRegionSync($plan['mapping']['31011'] === 'id:2', 'Composite district names must preserve existing IDs.');
assertRegionSync($plan['aggregateCities'] === 1 && $plan['ignoredUncodedRows'] === 1, 'Aggregate cities and explanations are not app regions.');
assertRegionSync($plan['create'][0]['parent'] === 'id:2' && $plan['create'][0]['code'] === 'KOSTAT-31011510', 'Statistical codes must be namespaced and parented.');
$existing[] = syncRegion(3, 2, 'KOSTAT-31011510', '파장동', 3);
assertRegionSync($planner->plan($source, $existing, [], $rules)['created'] === 0, 'Repeat application must not duplicate regions.');
$changed = $source;
$changed[] = syncNode('31011510', '다른동', '31', '31011');
syncMustFail(fn () => $planner->plan($changed, $existing, [], $rules));
$changed = $source;
$changed[3]['parent_code'] = '99999';
syncMustFail(fn () => $planner->plan($changed, $existing, [], $rules));
$changedRegions = $existing;
$changedRegions[2]['parent_id'] = 1;
syncMustFail(fn () => $planner->plan($source, $changedRegions, [], $rules));

// Missing districts are inserted before their children and linked through references.
$plan = $planner->plan(array_slice($source, 0, 4), [$existing[0]], [], $rules);
assertRegionSync($plan['createdDistricts'] === 1 && $plan['create'][1]['parent'] === 'KOSTAT-31011', 'New district and dong hierarchy must resolve.');

$officialRules = require dirname(__DIR__) . '/config/administrative_region_sync.php';
$incheon = [syncRegion(10, null, 'KR-28', '인천광역시', 1), syncRegion(11, 10, 'KR-28155', '영종구', 2)];
$source = [syncNode('23', '인천광역시', '23', null), syncNode('23010', '중구', '23', '23010'),
    syncNode('23010681', '영종1동', '23', '23010'), syncNode('23010640', '운서동', '23', '23010')];
$plan = $planner->plan($source, $incheon, [], $officialRules);
assertRegionSync($plan['createdDistricts'] === 0 && $plan['createdDongs'] === 1, 'Old districts must not be recreated.');
assertRegionSync($plan['create'][0]['parent'] === 'id:11', 'Official dong mapping must use current district.');
assertRegionSync($plan['skippedDongs'][0]['reason'] === 'SPLIT_SINCE_SOURCE_REVISION', 'Split historical dongs must not be activated.');
$source[2]['name'] = '확인되지않은동';
$plan = $planner->plan($source, $incheon, [], $officialRules);
assertRegionSync(count($plan['skippedDongs']) === 2, 'Unverified dong placement must be reported without guessing.');
$source[2]['name'] = '영종1동';
$incheon[] = syncRegion(12, 10, 'KR-28125', '제물포구', 2);
syncMustFail(fn () => $planner->plan($source, $incheon,
    [['district_code' => '28125', 'administrative_dong_name' => '영종1동']], $officialRules));

// Two historical province codes can refer to an existing merged province.
$source = [syncNode('24', '광주광역시', '24', null), syncNode('24010', '동구', '24', '24010')];
$merged = [syncRegion(90, null, 'KR-12', '전남광주통합특별시', 1), syncRegion(91, 90, 'KR-12110', '동구', 2)];
$plan = $planner->plan($source, $merged, [], $officialRules);
assertRegionSync($plan['created'] === 0 && $plan['mapping']['24010'] === 'id:91', 'Existing merged province district IDs must remain stable.');

echo "Administrative region synchronization planner tests passed.\n";
