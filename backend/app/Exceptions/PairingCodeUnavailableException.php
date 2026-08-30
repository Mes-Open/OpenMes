<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside device enrollment when a pairing code could not be atomically
 * claimed — it was already spent, expired, or a concurrent request won the race.
 * Caught by the controller and surfaced as a 422.
 */
class PairingCodeUnavailableException extends RuntimeException {}
