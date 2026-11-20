<?php

namespace App\Services\Logistics;

/**
 * The ERP tried to replace a shipment the dock has already started (or
 * finished). Answered as a 409.
 */
class ShipmentConflictException extends ShipmentLoadingException {}
