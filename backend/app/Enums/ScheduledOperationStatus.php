<?php

namespace App\Enums;

/**
 * Outcome of placing one operation with the finite-capacity scheduler.
 *
 * Machine-readable on purpose: the values are what the API returns and what
 * downstream consumers (completion forecast, schedule suggestions) branch on,
 * so they are part of the contract and must not be renamed.
 */
enum ScheduledOperationStatus: string
{
    case Scheduled = 'scheduled';
    case ScheduledLate = 'scheduled_late';
    case InfeasibleResourceCapacity = 'infeasible_resource_capacity';
    case InfeasibleMaterial = 'infeasible_material';
    case InfeasibleQuality = 'infeasible_quality';
    case InfeasibleWorkforceCapacity = 'infeasible_workforce_capacity';
    case InfeasibleQualification = 'infeasible_qualification';
    case InfeasibleNoEligibleWorkstation = 'infeasible_no_eligible_workstation';
    case InfeasiblePredecessor = 'infeasible_predecessor';
    case InfeasibleUnestimated = 'infeasible_unestimated';

    public function isScheduled(): bool
    {
        return $this === self::Scheduled || $this === self::ScheduledLate;
    }

    /** The reason code an unscheduled operation carries — the status minus its prefix. */
    public function reasonCode(): ?string
    {
        return $this->isScheduled() ? null : substr($this->value, strlen('infeasible_'));
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::ScheduledLate => __('Scheduled — late'),
            self::InfeasibleResourceCapacity => __('Infeasible — resource capacity'),
            self::InfeasibleMaterial => __('Infeasible — material'),
            self::InfeasibleQuality => __('Infeasible — quality hold'),
            self::InfeasibleWorkforceCapacity => __('Infeasible — workforce capacity'),
            self::InfeasibleQualification => __('Infeasible — qualification'),
            self::InfeasibleNoEligibleWorkstation => __('Infeasible — no eligible workstation'),
            self::InfeasiblePredecessor => __('Infeasible — predecessor not schedulable'),
            self::InfeasibleUnestimated => __('Infeasible — no duration estimate'),
        };
    }
}
