<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class RateLimitException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'RATE_LIMIT_EXCEEDED',
        string $userMessage = '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 429, $previous);
    }
}
