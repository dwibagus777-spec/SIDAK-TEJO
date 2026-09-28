<?php

namespace App\Exceptions;

use RuntimeException;

class NotFoundException extends RuntimeException
{
    protected int $httpCode = 404;

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
