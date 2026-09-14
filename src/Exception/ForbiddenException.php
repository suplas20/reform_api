<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class ForbiddenException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'FORBIDDEN',
        string $userMessage = '요청을 수행할 권한이 없습니다.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 403, $previous);
    }
}
