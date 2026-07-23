<?php

namespace App\Exceptions;

use Exception;

class SeatGenerationConflictException extends Exception
{
    public function __construct(string $message = 'Generated seats conflict with existing seats in this sector.')
    {
        parent::__construct($message);
    }
}
