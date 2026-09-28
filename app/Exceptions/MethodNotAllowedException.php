<?php

namespace App\Exceptions;

use RuntimeException;

class MethodNotAllowedException extends RuntimeException
{
    protected int $httpCode = 405;

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
