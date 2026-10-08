<?php

declare(strict_types=1);

$socialLocalPath = __DIR__ . '/social.local.php';
$socialLocal = is_file($socialLocalPath) ? require $socialLocalPath : [];
if (!is_array($socialLocal)) {
    throw new RuntimeException('config/social.local.php must return an array.');
}
$naver = array_replace([
    'client_id' => trim((string) (getenv('NAVER_CLIENT_ID') ?: '')),
    'client_secret' => trim((string) (getenv('NAVER_CLIENT_SECRET') ?: '')),
    'redirect_uris' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('NAVER_REDIRECT_URIS') ?: ''))))),
], $socialLocal);

$kakaoLocalPath = __DIR__ . '/kakao.local.php';
$kakaoLocal = is_file($kakaoLocalPath) ? require $kakaoLocalPath : [];
if (!is_array($kakaoLocal)) {
    throw new RuntimeException('config/kakao.local.php must return an array.');
}
$kakao = array_replace([
    'native_app_keys' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('KAKAO_NATIVE_APP_KEYS') ?: ''))))),
], $kakaoLocal);

$googleLocalPath = __DIR__ . '/google.local.php';
$googleLocal = is_file($googleLocalPath) ? require $googleLocalPath : [];
if (!is_array($googleLocal)) {
    throw new RuntimeException('config/google.local.php must return an array.');
}
$google = array_replace([
    'web_client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('GOOGLE_WEB_CLIENT_IDS') ?: ''))))),
    'authorized_client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('GOOGLE_AUTHORIZED_CLIENT_IDS') ?: ''))))),
], $googleLocal);

$authLocalPath = __DIR__ . '/auth.local.php';
$authLocal = is_file($authLocalPath) && (getenv('APP_ENV') ?: 'local') === 'local'
    ? require $authLocalPath : [];
if (!is_array($authLocal)) {
    throw new RuntimeException('config/auth.local.php must return an array.');
}

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
        'naver' => $naver,
        'kakao' => $kakao,
        'google' => $google,
        'access_ttl_seconds' => (int) (getenv('AUTH_ACCESS_TTL_SECONDS') ?: ($authLocal['access_ttl_seconds'] ?? 0)),
        'refresh_ttl_seconds' => (int) (getenv('AUTH_REFRESH_TTL_SECONDS') ?: ($authLocal['refresh_ttl_seconds'] ?? 0)),
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
