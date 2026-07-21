<?php

namespace App\Exceptions;

use Exception;

class OrderPaymentNotAllowedException extends Exception
{
    public function __construct(string $message = 'Order payment cannot be processed in its current state.')
    {
        parent::__construct($message);
    }
}
