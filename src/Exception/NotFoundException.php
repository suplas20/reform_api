<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

final class NotFoundException extends ApplicationException
{
    public function __construct(
        string $errorCode = 'NOT_FOUND',
        string $userMessage = '요청한 정보를 찾을 수 없습니다.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, $userMessage, 404, $previous);
    }
}
