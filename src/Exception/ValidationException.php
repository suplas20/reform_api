<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class ValidationException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'VALIDATION_ERROR',
        string $userMessage = '입력값을 확인해 주세요.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 422, $previous);
    }
}
