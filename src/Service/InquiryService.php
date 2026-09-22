<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\BusinessReadRepository;
use App\Repository\InquiryRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class InquiryService
{
    public function __construct(
        private readonly InquiryRepository $inquiryRepository,
        private readonly BusinessReadRepository $businessRepository,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /** @param list<string> $mediaIds */
    public function create(
        int $userId,
        int $businessId,
        string $type,
        ?int $categoryId,
        string $preferredDateType,
        ?string $preferredDate,
        string $message,
        array $mediaIds,
    ): array {
        if ($this->businessRepository->findPublicDetailById($businessId, gmdate('Y-m-d H:i:s')) === null) {
            throw new NotFoundException('BUSINESS_NOT_FOUND', '업체를 찾을 수 없습니다.');
        }

        try {
            $inquiryId = $this->transactionManager->run(function () use (
                $userId,
                $businessId,
                $type,
                $categoryId,
                $preferredDateType,
                $preferredDate,
                $message,
                $mediaIds,
            ): int {
                $now = gmdate('Y-m-d H:i:s');
                $id = $this->inquiryRepository->create(
                    $userId,
                    $businessId,
                    strtoupper($type),
                    $categoryId,
                    self::preferredDateTypeDatabase($preferredDateType),
                    $preferredDate,
                    $message,
                    $now,
                );
                $this->inquiryRepository->attachMedia($id, $userId, $mediaIds);

                return $id;
            });
        } catch (InvalidArgumentException $exception) {
            throw new ValidationException('MEDIA_INVALID', '첨부 이미지를 확인해주세요.', $exception);
        }

        return $this->detail($userId, $inquiryId);
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function list(int $userId, ?string $status, int $page, int $perPage): array
    {
        $databaseStatus = match ($status) {
            null => null,
            'inProgress' => 'IN_PROGRESS',
            default => strtoupper($status),
        };
        $total = $this->inquiryRepository->countByUserId($userId, $databaseStatus);
        $rows = $this->inquiryRepository->findPageByUserId(
            $userId,
            $databaseStatus,
            $perPage,
            ($page - 1) * $perPage,
        );

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'businessId' => (int) $row['business_id'],
                'businessName' => (string) $row['business_name'],
                'businessThumbnailUrl' => null,
                'title' => self::title((string) $row['message']),
                'type' => strtolower((string) $row['inquiry_type']),
                'status' => self::status((string) $row['status']),
                'createdAt' => self::utcDateTime((string) $row['created_at']),
                'lastRepliedAt' => $row['last_replied_at'] === null ? null : self::utcDateTime((string) $row['last_replied_at']),
            ], $rows),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(int $userId, int $inquiryId): array
    {
        $row = $this->inquiryRepository->findByIdForUser($inquiryId, $userId);

        if ($row === null) {
            throw new NotFoundException('INQUIRY_NOT_FOUND', '문의를 찾을 수 없습니다.');
        }

        return [
            'id' => (int) $row['id'],
            'businessId' => (int) $row['business_id'],
            'businessName' => (string) $row['business_name'],
            'type' => strtolower((string) $row['inquiry_type']),
            'status' => self::status((string) $row['status']),
            'itemCategoryId' => $row['item_category_id'] === null ? null : (int) $row['item_category_id'],
            'preferredDateType' => self::preferredDateType((string) $row['preferred_date_type']),
            'preferredDate' => $row['preferred_date'] === null ? null : (string) $row['preferred_date'],
            'message' => (string) $row['message'],
            'imageUrls' => [],
            'createdAt' => self::utcDateTime((string) $row['created_at']),
            'replies' => array_map(static fn (array $reply): array => [
                'id' => (int) $reply['id'],
                'senderType' => strtolower((string) $reply['sender_type']),
                'senderName' => (string) $reply['sender_name'],
                'senderAvatarUrl' => null,
                'message' => (string) $reply['message'],
                'createdAt' => self::utcDateTime((string) $reply['created_at']),
            ], $this->inquiryRepository->findReplies($inquiryId)),
        ];
    }

    private static function status(string $status): string
    {
        return match ($status) {
            'IN_PROGRESS' => 'inProgress',
            default => strtolower($status),
        };
    }

    private static function preferredDateType(string $value): string
    {
        return match ($value) {
            'THIS_WEEK' => 'thisWeek',
            'NEXT_WEEK' => 'nextWeek',
            'THIS_MONTH' => 'thisMonth',
            'AFTER_CONSULTATION' => 'afterConsultation',
            'EXACT_DATE' => 'exactDate',
            default => strtolower($value),
        };
    }

    private static function preferredDateTypeDatabase(string $value): string
    {
        return match ($value) {
            'thisWeek' => 'THIS_WEEK',
            'nextWeek' => 'NEXT_WEEK',
            'thisMonth' => 'THIS_MONTH',
            'afterConsultation' => 'AFTER_CONSULTATION',
            'exactDate' => 'EXACT_DATE',
        };
    }

    private static function title(string $message): string
    {
        return $message;
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
