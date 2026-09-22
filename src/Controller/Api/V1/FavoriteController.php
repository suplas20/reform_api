<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\FavoriteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FavoriteController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly FavoriteService $favoriteService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $page = RequestValidator::positiveInt($request->query->get('page'), 1);
        $perPage = RequestValidator::positiveInt(
            $request->query->get('perPage', $request->query->get('limit')),
            20,
            100,
        );
        $result = $this->favoriteService->list($this->userId($request), $page, $perPage);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    public function put(Request $request): Response
    {
        $this->favoriteService->add(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('businessId')),
        );

        return new Response('', 204);
    }

    public function delete(Request $request): Response
    {
        $this->favoriteService->remove(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('businessId')),
        );

        return new Response('', 204);
    }

    private function userId(Request $request): int
    {
        return (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];
    }
}
