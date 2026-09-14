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
    ],
];
