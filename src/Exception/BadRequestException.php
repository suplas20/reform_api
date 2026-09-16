<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class BadRequestException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'BAD_REQUEST',
        string $userMessage = '요청 형식이 올바르지 않습니다.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 400, $previous);
    }
}
