<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\HelpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class HelpController
{
    public function __construct(private readonly HelpService $service)
    {
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success($this->service->categories());
    }

    public function faqs(Request $request): JsonResponse
    {
        $page = RequestValidator::positiveInt($request->query->get('page'), 1);
        $perPage = RequestValidator::positiveInt($request->query->get('perPage', $request->query->get('limit')), 20, 100);
        $categoryValue = $request->query->get('categoryId');
        $categoryId = $categoryValue === null ? null : RequestValidator::positiveInt($categoryValue);
        $keyword = RequestValidator::optionalString($request->query->get('query'), 200);
        $result = $this->service->faqs($categoryId, $keyword, $page, $perPage);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    public function faq(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->faq(
            RequestValidator::positiveInt($request->attributes->get('faqId')),
        ));
    }
}
