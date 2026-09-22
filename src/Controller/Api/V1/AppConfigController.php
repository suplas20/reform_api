<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AppConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AppConfigController
{
    public function __construct(private readonly AppConfigService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->get(
            RequestValidator::enum($request->query->get('platform'), ['ios', 'android']),
            RequestValidator::requiredString($request->query->get('appVersion'), 30),
        ));
    }
}
