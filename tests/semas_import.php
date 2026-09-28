<?php

declare(strict_types=1);

use App\Integration\Semas\SemasOpenApiClient;
use App\Service\SemasBusinessClassifier;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSemasSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

$requestedQuery = [];
$client = new SemasOpenApiClient(
    'https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInUpjong',
    'decoded+/service=key',
    1000,
    30,
    static function (string $url) use (&$requestedQuery): string {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $requestedQuery);

        return json_encode([
            'response' => [
                'header' => [
                    'resultCode' => '00',
                    'resultMsg' => 'NORMAL SERVICE.',
                    'stdrYm' => '202609',
                ],
                'body' => [
                    'items' => [
                        'item' => [
                            'bizesId' => 'MA-1',
                            'bizesNm' => '가죽 리폼 공방',
                        ],
                    ],
                    'totalCount' => '1',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    },
);
$page = $client->fetchIndustryPage('S20602', 1);

assertSemasSame('decoded+/service=key', $requestedQuery['serviceKey'], 'Service key encoding is invalid.');
assertSemasSame('indsSclsCd', $requestedQuery['divId'], 'Industry division parameter is invalid.');
assertSemasSame('S20602', $requestedQuery['key'], 'Industry code parameter is invalid.');
assertSemasSame(1, $page['totalCount'], 'SEMAS total count parsing failed.');
assertSemasSame('202609', $page['dataReferenceMonth'], 'SEMAS reference month parsing failed.');
assertSemasSame('MA-1', $page['items'][0]['bizesId'], 'SEMAS item parsing failed.');

$columnClient = new SemasOpenApiClient(
    'https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInUpjong',
    'key',
    transport: static fn (): string => json_encode([
        'header' => ['columns' => 'bizesId, bizesNm'],
        'body' => ['items' => [['MA-2', '의류수선 연구소']], 'totalCount' => 1],
    ], JSON_THROW_ON_ERROR),
);
$columnPage = $columnClient->fetchIndustryPage('S20601', 1);

assertSemasSame(
    ['bizesId' => 'MA-2', 'bizesNm' => '의류수선 연구소'],
    $columnPage['items'][0],
    'Column-oriented SEMAS response parsing failed.',
);

$transposedClient = new SemasOpenApiClient(
    'https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInUpjong',
    'key',
    transport: static fn (): string => json_encode([
        'header' => [],
        'body' => [
            'items' => [
                'bizesId' => ['MA-3', 'MA-4'],
                'bizesNm' => ['가방복원', '구두리폼'],
            ],
            'totalCount' => 2,
        ],
    ], JSON_THROW_ON_ERROR),
);
$transposedPage = $transposedClient->fetchIndustryPage('S20602', 1);

assertSemasSame('MA-4', $transposedPage['items'][1]['bizesId'], 'SEMAS column transposition failed.');

$classifier = new SemasBusinessClassifier([
    '리폼', '리메이크', '커스텀', '업사이클', '복원', '염색', '천갈이', '가죽', '가방',
    '구두', '신발', '옷수선', '의류수선', '가구', '소파', '쇼파', '의자',
]);
$matches = $classifier->matchingKeywords([
    'business_name' => '우리 의류-수선 & 리폼 연구소',
    'branch_name' => null,
    'industry_small_name' => '의류/이불 수선업',
]);

assertSemasSame(['리폼', '의류수선'], $matches, 'Keyword normalization or matching failed.');
assertSemasSame(
    [
        'categorySlugs' => ['clothing'],
        'serviceSlugs' => ['repair', 'custom-remodeling'],
    ],
    $classifier->taxonomy($matches, 'S20601'),
    'SEMAS taxonomy mapping failed.',
);
assertSemasSame(
    [],
    $classifier->matchingKeywords([
        'business_name' => '대한수리점',
        'branch_name' => null,
        'industry_small_name' => '가죽/가방/신발 수선업',
    ]),
    'Industry labels must not cause keyword false positives.',
);

fwrite(STDOUT, "SEMAS import tests passed.\n");
