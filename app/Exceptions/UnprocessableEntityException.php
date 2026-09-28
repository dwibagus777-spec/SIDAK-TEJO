<?php

namespace App\Exceptions;

use RuntimeException;

class UnprocessableEntityException extends RuntimeException
{
    protected int $httpCode = 422;

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
