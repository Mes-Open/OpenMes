<?php

namespace App\Services\Traceability;

use RuntimeException;

/**
 * A serial binding the plant's rules refuse - carried to the operator as a
 * 422 with the reason. `rebindable` marks the one refusal a supervisor may
 * override: the unit already carries a different process serial.
 */
class BindingException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $rebindable = false)
    {
        parent::__construct($message);
    }
}
