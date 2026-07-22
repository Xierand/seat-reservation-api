<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class OrderPaymentNotAllowedException extends Exception
{
    public function __construct(
        string $message = 'Order payment cannot be processed in its current state.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
