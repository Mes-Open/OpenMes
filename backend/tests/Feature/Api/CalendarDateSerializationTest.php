<?php

namespace Tests\Feature\Api;

use App\Events\CollectionChanged;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\OeeRecord;
use App\Models\Tool;
use App\Models\User;
use App\Support\TimezoneRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DB `date` columns name a calendar day, so they serialize as `YYYY-MM-DD`.
 *
 * A plain `date` cast serializes midnight in the plant zone as a UTC instant:
 * `expiry_date = 2026-01-15` in a Warsaw plant came out as
 * `2026-01-14T23:00:00.000000Z`, and every client reading the date part saw
 * the previous day. Forms that read it back that way saved the date one day
 * earlier on every edit, and live-sync deltas disagreed with the snapshot
 * (which reads the raw column).
 */
class CalendarDateSerializationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        TimezoneRegistry::flush();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }

    public static function plantZones(): array
    {
        return [
            'east of UTC' => ['Europe/Warsaw'],
            'west of UTC' => ['America/Chicago'],
            'far east' => ['Pacific/Auckland'],
        ];
    }

    private function plantIn(string $timezone): void
    {
        TimezoneRegistry::save($timezone);
        TimezoneRegistry::apply();
    }

    public function test_every_date_cast_serializes_as_a_calendar_day(): void
    {
        $offenders = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            foreach ((new $class)->getCasts() as $attribute => $cast) {
                if (in_array($cast, ['date', 'immutable_date'], true)) {
                    $offenders[] = "{$class}::\${$attribute}";
                }
            }
        }

        $this->assertSame([], $offenders, "Cast DB date columns as 'date:Y-m-d' so they serialize as the day they name.");
    }

    #[DataProvider('plantZones')]
    public function test_api_returns_the_stored_day(string $timezone): void
    {
        $this->plantIn($timezone);

        $record = OeeRecord::factory()->create([
            'line_id' => Line::factory()->create()->id,
            'record_date' => '2026-01-15',
        ]);
        $lot = MaterialLot::factory()->create([
            'manufacturing_date' => '2026-01-02',
            'expiry_date' => '2026-01-15',
        ]);
        $tool = Tool::create(['code' => 'T-1', 'name' => 'Gauge', 'status' => 'available', 'next_service_at' => '2026-01-15']);

        $api = $this->actingAs($this->admin, 'sanctum');

        $this->assertSame('2026-01-15', $api->getJson('/api/v1/oee')->assertOk()->json('data.0.record_date'));
        $this->assertSame($record->id, $api->getJson('/api/v1/oee')->json('data.0.id'));

        $lotJson = $api->getJson("/api/v1/material-lots/{$lot->id}")->assertOk()->json('data');
        $this->assertSame('2026-01-15', $lotJson['expiry_date']);
        $this->assertSame('2026-01-02', $lotJson['manufacturing_date']);

        $this->assertSame('2026-01-15', $api->getJson("/api/v1/tools/{$tool->id}")->assertOk()->json('data.next_service_at'));
    }

    #[DataProvider('plantZones')]
    public function test_the_casted_value_is_still_midnight_of_that_day_in_the_plant_zone(string $timezone): void
    {
        $this->plantIn($timezone);

        $lot = MaterialLot::factory()->create(['expiry_date' => '2026-01-15']);
        $expiry = $lot->fresh()->expiry_date;

        $this->assertSame('2026-01-15 00:00:00', $expiry->format('Y-m-d H:i:s'));
        $this->assertSame($timezone, $expiry->getTimezone()->getName());
    }

    public function test_tool_edit_form_gets_the_day_it_saves_back(): void
    {
        $this->plantIn('Europe/Warsaw');
        $tool = Tool::create(['code' => 'T-1', 'name' => 'Gauge', 'status' => 'available', 'next_service_at' => '2026-01-15']);

        $this->actingAs($this->admin)
            ->get("/admin/tools/{$tool->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/tools/Edit')
                ->where('tool.next_service_at', '2026-01-15'));
    }

    public function test_live_sync_delta_matches_the_snapshot(): void
    {
        $this->plantIn('Europe/Warsaw');
        $lot = MaterialLot::factory()->create([
            'material_id' => Material::factory()->create()->id,
            'expiry_date' => '2026-01-15',
        ]);

        $deltas = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$deltas) {
            if ($e->collection === 'material_lots') {
                $deltas[] = $e->row;
            }
        });

        $lot->update(['quantity_available' => 1]);

        $snapshot = DB::table('material_lots')->where('id', $lot->id)->value('expiry_date');
        $this->assertNotEmpty($deltas);
        $this->assertSame((string) $snapshot, end($deltas)['expiry_date']);
        $this->assertSame('2026-01-15', end($deltas)['expiry_date']);
    }
}
