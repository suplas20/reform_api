<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\BusinessService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BusinessController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly BusinessService $businessService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $page = $this->paginationValue(
            $query['page'] ?? null,
            self::DEFAULT_PAGE,
            intdiv(PHP_INT_MAX, self::MAX_PER_PAGE),
        );
        $perPage = $this->paginationValue(
            $query['perPage'] ?? null,
            self::DEFAULT_PER_PAGE,
            self::MAX_PER_PAGE,
        );
        $result = $this->businessService->getPublicList($page, $perPage);

        return ApiResponse::list(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
            $result['totalPages'],
        );
    }

    public function show(Request $request): JsonResponse
    {
        $id = filter_var(
            $request->attributes->get('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($id === false) {
            throw new ValidationException(
                'INVALID_BUSINESS_ID',
                '업체 ID가 올바르지 않습니다.',
            );
        }

        return ApiResponse::success(
            $this->businessService->getPublicDetail($id),
        );
    }

    private function paginationValue(
        mixed $value,
        int $default,
        int $maximum,
    ): int {
        if ($value === null) {
            return $default;
        }

        if (!is_int($value) && !is_string($value)) {
            $this->throwInvalidPagination();
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => $maximum]],
        );

        if ($validated === false) {
            $this->throwInvalidPagination();
        }

        return $validated;
    }

    private function throwInvalidPagination(): never
    {
        throw new ValidationException(
            'INVALID_PAGINATION',
            '페이지 정보가 올바르지 않습니다.',
        );
    }
}
