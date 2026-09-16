<?php

namespace Tests\Feature\Web\Admin;

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
}
