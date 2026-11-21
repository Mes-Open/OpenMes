<?php

namespace App\Enums;

/**
 * Status a workstation device (operator panel or Workstation client) reports.
 *
 * The stored value is what the device last said. Whether the station counts
 * as active also depends on the heartbeat clock, see
 * WorkstationDevice::effectiveStatus(): a device that stays silent for longer
 * than the online window is inactive, whatever it last reported.
 */
enum WorkstationDeviceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
