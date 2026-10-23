<?php

namespace App\Exceptions;

use RuntimeException;

class XeroRateLimitException extends RuntimeException
{
    public string $type; // 'minute' atau 'day'

    public function __construct(string $type, string $message = '')
    {
        parent::__construct($message ?: "Xero rate limit ({$type})");
        $this->type = $type;
    }
}