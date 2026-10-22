<?php

namespace Tests\Unit\Services\Quality;

use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\DispositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DispositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DispositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DispositionService::class);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function dispositionMapProvider(): array
    {
        return [
            'accept → released' => [Inspection::DISPOSITION_ACCEPT, 'released'],
            'accept_with_deviation → released' => [Inspection::DISPOSITION_ACCEPT_WITH_DEVIATION, 'released'],
            'rework → quarantine' => [Inspection::DISPOSITION_REWORK, 'quarantine'],
            'quarantine → quarantine' => [Inspection::DISPOSITION_QUARANTINE, 'quarantine'],
            'scrap → rejected' => [Inspection::DISPOSITION_SCRAP, 'rejected'],
            'return_to_supplier → rejected' => [Inspection::DISPOSITION_RETURN_TO_SUPPLIER, 'rejected'],
            'reject → rejected' => [Inspection::DISPOSITION_REJECT, 'rejected'],
            'pending → null' => [Inspection::DISPOSITION_PENDING, null],
        ];
    }

    #[DataProvider('dispositionMapProvider')]
    public function test_map_disposition_to_lot_status(string $disposition, ?string $expected): void
    {
        $this->assertSame($expected, $this->service->mapDispositionToLotStatus($disposition));
    }

    public function test_apply_throws_on_invalid_disposition(): void
    {
        $inspection = $this->makeInspection();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid disposition');

        $this->service->apply($inspection, 'banana_split', null, $this->makeUser());
    }

    public function test_apply_throws_on_pending_reset(): void
    {
        $inspection = $this->makeInspection();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('disposition cannot be reset to pending');

        $this->service->apply($inspection, Inspection::DISPOSITION_PENDING, null, $this->makeUser());
    }

    public function test_apply_creates_the_lot_when_the_inspection_has_none(): void
    {
        // An inspector without the disposition right completed it, so no lot
        // was put on record; the decision is what creates it.
        $this->enableLotTracking();
        $inspection = $this->makeInspection();

        $this->service->apply($inspection, Inspection::DISPOSITION_ACCEPT, null, $this->makeUser());

        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertNotNull($lot);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->status);
        $this->assertEqualsWithDelta(120.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_apply_creates_a_held_lot_with_nothing_available(): void
    {
        $this->enableLotTracking();
        $inspection = $this->makeInspection();

        $this->service->apply($inspection, Inspection::DISPOSITION_QUARANTINE, null, $this->makeUser());

        $lot = MaterialLot::firstWhere('inspection_id', $inspection->id);
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->status);
        $this->assertEqualsWithDelta(0.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_apply_only_updates_the_status_of_an_existing_lot(): void
    {
        $this->enableLotTracking();
        $inspection = $this->makeInspection();
        app(\App\Services\Quality\InspectionLotCreator::class)
            ->ensureFromInspection($inspection, MaterialLot::STATUS_QUARANTINE);

        $this->service->apply($inspection, Inspection::DISPOSITION_SCRAP, null, $this->makeUser());

        $this->assertSame(1, MaterialLot::where('inspection_id', $inspection->id)->count());
        $this->assertSame(MaterialLot::STATUS_REJECTED, MaterialLot::firstWhere('inspection_id', $inspection->id)->status);
    }

    public function test_apply_creates_no_lot_when_tracking_is_off(): void
    {
        $inspection = $this->makeInspection();

        $this->service->apply($inspection, Inspection::DISPOSITION_ACCEPT, null, $this->makeUser());

        $this->assertSame(0, MaterialLot::count());
        $this->assertSame(Inspection::DISPOSITION_ACCEPT, $inspection->fresh()->disposition);
    }

    private function enableLotTracking(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );
    }

    private function makeInspection(): Inspection
    {
        $type = MaterialType::firstOrCreate(['code' => 'RAW'], ['name' => 'Raw']);
        $material = Material::firstOrCreate(
            ['code' => 'UNIT-M'],
            ['name' => 'Unit Mat', 'material_type_id' => $type->id],
        );

        return Inspection::factory()->create([
            'material_id' => $material->id,
            'status' => Inspection::STATUS_PASS,
            'completed_at' => now(),
            'quantity_received' => 120,
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }
}
