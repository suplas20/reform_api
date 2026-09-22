<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\ValidationException;
use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\ReviewService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReviewController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ?AuthService $authService = null,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $businessId = $this->positiveInt($request->attributes->get('businessId'), null, PHP_INT_MAX);
        $page = $this->positiveInt($query['page'] ?? null, self::DEFAULT_PAGE, intdiv(PHP_INT_MAX, self::MAX_PER_PAGE));
        $perPage = $this->positiveInt(
            $query['perPage'] ?? $query['limit'] ?? null,
            self::DEFAULT_PER_PAGE,
            self::MAX_PER_PAGE,
        );
        $result = $this->reviewService->getPublicList(
            $businessId,
            $page,
            $perPage,
            [
                'rating' => $this->optionalRating($query['rating'] ?? null),
                'hasImages' => $this->optionalBoolean($query['hasImages'] ?? null) ?? false,
                'sort' => $this->sort($query['sort'] ?? null),
            ],
        );

        return ApiResponse::list(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
            $result['totalPages'],
            ['summary' => $result['summary']],
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $businessId = $this->positiveInt($request->attributes->get('businessId'), null, PHP_INT_MAX);

        return ApiResponse::success($this->reviewService->getPublicSummary($businessId));
    }

    public function create(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $tags = $this->stringList($input['tags'] ?? [], 10, 50);
        $mediaIds = $this->stringList($input['mediaIds'] ?? [], 5, 40);
        $content = isset($input['content']) ? RequestValidator::optionalString($input['content'], 500) : null;

        return ApiResponse::success($this->reviewService->create(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('businessId')),
            RequestValidator::positiveInt($input['rating'] ?? null, null, 5),
            $content,
            $tags,
            $mediaIds,
            RequestValidator::optionalString($request->headers->get('Idempotency-Key'), 100),
        ), 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $page = RequestValidator::positiveInt($request->query->get('page'), 1);
        $perPage = RequestValidator::positiveInt($request->query->get('perPage', $request->query->get('limit')), 20, 100);
        $type = RequestValidator::enum($request->query->get('type'), ['written', 'helpful'], 'written');
        $result = $this->reviewService->getUserReviews($this->userId($request), $type, $page, $perPage);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    /** @return list<string> */
    private function stringList(mixed $value, int $maximumItems, int $maximumLength): array
    {
        if (!is_array($value) || count($value) > $maximumItems) {
            RequestValidator::invalid();
        }

        return array_values(array_unique(array_map(
            static fn (mixed $item): string => RequestValidator::requiredString($item, $maximumLength),
            $value,
        )));
    }

    private function userId(Request $request): int
    {
        if ($this->authService === null) {
            throw new \LogicException('Authentication service is unavailable.');
        }

        return (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];
    }

    private function optionalRating(mixed $value): ?int
    {
        return $value === null ? null : $this->positiveInt($value, null, 5);
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

    private function optionalBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (!is_bool($value) && !is_int($value) && !is_string($value)) {
            $this->throwValidationError();
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($validated === null) {
            $this->throwValidationError();
        }

        return $validated;
    }

    private function sort(mixed $value): string
    {
        if ($value === null || $value === 'latest') {
            return 'latest';
        }

        return match ($value) {
            'ratingDesc', 'ratingHigh' => 'ratingDesc',
            'ratingAsc', 'ratingLow' => 'ratingAsc',
            default => $this->throwValidationError(),
        };
    }

    private function throwValidationError(): never
    {
        throw new ValidationException('VALIDATION_ERROR', '입력값을 확인해주세요.');
    }
}
