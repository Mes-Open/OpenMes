<?php

namespace App\Support;

use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Database\Seeders\ShiftMonitorDemoSeeder;

/**
 * The example companies an admin can load from Settings → Data.
 *
 * Each entry is a whole plant: its own lines, products, routings, BOM, orders
 * and shift history. They are alternatives, not layers — loading one is meant
 * to leave the app looking like that business, so the picker only offers a
 * choice while no demo data is present.
 *
 * The shift monitor seeder is shared: it knows both plants' station codes and
 * skips whichever is not installed, so it runs last in either bundle.
 */
class DemoDatasetRegistry
{
    /**
     * key => [label, description, industry, seeders].
     *
     * `seeders` run in order; each is idempotent on its own keys.
     */
    public const DATASETS = [
        'bakery' => [
            'label' => 'Craft bakery',
            'description' => 'Bread, rolls and cakes baked overnight for the morning round. Night shift is the main one, and the recipe chain runs from loaf through dough to a levain the bakery keeps alive itself.',
            'industry' => 'Food production — bakery',
            'seeders' => [
                BakeryDemoSeeder::class,
                ShiftMonitorDemoSeeder::class,
            ],
        ],
        'machine_shop' => [
            'label' => 'Precision machine shop',
            'description' => 'Sawing, CNC turning and milling, heat treatment, grinding and CMM inspection. Parts measured in tens per hour with long cycles, and a three-level BOM from finished shaft down to bar stock.',
            'industry' => 'Manufacturing — metalworking / CNC',
            'seeders' => [
                MachineShopDemoSeeder::class,
                ShiftMonitorDemoSeeder::class,
            ],
        ],
        'print_shop' => [
            'label' => 'Garment print shop',
            'description' => 'DTG, screen print, embroidery and transfer lines decorating t-shirts, hoodies and accessories. Ten product types, ISA-95 site structure, crews and inspection plans.',
            'industry' => 'Manufacturing — apparel decoration',
            'seeders' => [
                PrintShopDemoSeeder::class,
                ShiftMonitorDemoSeeder::class,
            ],
        ],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::DATASETS);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::DATASETS);
    }

    /**
     * The seeder classes for a dataset, in run order.
     *
     * @return array<int, class-string>
     */
    public static function seedersFor(string $key): array
    {
        return self::DATASETS[$key]['seeders'] ?? [];
    }

    public static function labelFor(string $key): ?string
    {
        return self::DATASETS[$key]['label'] ?? null;
    }

    /**
     * The shape the settings screen renders its picker from.
     *
     * @return array<int, array{key: string, label: string, description: string, industry: string}>
     */
    public static function forDisplay(): array
    {
        return array_map(
            fn (string $key) => [
                'key' => $key,
                'label' => self::DATASETS[$key]['label'],
                'description' => self::DATASETS[$key]['description'],
                'industry' => self::DATASETS[$key]['industry'],
            ],
            self::keys(),
        );
    }
}
