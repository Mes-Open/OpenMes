<?php

namespace Tests\Feature\Extension;

use App\Events\Resource\ResourceChanged;
use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\CustomFieldDefinition;
use App\Models\Line;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Support\ShiftWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A module may set a shift with no line for a group of lines — one plant's
 * shifts in a multi-site installation — through the `shifts.coverage` filter,
 * and add its own fields to the shift form. Core's own answers stay: a line's
 * shift runs on that line, a shift with no line on every line.
 */
class ShiftCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Line $north;

    private Line $south;

    protected function setUp(): void
    {
        parent::setUp();
        $this->north = Line::factory()->create(['name' => 'North 1']);
        $this->south = Line::factory()->create(['name' => 'South 1']);
    }

    /** Shifts whose code starts with "N-" run on the north line only. */
    private function northShiftsOnNorthLine(): void
    {
        app(FilterRegistry::class)->addFilter('shifts.coverage', fn (array $coverage, array $ctx) => $ctx['shift']->line_id === null && str_starts_with((string) $ctx['shift']->code, 'N-')
            ? ['key' => 'group:north', 'line_ids' => [$this->north->id]]
            : $coverage);
    }

    private function shift(string $code, string $start, string $end, array $extra = []): Shift
    {
        return Shift::create(['name' => $code, 'code' => $code, 'start_time' => $start, 'end_time' => $end, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true] + $extra);
    }

    public function test_without_a_module_a_shift_runs_on_its_line_or_on_every_line(): void
    {
        $global = $this->shift('G', '06:00', '14:00');
        $own = $this->shift('L', '14:00', '22:00', ['line_id' => $this->north->id]);

        $this->assertSame(['key' => 'all', 'line_ids' => null], $global->coverage());
        $this->assertSame(['key' => 'line:'.$this->north->id, 'line_ids' => [$this->north->id]], $own->coverage());
        $this->assertTrue($global->runsOnLine($this->south->id));
        $this->assertFalse($global->runsOnLine($this->south->id, plantWide: false));
        $this->assertFalse($own->runsOnLine($this->south->id));
        $this->assertTrue($own->runsOnLine(null));
    }

    public function test_a_module_narrows_a_shift_to_a_group_of_lines(): void
    {
        $this->northShiftsOnNorthLine();
        $this->shift('N-MORNING', '06:00', '14:00');
        $this->shift('ALL-NIGHT', '22:00', '06:00');

        $morning = Carbon::parse('2026-11-23 09:00');
        $this->assertSame('N-MORNING', ShiftWindow::at($this->north->id, $morning)?->shift->code);
        $this->assertNull(ShiftWindow::at($this->south->id, $morning), 'the north plant\'s morning does not run on the south line');
        $this->assertSame('ALL-NIGHT', ShiftWindow::at($this->south->id, Carbon::parse('2026-11-23 23:00'))?->shift->code);

        $this->travelTo($morning);
        $this->assertSame('N-MORNING', Shift::current($this->north->id)?->code);
        $this->assertNull(Shift::current($this->south->id));
    }

    public function test_the_api_lists_the_shifts_set_for_a_line_including_its_group(): void
    {
        $this->northShiftsOnNorthLine();
        $this->shift('N-AM', '06:00', '14:00');
        $this->shift('OWN', '14:00', '22:00', ['line_id' => $this->north->id]);
        $this->shift('ALL', '22:00', '06:00');

        $codes = fn (Line $line) => collect($this->actingAs($this->admin(), 'sanctum')->getJson("/api/v1/shifts?line_id={$line->id}")
            ->assertOk()->json('data'))->pluck('code')->sort()->values()->all();

        // As before: a line's own shifts, not those every line runs — plus its group's.
        $this->assertSame(['N-AM', 'OWN'], $codes($this->north));
        $this->assertSame([], $codes($this->south));
    }

    public function test_shifts_only_clash_with_shifts_set_for_the_same_lines(): void
    {
        $this->northShiftsOnNorthLine();
        $admin = $this->admin();
        $this->shift('S-DAY', '06:00', '14:00');

        // Same hours, but the north group's own shift: no clash with the global one.
        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'N-DAY', 'name' => 'North day', 'start_time' => '06:00', 'end_time' => '14:00'])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertDatabaseHas('shifts', ['code' => 'N-DAY']);

        // A second north shift over the first one clashes — and nothing is saved.
        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'N-LATE', 'name' => 'North late', 'start_time' => '10:00', 'end_time' => '18:00'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('shifts', ['code' => 'N-LATE']);

        // Core's rule is unchanged: two global shifts over each other clash.
        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'G2', 'name' => 'Global 2', 'start_time' => '12:00', 'end_time' => '20:00'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('shifts', ['code' => 'G2']);
    }

    public function test_a_module_adds_a_field_to_the_shift_form_and_stores_it(): void
    {
        $hooks = app(HookRegistry::class);
        $hooks->listen('display.admin.shifts.form.fields', fn (array $ctx) => [
            'name' => 'module_example_group', 'type' => 'select', 'label' => 'Group', 'column' => 'code',
            'value' => $ctx['shift']?->code, 'options' => [['value' => 'a', 'label' => 'A']],
        ]);
        app(FilterRegistry::class)->addFilter('validation.admin.shifts', fn (array $rules) => $rules + ['module_example_group' => ['nullable', 'in:a,b']]);
        $seen = [];
        $hooks->listen('persist.admin.shifts', function (array $ctx) use (&$seen) {
            $seen[] = [$ctx['action'], $ctx['shift']->code, $ctx['input']['module_example_group'] ?? null];
        });

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/shifts/create')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hooks', fn ($hooks) => collect($hooks)->get('display.admin.shifts.form.fields')[0]['name'] === 'module_example_group'));

        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'X', 'name' => 'X', 'start_time' => '06:00', 'end_time' => '14:00', 'module_example_group' => 'zzz'])
            ->assertSessionHasErrors('module_example_group');

        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'X', 'name' => 'X', 'start_time' => '06:00', 'end_time' => '14:00', 'module_example_group' => 'b'])
            ->assertSessionHasNoErrors();
        $shift = Shift::where('code', 'X')->firstOrFail();
        $this->actingAs($admin)->put("/admin/shifts/{$shift->id}", ['code' => 'X', 'name' => 'Renamed', 'start_time' => '06:00', 'end_time' => '14:00', 'module_example_group' => 'a'])
            ->assertSessionHasNoErrors();

        $this->assertSame([['store', 'X', 'b'], ['update', 'X', 'a']], $seen);
        $this->assertSame('Renamed', $shift->fresh()->name);
    }

    public function test_the_shift_form_still_validates_its_own_fields(): void
    {
        $this->post('/admin/shifts', ['code' => 'Q', 'name' => 'Q', 'start_time' => '06:00', 'end_time' => '14:00'])->assertRedirect('/login');

        $admin = $this->admin();
        $this->shift('TAKEN', '06:00', '14:00');

        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'TAKEN', 'name' => '', 'start_time' => '6am', 'end_time' => '14:00'])
            ->assertSessionHasErrors(['code', 'name', 'start_time']);
    }

    public function test_a_shift_is_deleted_unless_production_was_booked_on_it(): void
    {
        $admin = $this->admin();
        $unused = $this->shift('FREE', '06:00', '14:00');
        $booked = $this->shift('BOOKED', '14:00', '22:00');
        WorkOrderShiftEntry::create(['work_order_id' => WorkOrder::factory()->create()->id, 'shift_id' => $booked->id, 'quantity' => 5, 'production_date' => now()->toDateString()]);

        $this->actingAs($admin)->delete("/admin/shifts/{$unused->id}")->assertRedirect('/admin/shifts')->assertSessionHas('success');
        $this->assertSoftDeleted('shifts', ['id' => $unused->id]);

        $this->actingAs($admin)->delete("/admin/shifts/{$booked->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('shifts', ['id' => $booked->id]);
    }

    public function test_a_refused_shift_is_never_announced_to_modules(): void
    {
        $admin = $this->admin();
        $this->shift('DAY', '06:00', '14:00');
        $heard = [];
        Event::listen(ResourceChanged::class, function (ResourceChanged $e) use (&$heard) {
            if ($e->model instanceof Shift) {
                $heard[] = [$e->action, $e->model->code];
            }
        });

        // Saved first, then found to overlap and rolled back: modules hear nothing.
        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'CLASH', 'name' => 'Clash', 'start_time' => '10:00', 'end_time' => '18:00'])
            ->assertSessionHas('error');
        $this->assertSame([], $heard);

        $this->actingAs($admin)->post('/admin/shifts', ['code' => 'LATE', 'name' => 'Late', 'start_time' => '14:00', 'end_time' => '22:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame([['created', 'LATE']], $heard);
    }

    public function test_a_refused_update_keeps_the_stored_file(): void
    {
        Storage::fake();
        CustomFieldDefinition::create(['entity_type' => 'shift', 'key' => 'f_file', 'label' => 'Rota', 'type' => 'file', 'required' => false, 'position' => 0, 'config' => []]);
        $admin = $this->admin();
        $base = ['name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00'];

        $this->actingAs($admin)->post('/admin/shifts', $base + ['code' => 'AM', 'custom_field_files' => ['f_file' => UploadedFile::fake()->create('rota.pdf', 20, 'application/pdf')]])
            ->assertSessionHasNoErrors();
        $shift = Shift::where('code', 'AM')->firstOrFail();
        $first = $shift->custom_fields['f_file']['path'];
        $this->shift('PM', '14:00', '22:00');

        // Moved over the afternoon with a new file: refused — the old file stays, the new one is never stored.
        $this->actingAs($admin)->put("/admin/shifts/{$shift->id}", ['code' => 'AM', 'name' => 'Morning', 'start_time' => '12:00', 'end_time' => '20:00',
            'custom_field_files' => ['f_file' => UploadedFile::fake()->create('new.pdf', 20, 'application/pdf')]])->assertSessionHas('error');

        $this->assertSame($first, $shift->fresh()->custom_fields['f_file']['path']);
        Storage::assertExists($first);
        $this->assertCount(1, Storage::allFiles());
    }

    public function test_a_module_hears_each_write_as_it_was_even_after_a_later_save(): void
    {
        $shift = $this->shift('DAY', '06:00', '14:00');
        $heard = [];
        Event::listen(ResourceChanged::class, function (ResourceChanged $e) use (&$heard) {
            if ($e->model instanceof Shift && $e->action === 'updated') {
                $heard[] = [array_keys(array_diff_key($e->model->getChanges(), ['updated_at' => 1])), substr((string) $e->model->getOriginal('start_time'), 0, 5)];
            }
        });

        // Two saves in one transaction: each event still shows its own change.
        \Illuminate\Support\Facades\DB::transaction(function () use ($shift) {
            $shift->update(['start_time' => '07:00']);
            $shift->update(['name' => 'Day shift']);
        });

        $this->assertSame([[['start_time'], '06:00'], [['name'], '07:00']], $heard);
    }

    public function test_the_narrowest_shift_running_on_a_line_is_its_current_shift(): void
    {
        $this->northShiftsOnNorthLine();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->shift('G', '06:00', '14:00', ['sort_order' => 0]);
        $this->shift('N-AM', '06:00', '14:00', ['sort_order' => 5]);

        $this->assertSame('N-AM', Shift::current($this->north->id)?->code);
        $this->assertSame('N-AM', ShiftWindow::at($this->north->id, now())?->shift->code);
        $this->assertSame('N-AM', ShiftWindow::startingOnDate($this->north->id, now()->startOfDay())?->shift->code);
        $this->assertSame('G', Shift::current($this->south->id)?->code);

        $this->shift('S-OWN', '06:00', '14:00', ['line_id' => $this->south->id, 'sort_order' => 9]);
        $this->assertSame('S-OWN', Shift::current($this->south->id)?->code);
    }

    public function test_only_an_admin_may_add_or_change_a_shift(): void
    {
        Role::findOrCreate('Supervisor');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $shift = $this->shift('DAY', '06:00', '14:00');
        $payload = ['code' => 'X', 'name' => 'X', 'start_time' => '14:00', 'end_time' => '22:00'];

        $this->actingAs($supervisor)->post('/admin/shifts', $payload)->assertForbidden();
        $this->actingAs($supervisor)->put("/admin/shifts/{$shift->id}", $payload)->assertForbidden();
        $this->actingAs($supervisor)->delete("/admin/shifts/{$shift->id}")->assertForbidden();

        $this->assertDatabaseMissing('shifts', ['code' => 'X']);
        $this->assertNotSoftDeleted('shifts', ['id' => $shift->id, 'code' => 'DAY']);
    }

    public function test_the_operator_station_only_offers_the_shifts_that_run_on_its_line(): void
    {
        $this->northShiftsOnNorthLine();
        $this->shift('G', '06:00', '14:00');
        $this->shift('N-AM', '06:00', '14:00');
        $this->shift('S-OWN', '14:00', '22:00', ['line_id' => $this->south->id]);
        Role::findOrCreate('Operator');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($this->south->id);

        $response = $this->actingAs($operator)->withSession(['selected_line_id' => $this->south->id])
            ->get(route('operator.workstation'))->assertOk();

        $codes = collect($response->viewData('page')['props']['shifts'])->pluck('code')->sort()->values()->all();
        $this->assertSame(['G', 'S-OWN'], $codes);
    }

    private function admin(): User
    {
        Role::findOrCreate('Admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }
}
