<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Service\SearchDataProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SearchController
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    public function __construct(private readonly SearchDataProvider $searchData)
    {
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $keyword = $query['keyword'] ?? null;

        if (!is_string($keyword) || trim($keyword) === '') {
            $this->throwValidationError();
        }

        $limit = $query['limit'] ?? null;

        if ($limit === null) {
            $limit = self::DEFAULT_LIMIT;
        } else {
            if (!is_int($limit) && !is_string($limit)) {
                $this->throwValidationError();
            }

            $limit = filter_var(
                $limit,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => self::MAX_LIMIT]],
            );

            if ($limit === false) {
                $this->throwValidationError();
            }
        }

        return ApiResponse::success(
            $this->searchData->getSuggestions(trim($keyword), $limit),
        );
    }

    private function throwValidationError(): never
    {
        throw new ValidationException(
            'VALIDATION_ERROR',
            '입력값을 확인해주세요.',
        );
    }
}
