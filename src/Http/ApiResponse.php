<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return self::json(
            [
                'success' => true,
                'data' => $data,
            ],
            $status,
        );
    }

    /**
     * @param list<mixed> $data
     */
    public static function list(
        array $data,
        int $page,
        int $perPage,
        int $total,
        int $totalPages,
    ): JsonResponse {
        return self::json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    public static function error(
        string $code,
        string $message,
        int $status,
    ): JsonResponse {
        return self::json(
            [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            $status,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function json(array $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->setEncodingOptions(
            JsonResponse::DEFAULT_ENCODING_OPTIONS
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        );

        return $response;
    }
}
