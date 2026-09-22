<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class InquiryRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        int $userId,
        int $businessId,
        string $type,
        ?int $categoryId,
        string $preferredDateType,
        ?string $preferredDate,
        string $message,
        string $now,
    ): int {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO inquiries
                    (user_id, business_id, inquiry_type, item_category_id, preferred_date_type,
                     preferred_date, message, status, created_at, updated_at)
                VALUES
                    (:user_id, :business_id, :inquiry_type, :item_category_id, :preferred_date_type,
                     :preferred_date, :message, :status, :created_at, :updated_at)
                SQL,
        );
        $statement->execute([
            'user_id' => $userId,
            'business_id' => $businessId,
            'inquiry_type' => $type,
            'item_category_id' => $categoryId,
            'preferred_date_type' => $preferredDateType,
            'preferred_date' => $preferredDate,
            'message' => $message,
            'status' => 'WAITING',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param list<string> $mediaIds */
    public function attachMedia(int $inquiryId, int $userId, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $check = $this->connection->prepare(
            <<<'SQL'
                SELECT 1 FROM media_uploads
                WHERE id = :id AND user_id = :user_id AND purpose = :purpose AND status = :status
                LIMIT 1
                SQL,
        );
        $insert = $this->connection->prepare(
            'INSERT INTO inquiry_media (inquiry_id, media_id, sort_order) VALUES (:inquiry_id, :media_id, :sort_order)',
        );

        foreach ($mediaIds as $index => $mediaId) {
            $check->execute(['id' => $mediaId, 'user_id' => $userId, 'purpose' => 'inquiry', 'status' => 'COMPLETED']);

            if ($check->fetchColumn() === false) {
                throw new \InvalidArgumentException('Invalid inquiry media.');
            }

            $insert->execute(['inquiry_id' => $inquiryId, 'media_id' => $mediaId, 'sort_order' => $index]);
        }
    }

    public function countByUserId(int $userId, ?string $status): int
    {
        [$condition, $parameters] = $this->statusCondition($status);
        $statement = $this->connection->prepare(sprintf(
            'SELECT COUNT(*) FROM inquiries WHERE user_id = :user_id%s',
            $condition,
        ));
        $statement->execute(['user_id' => $userId, ...$parameters]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function findPageByUserId(int $userId, ?string $status, int $limit, int $offset): array
    {
        [$condition, $parameters] = $this->statusCondition($status);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT inquiries.id, inquiries.business_id, businesses.name AS business_name,
                    inquiries.inquiry_type, inquiries.status, inquiries.message, inquiries.created_at,
                    (SELECT MAX(inquiry_replies.created_at) FROM inquiry_replies
                     WHERE inquiry_replies.inquiry_id = inquiries.id) AS last_replied_at
                FROM inquiries
                INNER JOIN businesses ON businesses.id = inquiries.business_id
                WHERE inquiries.user_id = :user_id%s
                ORDER BY inquiries.created_at DESC, inquiries.id DESC
                LIMIT :limit OFFSET :offset
                SQL,
            $condition,
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
    public function findByIdForUser(int $inquiryId, int $userId): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT inquiries.*, businesses.name AS business_name
                FROM inquiries
                INNER JOIN businesses ON businesses.id = inquiries.business_id
                WHERE inquiries.id = :id AND inquiries.user_id = :user_id
                LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $inquiryId, 'user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function findReplies(int $inquiryId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, sender_type, sender_name, sender_avatar_path, message, created_at
                FROM inquiry_replies
                WHERE inquiry_id = :inquiry_id
                ORDER BY created_at ASC, id ASC
                SQL,
        );
        $statement->execute(['inquiry_id' => $inquiryId]);

        return $statement->fetchAll();
    }

    /** @return array{string, array<string, string>} */
    private function statusCondition(?string $status): array
    {
        if ($status === null) {
            return ['', []];
        }

        return [' AND inquiries.status = :inquiry_status', ['inquiry_status' => $status]];
    }
}
