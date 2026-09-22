<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AdService;
use App\Service\AuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AdService $service,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $placement = RequestValidator::enum($request->query->get('placement'), ['home_feed_01', 'search_result_01']);

        return ApiResponse::success($this->service->get($placement));
    }

    public function impression(Request $request): Response
    {
        return $this->record($request, 'IMPRESSION');
    }

    public function click(Request $request): Response
    {
        return $this->record($request, 'CLICK');
    }

    private function record(Request $request, string $type): Response
    {
        $input = RequestValidator::json($request);
        $user = $this->authService->optionalUser($request->headers->get('Authorization'));
        $this->service->record(
            RequestValidator::positiveInt($request->attributes->get('adId')),
            $user === null ? null : (int) $user['id'],
            RequestValidator::requiredString($input['eventId'] ?? null, 100),
            $type,
            RequestValidator::requiredString($input['trackingToken'] ?? null, 500),
        );

        return new Response('', 204);
    }
}
