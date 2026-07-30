<?php

namespace App\Exceptions;

use Exception;

class InvalidPrefixException extends Exception
{
    protected array $allowedPrefixes;

    public function __construct(string $message, array $allowedPrefixes, int $code = 400, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->allowedPrefixes = $allowedPrefixes;
    }

    public function getAllowedPrefixes(): array
    {
        return $this->allowedPrefixes;
    }
}
