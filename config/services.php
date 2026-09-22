<?php

declare(strict_types=1);

return [
    'logging' => [
        'path' => dirname(__DIR__) . '/storage/logs/app.log',
        'level' => getenv('LOG_LEVEL') ?: 'debug',
    ],
    'storage' => [
        'uploads_path' => dirname(__DIR__) . '/storage/uploads',
        'cache_path' => dirname(__DIR__) . '/storage/cache',
        'public_base_url' => rtrim((string) (getenv('APP_BASE_URL') ?: ''), '/'),
        'maximum_image_bytes' => (int) (getenv('UPLOAD_MAX_IMAGE_BYTES') ?: 0),
    ],
    'auth' => [
        'access_ttl_seconds' => (int) (getenv('AUTH_ACCESS_TTL_SECONDS') ?: 0),
        'refresh_ttl_seconds' => (int) (getenv('AUTH_REFRESH_TTL_SECONDS') ?: 0),
    ],
    'ads' => [
        'tracking_secret' => (string) (getenv('AD_TRACKING_SECRET') ?: ''),
    ],
    'mobile_app' => [
        'minimum_version' => (string) (getenv('APP_MINIMUM_VERSION') ?: ''),
        'latest_version' => (string) (getenv('APP_LATEST_VERSION') ?: ''),
        'maintenance_enabled' => filter_var(getenv('APP_MAINTENANCE_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
        'maintenance_message' => getenv('APP_MAINTENANCE_MESSAGE') ?: null,
        'customer_center_phone' => getenv('CUSTOMER_CENTER_PHONE') ?: null,
        'customer_center_hours' => getenv('CUSTOMER_CENTER_HOURS') ?: null,
        'terms_url' => getenv('TERMS_OF_SERVICE_URL') ?: null,
        'privacy_url' => getenv('PRIVACY_POLICY_URL') ?: null,
        'licenses_url' => getenv('OPEN_SOURCE_LICENSE_URL') ?: null,
    ],
];
