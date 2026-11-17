<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `?since=` must accept the ISO 8601 timestamps integrations send (`…T…Z`,
 * `…+02:00`). Compared as a raw string, SQLite sorted every stored
 * `Y-m-d H:i:s` value before the `T` form, so incremental polls came back
 * empty on SQLite installs.
 */
class ErpSinceFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'Primary'])->id;
        app(TenantContext::class)->set($this->tenantId);

        $line = Line::factory()->create(['code' => 'LX']);
        $product = ProductType::factory()->create(['code' => 'PX']);

        foreach (['WO-OLD' => '2026-11-06 08:00:00', 'WO-NEW' => '2026-11-06 18:17:42'] as $no => $at) {
            $wo = WorkOrder::factory()->create([
                'tenant_id' => $this->tenantId,
                'line_id' => $line->id,
                'product_type_id' => $product->id,
                'order_no' => $no,
                'status' => WorkOrder::STATUS_DONE,
            ]);
            WorkOrder::withoutTimestamps(fn () => $wo->forceFill(['updated_at' => Carbon::parse($at)])->save());
        }

        [, $this->key] = ApiKey::issue([
            'name' => 'Since test',
            'scopes' => [ApiScope::ProductionRead->value],
            'tenant_id' => $this->tenantId,
        ]);
    }

    private function ordersSince(string $since): array
    {
        return collect($this->withHeader('X-Api-Key', $this->key)
            ->getJson('/api/v1/erp/production/completions?since='.urlencode($since))
            ->assertOk()
            ->json('data'))->pluck('order_no')->all();
    }

    public function test_iso_utc_timestamp_with_t_and_z(): void
    {
        $this->assertSame(['WO-NEW'], $this->ordersSince('2026-11-06T18:00:00Z'));
    }

    public function test_iso_timestamp_with_offset_is_converted(): void
    {
        // 20:00 at +02:00 is 18:00 UTC.
        $this->assertSame(['WO-NEW'], $this->ordersSince('2026-11-06T20:00:00+02:00'));
    }

    public function test_plain_datetime_still_works(): void
    {
        $this->assertSame(['WO-OLD', 'WO-NEW'], $this->ordersSince('2026-11-06 07:00:00'));
    }
}
