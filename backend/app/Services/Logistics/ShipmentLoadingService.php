<?php

namespace App\Services\Logistics;

use App\Enums\PalletStatus;
use App\Enums\ShipmentStatus;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentScan;
use App\Models\UnitCarton;
use App\Models\User;
use App\Services\Packaging\UnitPackingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scan-to-load verification (#400).
 *
 * The dock scans every pallet / carton label as it goes onto the truck. Each
 * scan is checked against the shipment's expected list and answered at once
 * (loaded, duplicate, unexpected, unknown, blocked); every answer is written to
 * the shipment's scan log. Confirming the shipment ships what was loaded, and a
 * short load needs a supervisor and a reason.
 *
 * Every write locks the shipment row first, so two scanners at the same truck
 * are serialised: the same label scanned twice at once is loaded once and
 * reported as a duplicate the second time.
 */
class ShipmentLoadingService
{
    public function __construct(private readonly UnitPackingService $packing) {}

    /**
     * Create a shipment with its expected list.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{unit_type?: string|null, identifier: string}>  $items
     */
    public function create(array $attributes, array $items, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($attributes, $items, $actor) {
            $shipment = Shipment::create($attributes + [
                'status' => ShipmentStatus::Planned->value,
                'created_by_id' => $actor?->id,
            ]);
            $this->syncItems($shipment, $items);

            return $shipment;
        });
    }

    /**
     * Change the header and replace the expected list. Rows that stay keep their
     * loaded state; a loaded row cannot be dropped from the list (undo it first).
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{unit_type?: string|null, identifier: string}>|null  $items  null keeps the list as it is
     *
     * @throws ShipmentLoadingException
     */
    public function update(Shipment $shipment, array $attributes, ?array $items, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $attributes, $items) {
            $shipment = $this->lock($shipment);
            $this->assertActive($shipment);

            $shipment->update($attributes);
            if ($items !== null) {
                $this->syncItems($shipment, $items);
            }

            return $shipment;
        });
    }

    /**
     * Bring the expected list in line with $items: add new ones, keep existing
     * ones, soft-delete the rest.
     *
     * @param  list<array{unit_type?: string|null, identifier: string}>  $items
     *
     * @throws ShipmentLoadingException
     */
    private function syncItems(Shipment $shipment, array $items): void
    {
        $wanted = [];
        foreach ($items as $row) {
            $identifier = $this->normalise((string) $row['identifier']);
            if ($identifier === '') {
                continue;
            }
            $type = $row['unit_type'] ?? null;
            $type = in_array($type, ShipmentItem::TYPES, true) ? $type : $this->detectType($identifier);
            $wanted[$type.'|'.$identifier] = [$type, $identifier];
        }

        $existing = $shipment->items()->get()->keyBy(fn (ShipmentItem $i) => $i->unit_type.'|'.$i->identifier);

        foreach ($existing as $key => $item) {
            if (isset($wanted[$key])) {
                continue;
            }
            if ($item->isLoaded()) {
                throw new ShipmentLoadingException(__(':identifier is already loaded. Undo the scan before removing it from the list.', ['identifier' => $item->identifier]));
            }
            $item->delete();
        }

        foreach ($wanted as $key => [$type, $identifier]) {
            if ($existing->has($key)) {
                continue;
            }
            $shipment->items()->create([
                'unit_type' => $type,
                'identifier' => $identifier,
                'pallet_id' => $type === ShipmentItem::TYPE_PALLET ? Pallet::where('pallet_no', $identifier)->value('id') : null,
                'carton_id' => $type === ShipmentItem::TYPE_CARTON ? UnitCarton::where('carton_no', $identifier)->value('id') : null,
                'tenant_id' => $shipment->tenant_id,
            ]);
        }
    }

    /**
     * Pallet or carton, for a label the list did not type: a known pallet / carton
     * number decides, else the number's prefix, else pallet (the usual load unit).
     */
    public function detectType(string $identifier): string
    {
        if (Pallet::where('pallet_no', $identifier)->exists()) {
            return ShipmentItem::TYPE_PALLET;
        }
        if (UnitCarton::where('carton_no', $identifier)->exists()) {
            return ShipmentItem::TYPE_CARTON;
        }

        return str_starts_with(strtoupper($identifier), 'CTN') ? ShipmentItem::TYPE_CARTON : ShipmentItem::TYPE_PALLET;
    }

    /**
     * Check one scanned label against the shipment and log the answer.
     *
     * @throws ShipmentLoadingException when the shipment is no longer on the dock
     */
    public function scan(Shipment $shipment, string $code, User $user, ?int $workstationId = null): ShipmentScan
    {
        return DB::transaction(function () use ($shipment, $code, $user, $workstationId) {
            $shipment = $this->lock($shipment);
            $this->assertActive($shipment);

            $code = $this->normalise($code);
            $verdict = $this->evaluate($shipment, $code);

            if ($verdict['result'] === ShipmentScan::RESULT_LOADED) {
                /** @var ShipmentItem $item */
                $item = $verdict['item'];
                $item->update([
                    'loaded_at' => now(),
                    'loaded_by_id' => $user->id,
                    'pallet_id' => $verdict['pallet']?->id ?? $item->pallet_id,
                    'carton_id' => $verdict['carton']?->id ?? $item->carton_id,
                ]);
            }

            if ($shipment->status === ShipmentStatus::Planned) {
                $shipment->update([
                    'status' => ShipmentStatus::Loading->value,
                    'started_at' => now(),
                    'started_by_id' => $user->id,
                ]);
            }

            return $this->log($shipment, $user, [
                'scanned_code' => mb_substr($code, 0, 128),
                'unit_type' => $verdict['pallet'] ? ShipmentItem::TYPE_PALLET : ($verdict['carton'] ? ShipmentItem::TYPE_CARTON : null),
                'unit_id' => $verdict['pallet']?->id ?? $verdict['carton']?->id,
                'shipment_item_id' => $verdict['item']?->id,
                'result' => $verdict['result'],
                'message' => $verdict['message'],
                'workstation_id' => $workstationId,
            ]);
        });
    }

    /**
     * Take a mistakenly scanned unit off the truck again. The original `loaded`
     * scan stays in the log; the undo is logged as `removed`.
     *
     * @throws ShipmentLoadingException
     */
    public function unload(ShipmentItem $item, User $user, ?int $workstationId = null): ShipmentScan
    {
        return DB::transaction(function () use ($item, $user, $workstationId) {
            $shipment = $this->lock($item->shipment()->firstOrFail());
            $this->assertActive($shipment);

            $item = ShipmentItem::whereKey($item->id)->firstOrFail();
            if (! $item->isLoaded()) {
                throw new ShipmentLoadingException(__(':identifier is not loaded.', ['identifier' => $item->identifier]));
            }

            $item->update(['loaded_at' => null, 'loaded_by_id' => null]);

            return $this->log($shipment, $user, [
                'scanned_code' => $item->identifier,
                'unit_type' => $item->unit_type,
                'unit_id' => $item->unit_type === ShipmentItem::TYPE_PALLET ? $item->pallet_id : $item->carton_id,
                'shipment_item_id' => $item->id,
                'result' => ShipmentScan::RESULT_REMOVED,
                'message' => __(':identifier taken off the load.', ['identifier' => $item->identifier]),
                'workstation_id' => $workstationId,
            ]);
        });
    }

    /**
     * Expected / loaded / missing, plus what was scanned that did not belong.
     *
     * @return array{expected: int, loaded: int, missing: int, loaded_items: Collection<int, ShipmentItem>, missing_items: Collection<int, ShipmentItem>, unexpected: Collection<int, ShipmentScan>, duplicates: Collection<int, ShipmentScan>, blocked: Collection<int, ShipmentScan>}
     */
    public function summary(Shipment $shipment): array
    {
        $items = $shipment->items()->with(['pallet', 'carton', 'loadedBy:id,name'])->orderBy('id')->get();
        $scans = $shipment->scans()
            ->whereIn('result', [ShipmentScan::RESULT_UNEXPECTED, ShipmentScan::RESULT_UNKNOWN, ShipmentScan::RESULT_DUPLICATE, ShipmentScan::RESULT_BLOCKED])
            ->with('user:id,name')
            ->orderBy('scanned_at')->orderBy('id')
            ->get();

        $loaded = $items->filter(fn (ShipmentItem $i) => $i->isLoaded())->values();
        $missing = $items->reject(fn (ShipmentItem $i) => $i->isLoaded())->values();

        return [
            'expected' => $items->count(),
            'loaded' => $loaded->count(),
            'missing' => $missing->count(),
            'loaded_items' => $loaded,
            'missing_items' => $missing,
            'unexpected' => $scans->whereIn('result', [ShipmentScan::RESULT_UNEXPECTED, ShipmentScan::RESULT_UNKNOWN])->values(),
            'duplicates' => $scans->where('result', ShipmentScan::RESULT_DUPLICATE)->values(),
            'blocked' => $scans->where('result', ShipmentScan::RESULT_BLOCKED)->values(),
        ];
    }

    /** How many units travel in a loaded pallet / carton: its serialised units, else its recorded quantity. */
    public function unitCount(ShipmentItem $item): int
    {
        if ($item->unit_type === ShipmentItem::TYPE_PALLET && $item->pallet_id) {
            $count = SerialUnit::where('pallet_id', $item->pallet_id)->where('status', '!=', SerialUnit::STATUS_SCRAPPED)->count();

            return $count > 0 ? $count : (int) ($item->pallet?->qty ?? 0);
        }
        if ($item->unit_type === ShipmentItem::TYPE_CARTON && $item->carton_id) {
            $count = SerialUnit::where('carton_id', $item->carton_id)->where('status', '!=', SerialUnit::STATUS_SCRAPPED)->count();

            return $count > 0 ? $count : (int) ($item->carton?->qty ?? 0);
        }

        return 0;
    }

    /**
     * Let the truck go. Loaded pallets become shipped (their units follow via the
     * pallet's own hook), loaded loose cartons ship their units the same way.
     *
     * A short load — anything on the list not loaded — is a supervisor's call and
     * needs a reason.
     *
     * @throws AuthorizationException a short load confirmed by someone who may not
     * @throws ValidationException short load without a reason, or nothing loaded
     * @throws ShipmentLoadingException
     */
    public function confirm(Shipment $shipment, User $user, ?string $reason = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $user, $reason) {
            $shipment = $this->lock($shipment);
            $this->assertActive($shipment);

            $items = $shipment->items()->with(['pallet', 'carton'])->get();
            $loaded = $items->filter(fn (ShipmentItem $i) => $i->isLoaded());
            $missing = $items->count() - $loaded->count();
            $reason = $reason !== null ? trim($reason) : null;

            if ($loaded->isEmpty()) {
                throw ValidationException::withMessages(['shipment' => __('Nothing has been loaded yet.')]);
            }

            if ($missing > 0) {
                if (! $user->can('confirmShort', $shipment)) {
                    throw new AuthorizationException(__('This load is short (:count missing). Only a supervisor can confirm it.', ['count' => $missing]));
                }
                if ($reason === null || $reason === '') {
                    throw ValidationException::withMessages(['short_reason' => __('Give a reason for shipping a short load.')]);
                }
            }

            $now = now();
            foreach ($loaded as $item) {
                if ($item->unit_type === ShipmentItem::TYPE_PALLET && $item->pallet) {
                    $this->shipPallet($item->pallet, $now);
                } elseif ($item->unit_type === ShipmentItem::TYPE_CARTON && $item->carton) {
                    $pallet = $item->carton->pallet;
                    // A carton whose pallet is shipping too leaves with that pallet.
                    if (! $pallet || ! $loaded->contains(fn (ShipmentItem $i) => $i->pallet_id === $pallet->id)) {
                        $this->packing->markCartonShipped($item->carton, $user, $now);
                    }
                }
            }

            $shipment->update([
                'status' => ShipmentStatus::Confirmed->value,
                'confirmed_at' => $now,
                'confirmed_by_id' => $user->id,
                'short_reason' => $missing > 0 ? $reason : null,
            ]);

            return $shipment;
        });
    }

    /** Call the load off. Nothing ships; the log stays. */
    public function cancel(Shipment $shipment): Shipment
    {
        return DB::transaction(function () use ($shipment) {
            $shipment = $this->lock($shipment);
            $this->assertActive($shipment);
            $shipment->update(['status' => ShipmentStatus::Cancelled->value]);

            return $shipment;
        });
    }

    private function shipPallet(Pallet $pallet, \DateTimeInterface $at): void
    {
        if ($pallet->status === PalletStatus::Shipped) {
            return;
        }
        $pallet->status = PalletStatus::Shipped;
        $pallet->shipped_at = $at;
        try {
            $pallet->save();
        } catch (\DomainException $e) {
            // The quality ship-gate refused it (the verdict changed after loading).
            throw ValidationException::withMessages(['shipment' => $e->getMessage()]);
        }
    }

    /**
     * What a scanned code means for this shipment.
     *
     * @return array{result: string, message: string, item: ShipmentItem|null, pallet: Pallet|null, carton: UnitCarton|null}
     */
    private function evaluate(Shipment $shipment, string $code): array
    {
        $pallet = $code === '' ? null : Pallet::where('pallet_no', $code)->first();
        $carton = ($pallet || $code === '') ? null : UnitCarton::where('carton_no', $code)->first();

        $item = $shipment->items()
            ->where(function ($q) use ($code, $pallet, $carton) {
                $q->where('identifier', $code);
                if ($pallet) {
                    $q->orWhere('pallet_id', $pallet->id);
                }
                if ($carton) {
                    $q->orWhere('carton_id', $carton->id);
                }
            })
            ->get()
            // The row naming this exact label wins over one matched by unit id.
            ->sortBy(fn (ShipmentItem $i) => $i->identifier === $code ? 0 : 1)
            ->first();

        $answer = fn (string $result, string $message) => [
            'result' => $result, 'message' => $message, 'item' => $item, 'pallet' => $pallet, 'carton' => $carton,
        ];

        if (! $pallet && ! $carton) {
            return $answer(ShipmentScan::RESULT_UNKNOWN, $item
                ? __(':code is on the list but no such pallet or carton exists in OpenMES.', ['code' => $code])
                : __(':code is not a known pallet or carton.', ['code' => $code]));
        }

        $label = $pallet ? $pallet->pallet_no : $carton->carton_no;

        if ($item && $item->isLoaded()) {
            return $answer(ShipmentScan::RESULT_DUPLICATE, __(':code is already loaded.', ['code' => $label]));
        }

        if ($blocker = $this->blocker($shipment, $pallet, $carton)) {
            return $answer(ShipmentScan::RESULT_BLOCKED, $blocker);
        }

        if ($item) {
            return $answer(ShipmentScan::RESULT_LOADED, __(':code loaded.', ['code' => $label]));
        }

        // A carton riding on a pallet that is on the list travels with it: it is
        // neither a stray nor a second count.
        if ($carton && $carton->pallet_id) {
            $palletItem = $shipment->items()->where('pallet_id', $carton->pallet_id)->first();
            if ($palletItem) {
                return $answer(ShipmentScan::RESULT_DUPLICATE, $palletItem->isLoaded()
                    ? __('Carton :code is on pallet :pallet, already loaded. Not counted again.', ['code' => $label, 'pallet' => $palletItem->identifier])
                    : __('Carton :code is on pallet :pallet. Scan the pallet label instead.', ['code' => $label, 'pallet' => $palletItem->identifier]));
            }
        }

        return $answer(ShipmentScan::RESULT_UNEXPECTED, __(':code is not on this shipment.', ['code' => $label]));
    }

    /** Why this unit may not go on a truck, or null when it may. */
    private function blocker(Shipment $shipment, ?Pallet $pallet, ?UnitCarton $carton): ?string
    {
        if ($pallet) {
            if ($pallet->status === PalletStatus::Open) {
                return __('Pallet :code is still open. Close it before loading.', ['code' => $pallet->pallet_no]);
            }
            if ($pallet->status === PalletStatus::Shipped) {
                return __('Pallet :code has already been shipped.', ['code' => $pallet->pallet_no]);
            }
            if ($pallet->quality_status === Pallet::QUALITY_FAIL) {
                return __('Pallet :code failed quality control.', ['code' => $pallet->pallet_no]);
            }
            if ($pallet->quality_status !== Pallet::QUALITY_PASS) {
                return __('Pallet :code is not released by quality yet.', ['code' => $pallet->pallet_no]);
            }
            $held = SerialUnit::where('pallet_id', $pallet->id)->where('status', SerialUnit::STATUS_BLOCKED)->count();
            if ($held > 0) {
                return __('Pallet :code carries :count held unit(s).', ['code' => $pallet->pallet_no, 'count' => $held]);
            }
            $other = $this->loadedElsewhere($shipment, 'pallet_id', $pallet->id);
        } else {
            if ($carton->isOpen()) {
                return __('Carton :code is still open. Close it before loading.', ['code' => $carton->carton_no]);
            }
            $parent = $carton->pallet;
            if ($parent && $parent->quality_status === Pallet::QUALITY_FAIL) {
                return __('Carton :code is on pallet :pallet, which failed quality control.', ['code' => $carton->carton_no, 'pallet' => $parent->pallet_no]);
            }
            $units = SerialUnit::where('carton_id', $carton->id)->where('status', '!=', SerialUnit::STATUS_SCRAPPED)->get(['id', 'status']);
            $held = $units->where('status', SerialUnit::STATUS_BLOCKED)->count();
            if ($held > 0) {
                return __('Carton :code holds :count held unit(s).', ['code' => $carton->carton_no, 'count' => $held]);
            }
            if (($parent && $parent->status === PalletStatus::Shipped)
                || ($units->isNotEmpty() && $units->every(fn ($u) => $u->status === SerialUnit::STATUS_SHIPPED))) {
                return __('Carton :code has already been shipped.', ['code' => $carton->carton_no]);
            }
            $other = $this->loadedElsewhere($shipment, 'carton_id', $carton->id);
        }

        if ($other) {
            return __(':code is already loaded on shipment :shipment.', [
                'code' => $pallet ? $pallet->pallet_no : $carton->carton_no,
                'shipment' => $other,
            ]);
        }

        return null;
    }

    /** shipment_no of another active shipment this unit is already loaded on, if any. */
    private function loadedElsewhere(Shipment $shipment, string $column, int $id): ?string
    {
        return ShipmentItem::query()
            ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->where("shipment_items.{$column}", $id)
            ->whereNotNull('shipment_items.loaded_at')
            ->where('shipment_items.shipment_id', '!=', $shipment->id)
            ->whereIn('shipments.status', ShipmentStatus::activeValues())
            ->whereNull('shipments.deleted_at')
            ->value('shipments.shipment_no');
    }

    /** @param array<string, mixed> $attributes */
    private function log(Shipment $shipment, User $user, array $attributes): ShipmentScan
    {
        return $shipment->scans()->create($attributes + [
            'user_id' => $user->id,
            'scanned_at' => now(),
            'tenant_id' => $shipment->tenant_id,
        ]);
    }

    private function lock(Shipment $shipment): Shipment
    {
        return Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
    }

    /** @throws ShipmentLoadingException */
    private function assertActive(Shipment $shipment): void
    {
        if (! $shipment->isActive()) {
            throw new ShipmentLoadingException(__('Shipment :no is :status; it can no longer be changed.', [
                'no' => $shipment->shipment_no,
                'status' => $shipment->status->label(),
            ]));
        }
    }

    /** Scanner input as stored: trimmed, control characters (CR/LF/GS from a wedge) removed. */
    public function normalise(string $code): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $code));
    }
}
