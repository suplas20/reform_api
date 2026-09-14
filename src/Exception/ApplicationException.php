<?php

declare(strict_types=1);

namespace App\Exception;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

abstract class ApplicationException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly string $userMessage,
        private readonly int $statusCode,
        ?Throwable $previous = null,
    ) {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new InvalidArgumentException('Application exception status must be between 400 and 599.');
        }

        parent::__construct($userMessage, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
