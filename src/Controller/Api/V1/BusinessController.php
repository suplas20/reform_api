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
            $query['perPage'] ?? $query['limit'] ?? null,
            self::DEFAULT_PER_PAGE,
            self::MAX_PER_PAGE,
        );
        $criteria = [
            'keyword' => $this->optionalString($query['keyword'] ?? $query['query'] ?? null),
            'categoryId' => $this->optionalPositiveInt($query['categoryId'] ?? null),
            'serviceId' => $this->optionalPositiveInt($query['serviceId'] ?? null),
            'regionId' => $this->optionalPositiveInt($query['regionId'] ?? null),
            'brandId' => $this->optionalPositiveInt($query['brandId'] ?? null),
            'lat' => $this->optionalFloat($query['lat'] ?? $query['latitude'] ?? null, -90.0, 90.0),
            'lng' => $this->optionalFloat($query['lng'] ?? $query['longitude'] ?? null, -180.0, 180.0),
            'radiusKm' => $this->optionalFloat($query['radiusKm'] ?? null, 0.000001, PHP_FLOAT_MAX),
            'verifiedOnly' => $this->optionalBoolean($query['verifiedOnly'] ?? null) ?? false,
            'sort' => $this->sort($query['sort'] ?? null),
        ];

        if (($criteria['lat'] === null) !== ($criteria['lng'] === null)) {
            $this->throwInvalidPagination();
        }

        if ($criteria['radiusKm'] !== null && $criteria['lat'] === null) {
            $this->throwInvalidPagination();
        }

        if ($criteria['sort'] === 'distance' && $criteria['lat'] === null) {
            $this->throwInvalidPagination();
        }

        $result = $this->businessService->getPublicList($page, $perPage, $criteria);

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
                'VALIDATION_ERROR',
                '입력값을 확인해주세요.',
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

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->paginationValue($value, 1, PHP_INT_MAX);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $this->throwInvalidPagination();
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalFloat(mixed $value, float $minimum, float $maximum): ?float
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            $this->throwInvalidPagination();
        }

        $validated = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($validated === false || $validated < $minimum || $validated > $maximum) {
            $this->throwInvalidPagination();
        }

        return $validated;
    }

    private function optionalBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (!is_bool($value) && !is_int($value) && !is_string($value)) {
            $this->throwInvalidPagination();
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($validated === null) {
            $this->throwInvalidPagination();
        }

        return $validated;
    }

    private function sort(mixed $value): string
    {
        if ($value === null) {
            return 'recommended';
        }

        if (
            !is_string($value)
            || !in_array($value, ['recommended', 'distance', 'rating', 'reviewCount', 'recent'], true)
        ) {
            $this->throwInvalidPagination();
        }

        return $value;
    }

    private function throwInvalidPagination(): never
    {
        throw new ValidationException(
            'VALIDATION_ERROR',
            '입력값을 확인해주세요.',
        );
    }
}
