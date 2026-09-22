<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use App\Repository\AdRepository;

final class AdService
{
    public function __construct(
        private readonly AdRepository $repository,
        private readonly string $trackingSecret,
    ) {
    }

    public function get(string $placement): ?array
    {
        $row = $this->repository->findActiveByPlacement($placement, gmdate('Y-m-d H:i:s'));

        if ($row === null) {
            return null;
        }

        $adId = (int) $row['id'];

        return [
            'id' => $adId,
            'placement' => (string) $row['placement'],
            'advertiser' => (string) $row['advertiser'],
            'headline' => (string) $row['headline'],
            'body' => $row['body'] === null ? null : (string) $row['body'],
            'imageUrl' => (string) $row['image_url'],
            'callToAction' => (string) $row['call_to_action'],
            'targetUrl' => (string) $row['target_url'],
            'trackingToken' => $this->sign($adId),
        ];
    }

    public function record(int $adId, ?int $userId, string $eventId, string $eventType, string $trackingToken): void
    {
        if (!$this->verify($adId, $trackingToken)) {
            throw new ValidationException('TRACKING_TOKEN_INVALID', '광고 추적 정보가 올바르지 않습니다.');
        }

        if (!$this->repository->activeExists($adId, gmdate('Y-m-d H:i:s'))) {
            throw new NotFoundException('AD_NOT_FOUND', '광고를 찾을 수 없습니다.');
        }

        $this->repository->recordEvent($adId, $userId, $eventId, $eventType, gmdate('Y-m-d H:i:s'));
    }

    private function sign(int $adId): string
    {
        if ($this->trackingSecret === '') {
            throw new ServiceUnavailableException('AD_TRACKING_NOT_CONFIGURED', '광고 추적 설정이 완료되지 않았습니다.');
        }

        $payload = $adId . '.' . time();

        return self::base64Url($payload . '.' . hash_hmac('sha256', $payload, $this->trackingSecret));
    }

    private function verify(int $adId, string $token): bool
    {
        if ($this->trackingSecret === '') {
            return false;
        }

        $base64 = strtr($token, '-_', '+/');
        $padding = strlen($base64) % 4;

        if ($padding !== 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        if (!is_string($decoded)) {
            return false;
        }

        $parts = explode('.', $decoded);

        if (count($parts) !== 3 || (int) $parts[0] !== $adId || !ctype_digit($parts[1])) {
            return false;
        }

        $payload = $parts[0] . '.' . $parts[1];

        return time() - (int) $parts[1] <= 86400
            && hash_equals(hash_hmac('sha256', $payload, $this->trackingSecret), $parts[2]);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
