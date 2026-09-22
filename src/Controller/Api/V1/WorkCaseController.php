<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\WorkCaseService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class WorkCaseController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly WorkCaseService $workCaseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $page = $this->positiveInt($query['page'] ?? null, self::DEFAULT_PAGE, intdiv(PHP_INT_MAX, self::MAX_PER_PAGE));
        $perPage = $this->positiveInt(
            $query['perPage'] ?? $query['limit'] ?? null,
            self::DEFAULT_PER_PAGE,
            self::MAX_PER_PAGE,
        );
        $criteria = [
            'businessId' => $this->optionalPositiveInt($query['businessId'] ?? null),
            'categoryId' => $this->optionalPositiveInt($query['categoryId'] ?? null),
            'serviceId' => $this->optionalPositiveInt($query['serviceId'] ?? null),
            'brandId' => $this->optionalPositiveInt($query['brandId'] ?? null),
        ];
        $result = $this->workCaseService->getPublicList($page, $perPage, $criteria);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $this->positiveInt($request->attributes->get('id'), null, PHP_INT_MAX);

        return ApiResponse::success($this->workCaseService->getPublicDetail($id));
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        return $value === null ? null : $this->positiveInt($value, null, PHP_INT_MAX);
    }

    private function positiveInt(mixed $value, ?int $default, int $maximum): int
    {
        if ($value === null && $default !== null) {
            return $default;
        }

        if (!is_int($value) && !is_string($value)) {
            $this->throwValidationError();
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $maximum],
        ]);

        if ($validated === false) {
            $this->throwValidationError();
        }

        return $validated;
    }

    private function throwValidationError(): never
    {
        throw new ValidationException('VALIDATION_ERROR', '입력값을 확인해주세요.');
    }
}
