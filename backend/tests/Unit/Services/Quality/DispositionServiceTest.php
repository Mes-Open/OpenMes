<?php

namespace Tests\Unit\Services\Quality;

use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use App\Services\Quality\DispositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }
}
