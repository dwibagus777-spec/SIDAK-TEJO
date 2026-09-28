<?php

namespace App\Exceptions;

use RuntimeException;

class ConflictException extends RuntimeException
{
    protected int $httpCode = 409;

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
