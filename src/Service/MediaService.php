<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use App\Repository\MediaRepository;
use DateTimeImmutable;
use DateTimeZone;
use finfo;

final class MediaService
{
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly string $uploadsPath,
        private readonly string $baseUrl,
        private readonly int $maximumBytes,
    ) {
    }

    /**
     * @param list<array{fileName: string, contentType: string, size: int, purpose: string}> $files
     * @return list<array<string, mixed>>
     */
    public function requestUploads(int $userId, array $files): array
    {
        if ($this->maximumBytes < 1 || $this->baseUrl === '') {
            throw new ServiceUnavailableException('MEDIA_POLICY_NOT_CONFIGURED', '이미지 업로드 설정이 완료되지 않았습니다.');
        }

        $uploads = [];

        foreach ($files as $file) {
            if (!isset(self::ALLOWED_TYPES[$file['contentType']]) || $file['size'] < 1 || $file['size'] > $this->maximumBytes) {
                throw new ValidationException('MEDIA_INVALID', '업로드할 이미지를 확인해주세요.');
            }

            $id = 'media_' . bin2hex(random_bytes(12));
            $token = bin2hex(random_bytes(24));
            $extension = self::ALLOWED_TYPES[$file['contentType']];
            $relativePath = 'pending/' . substr($id, 6, 2) . '/' . $id . '.' . $extension;
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $expiresAt = $now->modify('+10 minutes');
            $this->mediaRepository->create(
                $id,
                $userId,
                $file['purpose'],
                $file['fileName'],
                $file['contentType'],
                $file['size'],
                $relativePath,
                hash('sha256', $token),
                $expiresAt->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            );
            $uploads[] = [
                'mediaId' => $id,
                'uploadUrl' => rtrim($this->baseUrl, '/') . '/api/v1/media/uploads/' . rawurlencode($id) . '?token=' . rawurlencode($token),
                'headers' => ['Content-Type' => $file['contentType']],
                'expiresAt' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        return $uploads;
    }

    public function upload(string $id, string $token, string $contentType, string $contents): void
    {
        $row = $this->mediaRepository->findPendingUpload($id, hash('sha256', $token), gmdate('Y-m-d H:i:s'));

        if ($row === null) {
            throw new NotFoundException('UPLOAD_NOT_FOUND', '업로드 요청을 찾을 수 없습니다.');
        }

        if ($contentType !== $row['content_type'] || strlen($contents) !== (int) $row['expected_size']) {
            throw new ValidationException('MEDIA_INVALID', '업로드할 이미지를 확인해주세요.');
        }

        $detectedType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if ($detectedType !== $contentType || @getimagesizefromstring($contents) === false) {
            throw new ValidationException('MEDIA_INVALID', '올바른 이미지 파일이 아닙니다.');
        }

        $path = $this->uploadsPath . '/' . $row['storage_path'];
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ServiceUnavailableException('MEDIA_STORAGE_UNAVAILABLE', '이미지를 저장할 수 없습니다.');
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new ServiceUnavailableException('MEDIA_STORAGE_UNAVAILABLE', '이미지를 저장할 수 없습니다.');
        }

        $this->mediaRepository->markUploaded($id, gmdate('Y-m-d H:i:s'));
    }

    /** @param list<string> $mediaIds */
    public function complete(int $userId, array $mediaIds): array
    {
        foreach ($mediaIds as $mediaId) {
            $row = $this->mediaRepository->findOwned($userId, $mediaId);

            if ($row === null || $row['status'] !== 'UPLOADED') {
                throw new ValidationException('MEDIA_INVALID', '업로드 상태를 확인해주세요.');
            }

            $path = $this->uploadsPath . '/' . $row['storage_path'];

            if (!is_file($path) || filesize($path) !== (int) $row['expected_size']) {
                throw new ValidationException('MEDIA_INVALID', '업로드된 파일을 확인해주세요.');
            }

            $this->mediaRepository->markCompleted($userId, $mediaId, gmdate('Y-m-d H:i:s'));
        }

        return ['mediaIds' => $mediaIds];
    }
}
