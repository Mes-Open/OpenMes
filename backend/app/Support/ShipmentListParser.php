<?php

namespace App\Support;

use App\Models\ShipmentItem;

/**
 * Turns a pasted expected list or an uploaded CSV into shipment items (#400).
 *
 * One unit per line. A line is either just the label number, or the label
 * number and its type in any order, separated by a comma, semicolon or tab
 * (`PAL-000012`, `pallet,PAL-000012`, `CTN-000301;carton`). A header line
 * (`type,identifier`) and blank lines are skipped. A line without a type gets
 * $defaultType, and with none of that either the type is detected on save.
 */
class ShipmentListParser
{
    private const TYPE_WORDS = [
        'pallet' => ShipmentItem::TYPE_PALLET,
        'p' => ShipmentItem::TYPE_PALLET,
        'carton' => ShipmentItem::TYPE_CARTON,
        'c' => ShipmentItem::TYPE_CARTON,
        'box' => ShipmentItem::TYPE_CARTON,
    ];

    private const HEADER_WORDS = ['type', 'unit_type', 'identifier', 'label', 'number', 'code'];

    /**
     * @return list<array{unit_type: string|null, identifier: string}>
     */
    public static function parse(string $text, ?string $defaultType = null): array
    {
        $defaultType = in_array($defaultType, ShipmentItem::TYPES, true) ? $defaultType : null;
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $tokens = array_values(array_filter(
                array_map(fn ($t) => trim($t, " \t\"'"), preg_split('/[,;\t]/', $line) ?: []),
                fn ($t) => $t !== '',
            ));
            if ($tokens === []) {
                continue;
            }

            $type = null;
            $identifier = null;
            foreach ($tokens as $token) {
                $word = strtolower($token);
                if ($type === null && isset(self::TYPE_WORDS[$word])) {
                    $type = self::TYPE_WORDS[$word];
                } elseif ($identifier === null) {
                    $identifier = $token;
                }
            }

            if ($identifier === null || in_array(strtolower($identifier), self::HEADER_WORDS, true)) {
                continue;
            }

            $items[] = ['unit_type' => $type ?? $defaultType, 'identifier' => $identifier];
        }

        return $items;
    }
}
