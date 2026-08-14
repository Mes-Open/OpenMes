<?php

namespace App\Enums;

/**
 * Lifecycle of a production-change request (#182).
 *
 * The whole point of the feature is that a structural change is reviewed before it
 * touches anything, so the transitions are deliberately narrow: only a DRAFT can be
 * edited, only a SUBMITTED request can be approved or rejected, and only an APPROVED
 * one can be applied. APPLIED and REJECTED are terminal — a change that turned out
 * wrong is superseded by a new request, never edited in place, because the applied
 * configuration is already on the shop floor.
 */
enum ChangeRequestStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Applied = 'APPLIED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Submitted => __('Submitted'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Applied => __('Applied'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Editing is a draft-only privilege: a submitted request must not move under the approver. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Applied, self::Rejected, self::Cancelled], true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Applied, self::Cancelled],
            self::Rejected, self::Applied, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
