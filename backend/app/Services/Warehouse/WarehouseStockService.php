<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Reading and moving a per-location stock balance.
 *
 * Extracted so the two things that move location stock — posting a warehouse
 * document (#212) and shop-floor consumption — share one implementation. The
 * race-safe upsert below is subtle enough that a second copy of it would drift.
 *
 * Balances only. materials.stock_quantity, the stock_movements ledger and lot
 * availability are owned by their own services, and this one never touches them.
 */
class WarehouseStockService
{
    /**
     * Move a (warehouse, item[, lot]) balance by a signed quantity, creating the row
     * on first use. Call inside the caller's transaction — the row is locked, so the
     * read-modify-write is only safe while that lock is held.
     *
     * @param  array<string, int|null>  $keys  warehouse_id, material_id, product_type_id, material_lot_id
     */
    public function adjust(array $keys, float $signed, ?string $unit = null): WarehouseStock
    {
        $stock = $this->lockOrCreate($keys, $unit);

        $stock->quantity = round((float) $stock->quantity + $signed, 3);

        if ($unit && ! $stock->unit_of_measure) {
            $stock->unit_of_measure = $unit;
        }

        $stock->save();

        return $stock;
    }

    /**
     * The balance row for a slot, locked for the rest of the caller's transaction and
     * created at zero if it does not exist yet.
     *
     * Callers that decide something from the balance (refusing a deduction the
     * location cannot cover) must read it through this rather than {@see available()}:
     * an unlocked read lets two concurrent bookings both pass the same check and
     * overdraw the location together.
     *
     * @param  array<string, int|null>  $keys  warehouse_id, material_id, product_type_id, material_lot_id
     */
    public function lockOrCreate(array $keys, ?string $unit = null): WarehouseStock
    {
        $keys = $this->normalizeKeys($keys);

        $stock = WarehouseStock::query()->where($keys)->lockForUpdate()->first();

        if (! $stock) {
            // lockForUpdate() can only lock rows that exist, so two concurrent callers
            // can both miss and then race to insert. The partial unique index decides
            // the winner; the loser re-reads the row it lost to instead of failing.
            //
            // The insert runs in a nested transaction (a SAVEPOINT) because on
            // PostgreSQL a failed statement poisons the whole transaction: catching the
            // violation without a savepoint to roll back to would leave the caller's
            // transaction aborted, and every later statement in it would fail too.
            try {
                $stock = DB::transaction(fn () => WarehouseStock::create([
                    ...$keys,
                    'quantity' => 0,
                    'unit_of_measure' => $unit,
                ]));
            } catch (UniqueConstraintViolationException) {
                $stock = WarehouseStock::query()->where($keys)->lockForUpdate()->first();
            }
        }

        if (! $stock) {
            throw new \RuntimeException('Could not read the stock balance to update.');
        }

        return $stock;
    }

    /**
     * Current balance for a slot, 0 when the row does not exist yet — an untouched
     * location holds nothing, which is the same answer as an emptied one.
     *
     * @param  array<string, int|null>  $keys
     */
    public function available(array $keys): float
    {
        return (float) (WarehouseStock::query()
            ->where($this->normalizeKeys($keys))
            ->value('quantity') ?? 0);
    }

    /**
     * Whether the plant refuses to let a balance go below zero.
     *
     * Read straight from the settings table rather than through a cache, because the
     * answer decides whether production is allowed to continue and a stale yes/no
     * here is worse than the query. Missing or unreadable settings mean "not blocked":
     * this switch exists to tighten the default, never to halt a plant that never
     * turned it on.
     */
    public function blocksNegativeStock(): bool
    {
        try {
            $row = DB::table('system_settings')->where('key', 'block_negative_stock')->value('value');

            return (bool) json_decode($row ?? 'false', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, int|null>  $keys
     * @return array<string, int|null>
     */
    private function normalizeKeys(array $keys): array
    {
        return [
            'warehouse_id' => $keys['warehouse_id'] ?? null,
            'material_id' => $keys['material_id'] ?? null,
            'product_type_id' => $keys['product_type_id'] ?? null,
            'material_lot_id' => $keys['material_lot_id'] ?? null,
        ];
    }
}
