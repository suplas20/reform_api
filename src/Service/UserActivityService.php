<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Repository\BusinessReadRepository;
use App\Repository\UserActivityRepository;
use DateTimeImmutable;
use DateTimeZone;

final class UserActivityService
{
    public function __construct(
        private readonly UserActivityRepository $repository,
        private readonly BusinessReadRepository $businessRepository,
        private readonly BusinessService $businessService,
    ) {
    }

    public function recent(int $userId, int $page, int $perPage): array
    {
        $total = $this->repository->countRecent($userId);
        $rows = $this->repository->findRecentPage($userId, $perPage, ($page - 1) * $perPage);
        $viewedAt = [];
        $ids = [];

        foreach ($rows as $row) {
            $id = (int) $row['business_id'];
            $ids[] = $id;
            $viewedAt[$id] = self::utcDateTime((string) $row['viewed_at']);
        }

        $items = array_map(static fn (array $business): array => [
            'viewedAt' => $viewedAt[$business['id']],
            'business' => $business,
        ], $this->businessService->getPublicSummariesByIds($ids));

        return ['items' => $items, 'total' => $total, 'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage)];
    }

    public function touchRecent(int $userId, int $businessId): void
    {
        if ($this->businessRepository->findPublicDetailById($businessId, gmdate('Y-m-d H:i:s')) === null) {
            throw new NotFoundException('BUSINESS_NOT_FOUND', '업체를 찾을 수 없습니다.');
        }

        $this->repository->touchRecent($userId, $businessId, gmdate('Y-m-d H:i:s'));
    }

    public function clearRecent(int $userId): void
    {
        $this->repository->clearRecent($userId);
    }

    public function notifications(int $userId, ?string $type, bool $unreadOnly, int $page, int $perPage): array
    {
        $counts = $this->repository->countNotifications($userId, $type, $unreadOnly);
        $rows = $this->repository->findNotificationPage($userId, $type, $unreadOnly, $perPage, ($page - 1) * $perPage);

        return [
            'items' => array_map([self::class, 'mapNotification'], $rows),
            'total' => $counts['total'],
            'unreadCount' => $counts['unread'],
            'totalPages' => $counts['total'] === 0 ? 0 : (int) ceil($counts['total'] / $perPage),
        ];
    }

    public function notification(int $userId, int $notificationId): array
    {
        $row = $this->repository->findNotification($userId, $notificationId);

        if ($row === null) {
            throw new NotFoundException('NOTIFICATION_NOT_FOUND', '알림을 찾을 수 없습니다.');
        }

        return self::mapNotification($row);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $this->notification($userId, $notificationId);
        $this->repository->markNotificationRead($userId, $notificationId, gmdate('Y-m-d H:i:s'));
    }

    public function markAllRead(int $userId): void
    {
        $this->repository->markAllNotificationsRead($userId, gmdate('Y-m-d H:i:s'));
    }

    public function settings(int $userId): array
    {
        $row = $this->repository->findSettings($userId) ?? [];

        return [
            'pushEnabled' => !isset($row['push_enabled']) || (bool) $row['push_enabled'],
            'inquiryNotificationsEnabled' => !isset($row['inquiry_notifications_enabled']) || (bool) $row['inquiry_notifications_enabled'],
            'reviewNotificationsEnabled' => !isset($row['review_notifications_enabled']) || (bool) $row['review_notifications_enabled'],
            'marketingConsent' => isset($row['marketing_consent']) && (bool) $row['marketing_consent'],
            'nearbyRecommendationsEnabled' => !isset($row['nearby_recommendations_enabled']) || (bool) $row['nearby_recommendations_enabled'],
        ];
    }

    /** @param array<string, bool> $changes */
    public function updateSettings(int $userId, array $changes): array
    {
        $this->repository->saveSettings($userId, $changes, gmdate('Y-m-d H:i:s'));

        return $this->settings($userId);
    }

    /** @param array<string, string|null> $device */
    public function saveDevice(int $userId, string $deviceId, array $device): void
    {
        $this->repository->saveDevice($userId, $deviceId, $device, gmdate('Y-m-d H:i:s'));
    }

    public function deleteDevice(int $userId, string $deviceId): void
    {
        $this->repository->deleteDevice($userId, $deviceId);
    }

    private static function mapNotification(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => strtolower((string) $row['notification_type']),
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'imageUrl' => null,
            'isRead' => $row['read_at'] !== null,
            'createdAt' => self::utcDateTime((string) $row['created_at']),
            'deepLink' => $row['deep_link'] === null ? null : (string) $row['deep_link'],
        ];
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
