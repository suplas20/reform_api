<?php

declare(strict_types=1);

$environment = getenv('APP_ENV');
$debug = getenv('APP_DEBUG');
$timezone = getenv('APP_TIMEZONE');
$corsAllowedOrigins = getenv('CORS_ALLOWED_ORIGINS');

$environment = is_string($environment) && $environment !== '' ? $environment : 'local';
$defaultCorsAllowedOrigins = $environment === 'local'
    ? ['http://localhost:8081', 'http://127.0.0.1:8081', 'http://[::1]:8081']
    : [];

if (is_string($corsAllowedOrigins) && trim($corsAllowedOrigins) !== '') {
    $corsAllowedOrigins = array_values(array_unique(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', $corsAllowedOrigins),
    ))));
} else {
    $corsAllowedOrigins = $defaultCorsAllowedOrigins;
}

return [
    'environment' => $environment,
    'debug' => is_string($debug) && filter_var($debug, FILTER_VALIDATE_BOOL),
    'timezone' => is_string($timezone) && $timezone !== '' ? $timezone : 'UTC',
    'cors_allowed_origins' => $corsAllowedOrigins,
];
