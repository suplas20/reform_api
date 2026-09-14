<?php

declare(strict_types=1);

$environment = getenv('APP_ENV');
$debug = getenv('APP_DEBUG');
$timezone = getenv('APP_TIMEZONE');

return [
    'environment' => is_string($environment) && $environment !== '' ? $environment : 'local',
    'debug' => is_string($debug) && filter_var($debug, FILTER_VALIDATE_BOOL),
    'timezone' => is_string($timezone) && $timezone !== '' ? $timezone : 'UTC',
];
