<?php

declare(strict_types=1);

$localPath = __DIR__ . '/administrative_regions.local.php';
$local = is_file($localPath) ? require $localPath : [];
if (!is_array($local)) {
    throw new RuntimeException('Administrative region configuration must return an array.');
}

return array_replace([
    'base_url' => 'https://api.odcloud.kr/api',
    'path' => '/15136373/v1/uddi:84f86747-7012-4d74-b4c6-9d5068312ac4',
    'dataset_version' => '20250704',
    // A completed snapshot is immutable. Use a new key to collect a later publication.
    'snapshot_key' => 'kostat-15136373-20250704',
    'service_key' => (string) (getenv('ADMINISTRATIVE_REGIONS_SERVICE_KEY') ?: ''),
    'page_size' => 1000,
    'timeout_seconds' => 30,
    'request_interval_milliseconds' => 100,
], $local);
