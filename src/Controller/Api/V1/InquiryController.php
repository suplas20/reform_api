<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\InquiryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class InquiryController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly InquiryService $inquiryService,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $preferredDateType = RequestValidator::enum(
            $input['preferredDateType'] ?? null,
            ['thisWeek', 'nextWeek', 'thisMonth', 'afterConsultation', 'exactDate'],
        );
        $preferredDate = RequestValidator::optionalString($input['preferredDate'] ?? null, 10);

        if (($preferredDateType === 'exactDate') !== ($preferredDate !== null)) {
            RequestValidator::invalid();
        }

        $mediaIds = $this->mediaIds($input['mediaIds'] ?? []);
        $data = $this->inquiryService->create(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('businessId')),
            RequestValidator::enum($input['type'] ?? null, ['repair', 'quote', 'restoration', 'etc']),
            isset($input['itemCategoryId']) ? RequestValidator::positiveInt($input['itemCategoryId']) : null,
            $preferredDateType,
            $preferredDate,
            RequestValidator::requiredString($input['message'] ?? null, 500),
            $mediaIds,
        );

        return ApiResponse::success($data, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $page = RequestValidator::positiveInt($request->query->get('page'), 1);
        $perPage = RequestValidator::positiveInt($request->query->get('perPage', $request->query->get('limit')), 20, 100);
        $statusValue = $request->query->get('status');
        $status = $statusValue === null ? null : RequestValidator::enum($statusValue, ['waiting', 'inProgress', 'answered', 'closed']);
        $result = $this->inquiryService->list($this->userId($request), $status, $page, $perPage);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->inquiryService->detail(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('inquiryId')),
        ));
    }

    /** @return list<string> */
    private function mediaIds(mixed $value): array
    {
        if (!is_array($value) || count($value) > 5) {
            RequestValidator::invalid();
        }

        return array_map(static fn (mixed $id): string => RequestValidator::requiredString($id, 40), $value);
    }

    private function userId(Request $request): int
    {
        return (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];
    }
}
