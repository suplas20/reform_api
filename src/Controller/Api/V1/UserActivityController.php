<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Http\ApiResponse;
use App\Http\RequestValidator;
use App\Service\AuthService;
use App\Service\UserActivityService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserActivityController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserActivityService $service,
    ) {
    }

    public function recent(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->service->recent($this->userId($request), $page, $perPage);

        return ApiResponse::list($result['items'], $page, $perPage, $result['total'], $result['totalPages']);
    }

    public function touchRecent(Request $request): Response
    {
        $this->service->touchRecent(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('businessId')),
        );

        return new Response('', 204);
    }

    public function clearRecent(Request $request): Response
    {
        $this->service->clearRecent($this->userId($request));

        return new Response('', 204);
    }

    public function notifications(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pagination($request);
        $typeValue = $request->query->get('type');
        $type = $typeValue === null ? null : RequestValidator::enum($typeValue, ['inquiry', 'review', 'event', 'system']);
        $unreadOnly = RequestValidator::optionalBoolean($request->query->get('unreadOnly')) ?? false;
        $result = $this->service->notifications($this->userId($request), $type, $unreadOnly, $page, $perPage);

        return ApiResponse::list(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
            $result['totalPages'],
            ['unreadCount' => $result['unreadCount']],
        );
    }

    public function notification(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->notification(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('notificationId')),
        ));
    }

    public function markRead(Request $request): Response
    {
        $this->service->markRead(
            $this->userId($request),
            RequestValidator::positiveInt($request->attributes->get('notificationId')),
        );

        return new Response('', 204);
    }

    public function markAllRead(Request $request): Response
    {
        $this->service->markAllRead($this->userId($request));

        return new Response('', 204);
    }

    public function settings(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->settings($this->userId($request)));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $input = RequestValidator::json($request);
        $changes = [];

        foreach ([
            'pushEnabled',
            'inquiryNotificationsEnabled',
            'reviewNotificationsEnabled',
            'marketingConsent',
            'nearbyRecommendationsEnabled',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = RequestValidator::optionalBoolean($input[$field]);

                if ($changes[$field] === null) {
                    RequestValidator::invalid();
                }
            }
        }

        return ApiResponse::success($this->service->updateSettings($this->userId($request), $changes));
    }

    public function putDevice(Request $request): Response
    {
        $input = RequestValidator::json($request);
        $this->service->saveDevice(
            $this->userId($request),
            RequestValidator::requiredString($request->attributes->get('deviceId'), 100),
            [
                'platform' => RequestValidator::enum($input['platform'] ?? null, ['ios', 'android']),
                'pushToken' => RequestValidator::requiredString($input['pushToken'] ?? null, 500),
                'appVersion' => RequestValidator::requiredString($input['appVersion'] ?? null, 30),
                'locale' => RequestValidator::optionalString($input['locale'] ?? null, 20),
                'timezone' => RequestValidator::optionalString($input['timezone'] ?? null, 50),
            ],
        );

        return new Response('', 204);
    }

    public function deleteDevice(Request $request): Response
    {
        $this->service->deleteDevice(
            $this->userId($request),
            RequestValidator::requiredString($request->attributes->get('deviceId'), 100),
        );

        return new Response('', 204);
    }

    /** @return array{int, int} */
    private function pagination(Request $request): array
    {
        return [
            RequestValidator::positiveInt($request->query->get('page'), 1),
            RequestValidator::positiveInt($request->query->get('perPage', $request->query->get('limit')), 20, 100),
        ];
    }

    private function userId(Request $request): int
    {
        return (int) $this->authService->requireUser($request->headers->get('Authorization'))['id'];
    }
}
