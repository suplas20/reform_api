<?php

declare(strict_types=1);

$localConfigPath = __DIR__ . '/public_data.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];

if (!is_array($localConfig)) {
    throw new RuntimeException('config/public_data.local.php must return an array.');
}

$environmentConfig = [
    'semas' => [
        'endpoint' => 'https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInUpjong',
        'service_key' => (string) (getenv('DATA_GO_KR_SERVICE_KEY') ?: ''),
        'page_size' => 1000,
        'timeout_seconds' => 30,
        'request_interval_milliseconds' => 100,
        'industry_codes' => ['S20601', 'S20602', 'S20603', 'S20699'],
        'keywords' => [
            '리폼',
            '리메이크',
            '커스텀',
            '업사이클',
            '복원',
            '염색',
            '천갈이',
            '가죽',
            '가방',
            '구두',
            '신발',
            '옷수선',
            '의류수선',
            '가구',
            '소파',
            '쇼파',
            '의자',
        ],
    ],
];

return array_replace_recursive($environmentConfig, $localConfig);
