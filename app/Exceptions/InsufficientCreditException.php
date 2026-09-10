<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditException extends RuntimeException
{
    public function __construct(public readonly int $required)
    {
        parent::__construct("This action requires {$required} credits but your balance is too low.");
    }
}
