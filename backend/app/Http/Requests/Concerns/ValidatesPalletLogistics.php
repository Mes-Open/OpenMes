<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PalletStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared existence rules for the logistics endpoints that act on a pallet
 * (moving it, #103, and re-routing it, #101).
 *
 * Both endpoints accept exactly the same pallet and operator sets, so keeping
 * one definition means a change to what "movable" means can't drift between
 * them — and it keeps validation in agreement with the controllers' findOrFail,
 * so a stale id yields a 422 rather than a 404.
 */
trait ValidatesPalletLogistics
{
    /**
     * A live, still-movable pallet. A shipped pallet is dispatched (its location
     * and ledger are frozen); a soft-deleted one is gone.
     */
    protected function movablePalletExists(): Exists
    {
        return Rule::exists('pallets', 'id')->where(fn ($q) => $q
            ->whereNull('deleted_at')
            ->where('status', '!=', PalletStatus::Shipped->value));
    }

    /** An active, non-deleted worker flagged as a logistics operator. */
    protected function activeLogisticsOperatorExists(): Exists
    {
        return Rule::exists('workers', 'id')->where(fn ($q) => $q
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('is_logistics', true));
    }

    /** @return array<string, string> */
    protected function palletLogisticsMessages(): array
    {
        return [
            'pallet_id.exists' => __('Select a movable (not shipped) pallet.'),
            'worker_id.exists' => __('Select an active logistics operator.'),
        ];
    }

    /**
     * A submitted location/destination, or null when the field was left blank.
     *
     * Blank and absent must both reach the service as null — an empty string
     * would be stored as a real (empty) destination.
     */
    protected function optionalText(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
