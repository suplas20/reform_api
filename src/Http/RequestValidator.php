<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\BadRequestException;
use App\Exception\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

final class RequestValidator
{
    /** @return array<string, mixed> */
    public static function json(Request $request): array
    {
        try {
            $value = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BadRequestException('INVALID_REQUEST', '요청 형식이 올바르지 않습니다.', $exception);
        }

        if (!is_array($value)) {
            throw new BadRequestException('INVALID_REQUEST', '요청 형식이 올바르지 않습니다.');
        }

        return $value;
    }

    public static function positiveInt(mixed $value, ?int $default = null, int $maximum = PHP_INT_MAX): int
    {
        if ($value === null && $default !== null) {
            return $default;
        }

        if (!is_int($value) && !is_string($value)) {
            self::invalid();
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $maximum],
        ]);

        if ($validated === false) {
            self::invalid();
        }

        return $validated;
    }

    public static function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            self::invalid();
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > $maximum) {
            self::invalid();
        }

        return $value;
    }

    public static function requiredString(mixed $value, int $maximum): string
    {
        $value = self::optionalString($value, $maximum);

        if ($value === null) {
            self::invalid();
        }

        return $value;
    }

    public static function optionalBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (!is_bool($value) && !is_int($value) && !is_string($value)) {
            self::invalid();
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($validated === null) {
            self::invalid();
        }

        return $validated;
    }

    /** @param list<string> $allowed */
    public static function enum(mixed $value, array $allowed, ?string $default = null): string
    {
        if ($value === null && $default !== null) {
            return $default;
        }

        if (!is_string($value) || !in_array($value, $allowed, true)) {
            self::invalid();
        }

        return $value;
    }

    public static function invalid(): never
    {
        throw new ValidationException('VALIDATION_ERROR', '입력값을 확인해주세요.');
    }
}
