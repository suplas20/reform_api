<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Repository\EventLogStore;

final class EventService implements EventRecorder
{
    private const EVENT_NAMES = [
        'SEARCH',
        'FILTER',
        'BUSINESS_IMPRESSION',
        'BUSINESS_VIEW',
        'BUSINESS_CLICK',
        'FAVORITE',
        'CALL_CLICK',
        'MAP_CLICK',
        'WEBSITE_CLICK',
        'SNS_CLICK',
        'CASE_VIEW',
        'SHARE',
        'EXTERNAL_GOOGLE_CLICK',
        'EXTERNAL_NAVER_CLICK',
        'REVIEW_VIEW',
        'REVIEW_CREATE',
        'REVIEW_UPDATE',
        'REVIEW_DELETE',
    ];

    private const SENSITIVE_METADATA_KEYS = [
        'accesstoken',
        'refreshtoken',
        'providertoken',
        'password',
        'apikey',
        'authorization',
        'sessionid',
    ];

    private const MAX_METADATA_BYTES = 65_535;

    public function __construct(private readonly EventLogStore $eventLogRepository)
    {
    }

    public function record(
        ?int $userId,
        ?string $sessionKey,
        string $eventName,
        ?string $entityType,
        ?int $entityId,
        ?array $metadata,
    ): void {
        if (!in_array($eventName, self::EVENT_NAMES, true)) {
            $this->throwValidationError();
        }

        if ($metadata !== null && $this->containsSensitiveKey($metadata)) {
            $this->throwValidationError();
        }

        $encodedMetadata = $metadata === null
            ? null
            : json_encode(
                $metadata === [] ? (object) [] : $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

        if ($encodedMetadata !== null && strlen($encodedMetadata) > self::MAX_METADATA_BYTES) {
            $this->throwValidationError();
        }

        $this->eventLogRepository->create(
            $userId,
            $sessionKey,
            $eventName,
            $entityType,
            $entityId,
            $encodedMetadata,
            gmdate('Y-m-d H:i:s'),
        );
    }

    /**
     * @param array<array-key, mixed> $metadata
     */
    private function containsSensitiveKey(array $metadata): bool
    {
        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

                if (in_array($normalizedKey, self::SENSITIVE_METADATA_KEYS, true)) {
                    return true;
                }
            }

            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }

    private function throwValidationError(): never
    {
        throw new ValidationException(
            'VALIDATION_ERROR',
            '입력값을 확인해주세요.',
        );
    }
}
