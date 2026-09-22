<?php

declare(strict_types=1);

namespace App\Exception;

final class ServiceUnavailableException extends ApplicationException
{
    public function __construct(string $errorCode, string $userMessage)
    {
        parent::__construct($errorCode, $userMessage, 503);
    }
}
