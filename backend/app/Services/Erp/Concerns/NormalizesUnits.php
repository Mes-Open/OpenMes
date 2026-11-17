<?php

namespace App\Services\Erp\Concerns;

use App\Support\Units\UnitNormalizer;

/**
 * Unit handling shared by the ERP importers. Used together with
 * ReportsImportRows, whose warn() / error() it reports through.
 *
 * Two rules, by what the unit means to the row:
 *  - on an item master (product, material) the unit is just a label, so an
 *    unknown one is stored as sent and flagged with a warning;
 *  - on a quantity (stock balance, lot) the unit qualifies the number, so one
 *    that does not match the item's unit fails the row — 340 g must never be
 *    booked as 340 kg. Quantities are never converted.
 */
trait NormalizesUnits
{
    /**
     * The unit to store on an item master: canonical when an alias (or a
     * differently-cased canonical unit) matches, otherwise the trimmed value
     * as sent plus a warning on the row.
     */
    protected function masterDataUnit(UnitNormalizer $units, string $raw): string
    {
        $resolved = $units->resolve($raw);

        if (! $resolved['known']) {
            $this->warn('unit_of_measure', __("Unknown unit ':unit' — saved as sent. Add an alias under Settings → Units of Measure to map it.", [
                'unit' => trim($raw),
            ]));
        }

        return (string) $resolved['unit'];
    }

    /**
     * The unit to store with a quantity of an item, or an error when the row's
     * unit is not the item's.
     *
     *  - no unit on the row → the item's unit (unchanged behaviour);
     *  - item has no unit → the row's unit, resolved;
     *  - same unit after aliases → the item's own spelling, so balances read
     *    the same as the item master;
     *  - different unit → a row error on `unit_of_measure`.
     *
     * @return array{unit: string|null, error: array<string, mixed>|null}
     */
    protected function quantityUnit(UnitNormalizer $units, mixed $rowUnit, ?string $itemUnit, string $itemCode): array
    {
        $rowUnit = is_string($rowUnit) ? trim($rowUnit) : '';
        $itemUnit = $itemUnit !== null ? trim($itemUnit) : '';

        if ($rowUnit === '') {
            return ['unit' => $itemUnit !== '' ? $itemUnit : null, 'error' => null];
        }

        if ($itemUnit === '') {
            return ['unit' => $units->resolve($rowUnit)['unit'], 'error' => null];
        }

        if ($units->same($rowUnit, $itemUnit)) {
            return ['unit' => $itemUnit, 'error' => null];
        }

        return [
            'unit' => null,
            'error' => $this->error('unit_of_measure', __("Unit ':unit' does not match the unit of ':code' (:expected). Quantities are not converted — send the quantity in :expected or add a unit alias.", [
                'unit' => $rowUnit,
                'code' => $itemCode,
                'expected' => $itemUnit,
            ])),
        ];
    }
}
