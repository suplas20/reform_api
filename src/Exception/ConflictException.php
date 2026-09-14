<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class ConflictException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'CONFLICT',
        string $userMessage = '요청이 현재 상태와 충돌합니다.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 409, $previous);
    }
}
