<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use App\Exception\ConflictException;
use App\Database\TransactionManager;
use App\Repository\ReviewReadRepository;
use App\Repository\ReviewWriteRepository;
use InvalidArgumentException;
use PDOException;
use DateTimeImmutable;
use DateTimeZone;

final class ReviewService
{
    public function __construct(
        private readonly ReviewReadRepository $reviewRepository,
        private readonly ?ReviewWriteRepository $reviewWriteRepository = null,
        private readonly ?TransactionManager $transactionManager = null,
    ) {
    }

    /**
     * @param list<string> $tags
     * @param list<string> $mediaIds
     * @return array<string, mixed>
     */
    public function create(
        int $userId,
        int $businessId,
        int $rating,
        ?string $content,
        array $tags,
        array $mediaIds,
        ?string $idempotencyKey = null,
    ): array {
        if ($this->reviewWriteRepository === null || $this->transactionManager === null) {
            throw new ServiceUnavailableException('REVIEW_WRITE_UNAVAILABLE', '리뷰 작성 기능을 사용할 수 없습니다.');
        }

        $this->assertPublicBusinessExists($businessId);

        $requestHash = $idempotencyKey === null ? null : hash('sha256', json_encode([
            'businessId' => $businessId,
            'rating' => $rating,
            'content' => $content,
            'tags' => $tags,
            'mediaIds' => $mediaIds,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            $result = $this->transactionManager->run(function () use (
                $userId,
                $businessId,
                $rating,
                $content,
                $tags,
                $mediaIds,
                $idempotencyKey,
                $requestHash,
            ): array {
                if ($idempotencyKey !== null) {
                    $existing = $this->reviewWriteRepository->findByIdempotencyKey($userId, $idempotencyKey);

                    if ($existing !== null) {
                        $this->assertMatchingIdempotentRequest($existing, $requestHash);

                        return ['id' => (int) $existing['id'], 'createdAt' => (string) $existing['created_at']];
                    }
                }

                $now = gmdate('Y-m-d H:i:s');
                $id = $this->reviewWriteRepository->createReview(
                    $userId,
                    $businessId,
                    $rating,
                    $content,
                    $idempotencyKey,
                    $requestHash,
                    $now,
                );
                $this->reviewWriteRepository->addTags($id, $tags);
                $this->reviewWriteRepository->addCompletedMedia($id, $userId, $mediaIds, $now);
                $this->reviewWriteRepository->refreshBusinessStats($businessId, $now);

                return ['id' => $id, 'createdAt' => $now];
            });
        } catch (InvalidArgumentException $exception) {
            throw new ValidationException('MEDIA_INVALID', '첨부 이미지를 확인해주세요.', $exception);
        } catch (PDOException $exception) {
            if ($idempotencyKey === null || $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->reviewWriteRepository->findByIdempotencyKey($userId, $idempotencyKey);

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingIdempotentRequest($existing, $requestHash);
            $result = ['id' => (int) $existing['id'], 'createdAt' => (string) $existing['created_at']];
        }

        return [
            'id' => $result['id'],
            'businessId' => $businessId,
            'rating' => $rating,
            'content' => $content,
            'tags' => $tags,
            'images' => [],
            'createdAt' => self::utcDateTime($result['createdAt']),
        ];
    }

    /** @param array{id: mixed, request_hash: mixed, created_at: mixed} $existing */
    private function assertMatchingIdempotentRequest(array $existing, ?string $requestHash): void
    {
        if (!is_string($existing['request_hash']) || !is_string($requestHash) || !hash_equals($existing['request_hash'], $requestHash)) {
            throw new ConflictException(
                'IDEMPOTENCY_KEY_REUSED',
                '같은 Idempotency-Key를 다른 요청에 사용할 수 없습니다.',
            );
        }
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function getUserReviews(int $userId, string $type, int $page, int $perPage): array
    {
        if ($this->reviewWriteRepository === null) {
            throw new ServiceUnavailableException('REVIEW_READ_UNAVAILABLE', '내 리뷰를 조회할 수 없습니다.');
        }

        $total = $this->reviewWriteRepository->countByUserId($userId, $type);
        $rows = $this->reviewWriteRepository->findPageByUserId($userId, $type, $perPage, ($page - 1) * $perPage);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'businessId' => (int) $row['business_id'],
                'businessName' => (string) $row['business_name'],
                'businessThumbnailUrl' => null,
                'rating' => (int) $row['rating'],
                'content' => $row['content'] === null ? null : (string) $row['content'],
                'imageUrls' => [],
                'createdAt' => self::utcDateTime((string) $row['created_at']),
                'updatedAt' => self::utcDateTime((string) $row['updated_at']),
            ], $rows),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{items: list<array<string, mixed>>, total: int, totalPages: int, summary: array<string, mixed>}
     */
    public function getPublicList(
        int $businessId,
        int $page,
        int $perPage,
        array $criteria,
    ): array {
        $this->assertPublicBusinessExists($businessId);
        $total = $this->reviewRepository->countPublicByBusinessId($businessId, $criteria);

        return [
            'items' => array_map(
                static fn (array $row): array => self::mapReview($row),
                $this->reviewRepository->findPublicPageByBusinessId(
                    $businessId,
                    $perPage,
                    ($page - 1) * $perPage,
                    $criteria,
                ),
            ),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            'summary' => $this->mapSummary(
                $this->reviewRepository->summarizePublicByBusinessId($businessId),
            ),
        ];
    }

    /** @return array{average: ?float, total: int, distribution: list<array{score: int, count: int, ratio: float}>} */
    public function getPublicSummary(int $businessId): array
    {
        $this->assertPublicBusinessExists($businessId);
        $summary = $this->mapSummary(
            $this->reviewRepository->summarizePublicByBusinessId($businessId),
        );
        $total = $summary['reviewCount'];
        $distribution = [];

        for ($score = 5; $score >= 1; $score--) {
            $count = $summary['distribution'][(string) $score];
            $distribution[] = [
                'score' => $score,
                'count' => $count,
                'ratio' => $total === 0 ? 0.0 : $count / $total,
            ];
        }

        return [
            'average' => $summary['averageRating'],
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    private function assertPublicBusinessExists(int $businessId): void
    {
        if (!$this->reviewRepository->publicBusinessExists($businessId, gmdate('Y-m-d H:i:s'))) {
            throw new NotFoundException('BUSINESS_NOT_FOUND', '업체를 찾을 수 없습니다.');
        }
    }

    /** @param array<string, mixed> $row */
    private static function mapReview(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'rating' => (int) $row['rating'],
            'content' => $row['content'] === null ? null : (string) $row['content'],
            'user' => [
                'id' => (int) $row['user_id'],
                'name' => $row['user_name'] === null ? null : (string) $row['user_name'],
                // Public profile image URL policy is not finalized.
                'profileImage' => null,
            ],
            'images' => [],
            'createdAt' => self::utcDateTime((string) $row['created_at']),
            'updatedAt' => self::utcDateTime((string) $row['updated_at']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{averageRating: ?float, reviewCount: int, distribution: array<string, int>}
     */
    private function mapSummary(array $row): array
    {
        return [
            'averageRating' => $row['average_rating'] === null ? null : (float) $row['average_rating'],
            'reviewCount' => (int) $row['review_count'],
            'distribution' => [
                '5' => (int) $row['rating_5'],
                '4' => (int) $row['rating_4'],
                '3' => (int) $row['rating_3'],
                '2' => (int) $row['rating_2'],
                '1' => (int) $row['rating_1'],
            ],
        ];
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
