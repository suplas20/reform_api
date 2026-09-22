<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\MediaService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MediaController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly MediaService $mediaService,
    ) {
    }

    public function requestUploads(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);

        if (!isset($input['files']) || !is_array($input['files']) || $input['files'] === [] || count($input['files']) > 5) {
            RequestValidator::invalid();
        }

        $files = array_map(static function (mixed $file): array {
            if (!is_array($file)) {
                RequestValidator::invalid();
            }

            return [
                'fileName' => RequestValidator::requiredString($file['fileName'] ?? null, 255),
                'contentType' => RequestValidator::requiredString($file['contentType'] ?? null, 100),
                'size' => RequestValidator::positiveInt($file['size'] ?? null),
                'purpose' => RequestValidator::enum($file['purpose'] ?? null, ['profile', 'review', 'inquiry']),
            ];
        }, $input['files']);
        $userId = (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];

        return ApiResponse::success(['uploads' => $this->mediaService->requestUploads($userId, $files)], 201);
    }

    public function upload(Request $request): Response
    {
        $this->mediaService->upload(
            RequestValidator::requiredString($request->attributes->get('mediaId'), 40),
            RequestValidator::requiredString($request->query->get('token'), 100),
            (string) $request->headers->get('Content-Type'),
            $request->getContent(),
        );

        return new Response('', 204);
    }

    public function complete(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $mediaIds = $input['mediaIds'] ?? null;

        if (!is_array($mediaIds) || $mediaIds === [] || count($mediaIds) > 5) {
            RequestValidator::invalid();
        }

        $mediaIds = array_map(static fn (mixed $id): string => RequestValidator::requiredString($id, 40), $mediaIds);
        $userId = (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];

        return ApiResponse::success($this->mediaService->complete($userId, $mediaIds));
    }
}
