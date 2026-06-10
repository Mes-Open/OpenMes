<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftHandover>
 */
class ShiftHandoverFactory extends Factory
{
    public function definition(): array
    {
        $produced = fake()->numberBetween(50, 500);
        $scrap = fake()->numberBetween(0, 20);
        $good = $produced - $scrap;
        $packed = (int) ($good * 0.8);

        return [
            'shift_id' => null,
            'line_id' => null,
            'business_date' => now()->toDateString(),
            'shift_start' => now()->subHours(8),
            'shift_end' => now(),
            'produced_qty' => $produced,
            'scrap_qty' => $scrap,
            'good_qty' => $good,
            'packed_qty' => $packed,
            'wip_open_pallets_qty' => fake()->numberBetween(0, 30),
            'wip_unpacked_qty' => max(0, $good - $packed),
            'shipped_qty' => (int) ($packed * 0.5),
            'discrepancies' => [],
            'breakdown' => [],
            'notes' => null,
            'confirmed_by' => User::factory(),
            'confirmed_at' => now(),
        ];
    }
}
