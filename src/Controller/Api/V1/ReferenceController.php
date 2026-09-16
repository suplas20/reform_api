<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\ReferenceDataProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReferenceController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ReferenceDataProvider $referenceData)
    {
    }

    public function categories(Request $request): JsonResponse
    {
        $query = $request->query->all();

        return ApiResponse::success(
            $this->referenceData->getCategories(
                $this->optionalPositiveInt($query['parentId'] ?? null),
                $this->optionalPositiveInt($query['level'] ?? null),
            ),
        );
    }

    public function services(Request $request): JsonResponse
    {
        $query = $request->query->all();

        return ApiResponse::success(
            $this->referenceData->getServices(
                $this->optionalPositiveInt($query['categoryId'] ?? null),
                $this->optionalPositiveInt($query['limit'] ?? null),
            ),
        );
    }

    public function regions(Request $request): JsonResponse
    {
        $query = $request->query->all();

        return ApiResponse::success(
            $this->referenceData->getRegions(
                $this->optionalPositiveInt($query['parentId'] ?? null),
                $this->optionalPositiveInt($query['level'] ?? null),
            ),
        );
    }

    public function brands(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $page = $this->positiveInt(
            $query['page'] ?? null,
            self::DEFAULT_PAGE,
            intdiv(PHP_INT_MAX, self::MAX_PER_PAGE),
        );
        $perPage = $this->positiveInt(
            $query['perPage'] ?? null,
            self::DEFAULT_PER_PAGE,
            self::MAX_PER_PAGE,
        );
        $keyword = $this->optionalString($query['keyword'] ?? null);
        $result = $this->referenceData->getBrands($keyword, $page, $perPage);

        return ApiResponse::list(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
            $result['totalPages'],
        );
    }

    private function optionalPositiveInt(mixed $value, int $maximum = PHP_INT_MAX): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->positiveInt($value, null, $maximum);
    }

    private function positiveInt(
        mixed $value,
        ?int $default,
        int $maximum,
    ): int {
        if ($value === null && $default !== null) {
            return $default;
        }

        if (!is_int($value) && !is_string($value)) {
            $this->throwValidationError();
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => $maximum]],
        );

        if ($validated === false) {
            $this->throwValidationError();
        }

        return $validated;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $this->throwValidationError();
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function throwValidationError(): never
    {
        throw new ValidationException(
            'VALIDATION_ERROR',
            '입력값을 확인해주세요.',
        );
    }
}
