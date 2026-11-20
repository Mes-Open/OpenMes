<?php

namespace Tests\Feature\Web\Admin;

use App\Models\EmployeeActivity;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RequiresNoModules;
use Tests\TestCase;

class EmployeeScheduleTest extends TestCase
{
    use RefreshDatabase;
    use RequiresNoModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfAnyModuleIsInstalled();
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public static function views(): array
    {
        return [
            'day' => ['day', 'Day'],
            'team' => ['team', 'Team'],
            'month' => ['month', 'Month'],
        ];
    }

    #[DataProvider('views')]
    public function test_schedule_renders_workers_without_optional_personnel_classes(string $view, string $component): void
    {
        $worker = Worker::create(['code' => 'CUT-01', 'name' => 'Cutting operator', 'is_active' => true]);

        $this->get(route('admin.schedule.employees', ['date' => '2026-09-14', 'view' => $view]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/schedule/employees/'.$component)
                ->where('date', '2026-09-14')
                ->where('selectedWorkerId', $worker->id)
                ->has('workers', 1)
                ->where('workers.0.name', 'Cutting operator')
                ->where('workers.0.personnel_class_code', null));
    }

    public function test_adding_an_activity_saves_it_and_says_so(): void
    {
        $worker = Worker::factory()->create();

        $this->post('/admin/schedule/employees', [
            'worker_id' => $worker->id, 'type' => 'training', 'date' => '2026-11-20',
            'from_time' => '10:00', 'to_time' => '12:00',
        ])->assertRedirect()->assertSessionHas('success', 'Activity added.');

        $activity = EmployeeActivity::sole();
        $this->assertSame('2026-11-20 10:00', $activity->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-20 12:00', $activity->ends_at->format('Y-m-d H:i'));
    }

    public function test_a_night_block_ends_the_next_day(): void
    {
        $worker = Worker::factory()->create();

        $this->post('/admin/schedule/employees', [
            'worker_id' => $worker->id, 'type' => 'work', 'date' => '2026-11-20',
            'from_time' => '22:00', 'to_time' => '06:00',
        ])->assertRedirect()->assertSessionHas('success', 'Activity added. It ends the next day at 06:00.');

        $activity = EmployeeActivity::sole();
        $this->assertSame('2026-11-21 06:00', $activity->ends_at->format('Y-m-d H:i'));
    }

    public function test_a_night_block_shows_on_both_days_it_covers(): void
    {
        $worker = Worker::factory()->create();
        EmployeeActivity::factory()->create([
            'worker_id' => $worker->id, 'type' => 'work',
            'starts_at' => '2026-11-20 22:00:00', 'ends_at' => '2026-11-21 06:00:00',
        ]);

        $day = fn (string $date) => collect($this->get(route('admin.schedule.employees', ['view' => 'day', 'date' => $date, 'worker_id' => $worker->id]))
            ->assertOk()->viewData('page')['props']['activities'])->where('type', 'work')->values();

        $first = $day('2026-11-20');
        $this->assertSame([['22:00', '24:00', 120, true]], $first->map(fn ($a) => [$a['from'], $a['to'], $a['duration'], $a['continues']])->all());
        $next = $day('2026-11-21');
        $this->assertSame([['00:00', '06:00', 360, true]], $next->map(fn ($a) => [$a['from'], $a['to'], $a['duration'], $a['continued']])->all());

        $month = $this->get(route('admin.schedule.employees', ['view' => 'month', 'date' => '2026-11-20', 'worker_id' => $worker->id]))
            ->assertOk()->viewData('page')['props']['monthByWorker'];
        $this->assertSame('24:00', $month['2026-11-20'][0]['ends_at_time']);
        $this->assertSame('00:00', $month['2026-11-21'][0]['starts_at_time']);
    }

    public function test_an_activity_needs_a_real_time_range_and_a_known_worker(): void
    {
        $worker = Worker::factory()->create();

        $this->post('/admin/schedule/employees', [
            'worker_id' => $worker->id, 'type' => 'work', 'date' => '2026-11-20',
            'from_time' => '08:00', 'to_time' => '08:00',
        ])->assertSessionHasErrors('to_time');

        $this->post('/admin/schedule/employees', [
            'worker_id' => 999999, 'type' => 'nap', 'date' => '20.11.2026',
            'from_time' => '8', 'to_time' => '25:00',
        ])->assertSessionHasErrors(['worker_id', 'type', 'date', 'from_time', 'to_time']);

        $this->assertSame(0, EmployeeActivity::count());
    }

    public function test_a_guest_cannot_add_an_activity(): void
    {
        auth()->logout();
        $worker = Worker::factory()->create();

        $this->post('/admin/schedule/employees', [
            'worker_id' => $worker->id, 'type' => 'work', 'date' => '2026-11-20',
            'from_time' => '08:00', 'to_time' => '10:00',
        ])->assertRedirect('/login');

        $this->assertSame(0, EmployeeActivity::count());
    }
}
