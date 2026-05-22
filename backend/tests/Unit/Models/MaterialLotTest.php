<?php

namespace Tests\Unit\Models;

use App\Models\MaterialLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants(): void
    {
        $this->assertSame('received', MaterialLot::STATUS_RECEIVED);
        $this->assertSame('released', MaterialLot::STATUS_RELEASED);
        $this->assertSame('consumed', MaterialLot::STATUS_CONSUMED);
        $this->assertContains('quarantine', MaterialLot::STATUSES);
    }

    public function test_is_available_requires_released_status_and_positive_quantity(): void
    {
        $lot = MaterialLot::factory()->create([
            'status' => MaterialLot::STATUS_RELEASED,
            'quantity_received' => 100,
            'quantity_available' => 100,
        ]);
        $this->assertTrue($lot->isAvailable());

        $lot->quantity_available = 0;
        $this->assertFalse($lot->isAvailable());

        $lot->quantity_available = 50;
        $lot->status = MaterialLot::STATUS_QUARANTINE;
        $this->assertFalse($lot->isAvailable());
    }

    public function test_is_expired_returns_true_for_past_expiry_date(): void
    {
        $expired = MaterialLot::factory()->expired()->create();
        $fresh = MaterialLot::factory()->create([
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        $noExpiry = MaterialLot::factory()->create(['expiry_date' => null]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($fresh->isExpired());
        $this->assertFalse($noExpiry->isExpired());
    }

    public function test_consume_decrements_quantity_available(): void
    {
        $lot = MaterialLot::factory()->create([
            'quantity_received' => 100,
            'quantity_available' => 100,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);

        $lot->consume(30);
        $this->assertEqualsWithDelta(70.0, (float) $lot->fresh()->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->fresh()->status);
    }

    public function test_consume_transitions_status_to_consumed_when_depleted(): void
    {
        $lot = MaterialLot::factory()->create([
            'quantity_received' => 50,
            'quantity_available' => 50,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);

        $lot->consume(50);
        $fresh = $lot->fresh();
        $this->assertEqualsWithDelta(0.0, (float) $fresh->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $fresh->status);
    }

    public function test_consume_throws_on_overflow(): void
    {
        $lot = MaterialLot::factory()->create([
            'quantity_received' => 10,
            'quantity_available' => 10,
        ]);

        $this->expectException(\DomainException::class);
        $lot->consume(20);
    }

    public function test_consume_throws_on_non_positive_amount(): void
    {
        $lot = MaterialLot::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $lot->consume(0);
    }

    public function test_consume_throws_on_negative_amount(): void
    {
        $lot = MaterialLot::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $lot->consume(-5);
    }
}
