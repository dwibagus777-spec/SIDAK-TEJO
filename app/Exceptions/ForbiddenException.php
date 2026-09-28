<?php

namespace App\Exceptions;

use RuntimeException;

class ForbiddenException extends RuntimeException
{
    protected int $httpCode = 403;

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
