<?php

namespace Tests\Feature\Seeders;

use App\Models\MaterialLot;
use App\Support\DemoDatasetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The material lots an example company brings are lots production can use.
 *
 * The seeders wrote `status = 'available'`, which is not a lot status: lot
 * picking only takes released lots, so with lot tracking switched on every
 * seeded lot was invisible - the pick dialog said "No lots available for this
 * material" and no step that consumes it could start.
 */
class DemoLotsArePickableTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function datasets(): array
    {
        return array_combine(
            array_keys(DemoDatasetRegistry::DATASETS),
            array_map(fn (string $key) => [$key], array_keys(DemoDatasetRegistry::DATASETS)),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datasets')]
    public function test_every_seeded_lot_has_a_known_status_and_stock_on_hand_is_released(string $dataset): void
    {
        foreach (DemoDatasetRegistry::seedersFor($dataset) as $seeder) {
            $this->seed($seeder);
        }

        $unknown = MaterialLot::whereNotIn('status', MaterialLot::STATUSES)->pluck('status', 'lot_number')->all();
        $this->assertSame([], $unknown, 'Lots with a status the app does not know: '.json_encode($unknown));

        // A received, unblocked lot with stock is what a picker should be offered.
        $this->assertTrue(
            MaterialLot::where('status', MaterialLot::STATUS_RELEASED)->where('quantity_available', '>', 0)->exists(),
            "The {$dataset} demo has no lot production can pick from.",
        );
    }
}
