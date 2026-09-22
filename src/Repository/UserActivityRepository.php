<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserActivityRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function countRecent(int $userId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM recent_businesses WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array{business_id: mixed, viewed_at: mixed}> */
    public function findRecentPage(int $userId, int $limit, int $offset): array
    {
        $statement = $this->connection->prepare(
            'SELECT business_id, viewed_at FROM recent_businesses WHERE user_id = :user_id ORDER BY viewed_at DESC, business_id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function touchRecent(int $userId, int $businessId, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO recent_businesses (user_id, business_id, viewed_at)
                VALUES (:user_id, :business_id, :viewed_at)
                ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)
                SQL,
        );
        $statement->execute(['user_id' => $userId, 'business_id' => $businessId, 'viewed_at' => $now]);
    }

    public function clearRecent(int $userId): void
    {
        $statement = $this->connection->prepare('DELETE FROM recent_businesses WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }

    /** @return array{total: int, unread: int} */
    public function countNotifications(int $userId, ?string $type, bool $unreadOnly): array
    {
        [$conditions, $parameters] = $this->notificationConditions($type, $unreadOnly);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT COUNT(*) AS total,
                    SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread
                FROM notifications
                WHERE user_id = :user_id %s
                SQL,
            implode(' ', $conditions),
        ));
        $statement->execute(['user_id' => $userId, ...$parameters]);
        $row = $statement->fetch();

        return ['total' => (int) $row['total'], 'unread' => (int) $row['unread']];
    }

    /** @return list<array<string, mixed>> */
    public function findNotificationPage(int $userId, ?string $type, bool $unreadOnly, int $limit, int $offset): array
    {
        [$conditions, $parameters] = $this->notificationConditions($type, $unreadOnly);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT id, notification_type, title, body, image_path, deep_link, read_at, created_at
                FROM notifications
                WHERE user_id = :user_id %s
                ORDER BY created_at DESC, id DESC
                LIMIT :limit OFFSET :offset
                SQL,
            implode(' ', $conditions),
        ));
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findNotification(int $userId, int $notificationId): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, notification_type, title, body, image_path, deep_link, read_at, created_at
                FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function markNotificationRead(int $userId, int $notificationId, string $now): void
    {
        $statement = $this->connection->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, :read_at) WHERE id = :id AND user_id = :user_id',
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId, 'read_at' => $now]);
    }

    public function markAllNotificationsRead(int $userId, string $now): void
    {
        $statement = $this->connection->prepare(
            'UPDATE notifications SET read_at = :read_at WHERE user_id = :user_id AND read_at IS NULL',
        );
        $statement->execute(['user_id' => $userId, 'read_at' => $now]);
    }

    /** @return array<string, mixed>|null */
    public function findSettings(int $userId): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM user_settings WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, bool> $settings */
    public function saveSettings(int $userId, array $settings, string $now): void
    {
        $current = $this->findSettings($userId) ?? [
            'push_enabled' => 1,
            'inquiry_notifications_enabled' => 1,
            'review_notifications_enabled' => 1,
            'marketing_consent' => 0,
            'nearby_recommendations_enabled' => 1,
        ];
        $values = [
            'pushEnabled' => (int) ($settings['pushEnabled'] ?? (bool) $current['push_enabled']),
            'inquiryNotificationsEnabled' => (int) ($settings['inquiryNotificationsEnabled'] ?? (bool) $current['inquiry_notifications_enabled']),
            'reviewNotificationsEnabled' => (int) ($settings['reviewNotificationsEnabled'] ?? (bool) $current['review_notifications_enabled']),
            'marketingConsent' => (int) ($settings['marketingConsent'] ?? (bool) $current['marketing_consent']),
            'nearbyRecommendationsEnabled' => (int) ($settings['nearbyRecommendationsEnabled'] ?? (bool) $current['nearby_recommendations_enabled']),
        ];
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO user_settings
                    (user_id, push_enabled, inquiry_notifications_enabled, review_notifications_enabled,
                     marketing_consent, nearby_recommendations_enabled, updated_at)
                VALUES
                    (:user_id, :push_enabled, :inquiry_enabled, :review_enabled,
                     :marketing_consent, :nearby_enabled, :updated_at)
                ON DUPLICATE KEY UPDATE
                    push_enabled = VALUES(push_enabled),
                    inquiry_notifications_enabled = VALUES(inquiry_notifications_enabled),
                    review_notifications_enabled = VALUES(review_notifications_enabled),
                    marketing_consent = VALUES(marketing_consent),
                    nearby_recommendations_enabled = VALUES(nearby_recommendations_enabled),
                    updated_at = VALUES(updated_at)
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'push_enabled' => $values['pushEnabled'],
            'inquiry_enabled' => $values['inquiryNotificationsEnabled'],
            'review_enabled' => $values['reviewNotificationsEnabled'],
            'marketing_consent' => $values['marketingConsent'],
            'nearby_enabled' => $values['nearbyRecommendationsEnabled'],
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, string|null> $device */
    public function saveDevice(int $userId, string $deviceId, array $device, string $now): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO user_devices
                    (user_id, device_id, platform, push_token, app_version, locale, timezone, created_at, updated_at)
                VALUES
                    (:user_id, :device_id, :platform, :push_token, :app_version, :locale, :timezone, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    platform = VALUES(platform), push_token = VALUES(push_token), app_version = VALUES(app_version),
                    locale = VALUES(locale), timezone = VALUES(timezone), updated_at = VALUES(updated_at)
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'platform' => $device['platform'],
            'push_token' => $device['pushToken'],
            'app_version' => $device['appVersion'],
            'locale' => $device['locale'],
            'timezone' => $device['timezone'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function deleteDevice(int $userId, string $deviceId): void
    {
        $statement = $this->connection->prepare('DELETE FROM user_devices WHERE user_id = :user_id AND device_id = :device_id');
        $statement->execute(['user_id' => $userId, 'device_id' => $deviceId]);
    }

    /** @return array{list<string>, array<string, string>} */
    private function notificationConditions(?string $type, bool $unreadOnly): array
    {
        $conditions = [];
        $parameters = [];

        if ($type !== null) {
            $conditions[] = 'AND notification_type = :notification_type';
            $parameters['notification_type'] = strtoupper($type);
        }

        if ($unreadOnly) {
            $conditions[] = 'AND read_at IS NULL';
        }

        return [$conditions, $parameters];
    }
}
