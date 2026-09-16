<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exception\BadRequestException;
use App\Exception\ValidationException;
use App\Service\EventRecorder;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class EventController
{
    private const ALLOWED_FIELDS = [
        'sessionKey',
        'eventName',
        'entityType',
        'entityId',
        'metadata',
    ];

    public function __construct(private readonly EventRecorder $eventRecorder)
    {
    }

    public function create(Request $request): Response
    {
        $payload = $this->payload($request);
        $unknownFields = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknownFields !== []) {
            $this->throwValidationError();
        }

        $sessionKey = $this->optionalString($payload, 'sessionKey', 100);
        $eventName = $this->requiredString($payload, 'eventName', 50);
        $entityType = $this->optionalString($payload, 'entityType', 30);

        if ($entityType !== null && preg_match('/\A[A-Z][A-Z0-9_]*\z/', $entityType) !== 1) {
            $this->throwValidationError();
        }

        $entityId = $this->optionalPositiveInt($payload, 'entityId');
        $metadata = $this->metadata($payload);

        $this->eventRecorder->record(
            null,
            $sessionKey,
            $eventName,
            $entityType,
            $entityId,
            $metadata,
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        if ($request->getContentTypeFormat() !== 'json') {
            throw new BadRequestException();
        }

        try {
            $payload = json_decode(
                $request->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new BadRequestException(previous: $exception);
        }

        if (!is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new BadRequestException();
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $field, int $maximumLength): string
    {
        $value = $this->optionalString($payload, $field, $maximumLength);

        if ($value === null) {
            $this->throwValidationError();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalString(
        array $payload,
        string $field,
        int $maximumLength,
    ): ?string {
        $value = $payload[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $this->throwValidationError();
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > $maximumLength) {
            $this->throwValidationError();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalPositiveInt(array $payload, string $field): ?int
    {
        $value = $payload[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            $this->throwValidationError();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function metadata(array $payload): ?array
    {
        $metadata = $payload['metadata'] ?? null;

        if ($metadata === null) {
            return null;
        }

        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            $this->throwValidationError();
        }

        return $metadata;
    }

    private function throwValidationError(): never
    {
        throw new ValidationException(
            'VALIDATION_ERROR',
            '입력값을 확인해주세요.',
        );
    }
}
