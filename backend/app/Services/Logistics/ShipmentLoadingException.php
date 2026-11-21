<?php

namespace App\Services\Logistics;

/**
 * A shipment write the domain refuses — the load is no longer on the dock, or
 * an edit would drop a unit that is already on the truck. Answered as a 422.
 */
class ShipmentLoadingException extends \RuntimeException {}
