<?php

namespace App\Exceptions;

use Exception;

class SeatsNotAvailableException extends Exception
{
    public function __construct(string $message = 'One or more seats are not available.')
    {
        parent::__construct($message);
    }
}
