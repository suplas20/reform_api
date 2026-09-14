<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class UnauthorizedException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'UNAUTHORIZED',
        string $userMessage = '인증이 필요합니다.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 401, $previous);
    }
}
