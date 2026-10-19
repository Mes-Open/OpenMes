<?php

namespace App\Services\Auth\Sso;

use RuntimeException;

/**
 * A single sign-on attempt that cannot complete. The message is safe to show
 * on the login screen; anything technical belongs in `$detail`, which is only
 * written to the log.
 */
class SsoException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
