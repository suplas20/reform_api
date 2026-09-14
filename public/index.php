<?php

declare(strict_types=1);

use App\Support\ApplicationFactory;
use Symfony\Component\HttpFoundation\Request;

ini_set('display_errors', '0');
error_reporting(E_ALL);

$rootPath = dirname(__DIR__);

try {
    require $rootPath . '/vendor/autoload.php';

    $application = ApplicationFactory::create($rootPath);
    $response = $application->handle(Request::createFromGlobals());
    $response->send();
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '서버 초기화 중 오류가 발생했습니다.';
}
