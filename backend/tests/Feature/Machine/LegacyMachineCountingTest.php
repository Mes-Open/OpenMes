<?php

namespace Tests\Feature\Machine;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Connectivity\ActionExecutor;
use App\Services\Machine\MachineCounterService;
use App\Services\Machine\MachineCountingCompatibility;
use App\Services\Machine\MachineSignalIngestor;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyMachineCountingTest extends TestCase
{
    use RefreshDatabase;

    private function setupMachine(string $protocol = 'modbus'): array
    {
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);
        $order = WorkOrder::factory()->inProgress()->create(['line_id' => $line->id, 'planned_qty' => 100, 'produced_qty' => 0, 'counting_source' => 'machine']);
        $batch = Batch::factory()->inProgress()->create(['work_order_id' => $order->id, 'target_qty' => 100]);
        $step = BatchStep::factory()->inProgress()->create(['batch_id' => $batch->id, 'workstation_id' => $ws->id, 'step_number' => 1]);
        $conn = MachineConnection::create(['name' => 'Existing machine', 'protocol' => $protocol, 'line_id' => $line->id, 'is_active' => true]);
        $tag = MachineTag::create(['machine_connection_id' => $conn->id, 'workstation_id' => $ws->id, 'name' => 'Existing counter', 'address' => '1', 'signal_type' => 'good_count', 'is_active' => true])->fresh();

        return [$order, $step, $tag];
    }

    public function test_connectivity_overview_exposes_the_rest_connections_own_counters(): void
    {
        [, , $tag] = $this->setupMachine('rest');
        $counter = app(MachineCounterService::class)->forSource($tag);
        [, , $otherTag] = $this->setupMachine('rest');
        app(MachineCounterService::class)->forSource($otherTag);
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $response = $this->actingAs($admin)->get('/admin/connectivity')->assertOk();
        $connections = $response->getOriginalContent()->getData()['page']['props']['connections'];
        $connection = collect($connections)->firstWhere('id', $tag->machine_connection_id);
        $this->assertSame([$counter->id], collect($connection['counter_ids'])->all());
        $this->get('/admin/connectivity/counters?counter='.$counter->id)->assertOk();
    }

    public function test_existing_modbus_and_opcua_counts_need_no_configuration_or_event_metadata(): void
    {
        foreach (['modbus', 'opcua'] as $protocol) {
            [$order, , $tag] = $this->setupMachine($protocol);
            $ingestor = app(MachineSignalIngestor::class);
            $ingestor->ingest($tag, 100);
            $ingestor->ingest($tag, 104);
            $this->assertEquals(4, $order->fresh()->produced_qty);
            $this->assertNull($tag->counter->configured_at);
        }
    }

    public function test_existing_mqtt_absolute_payload_and_wildcard_topic_still_work(): void
    {
        [$order, , $tag] = $this->setupMachine('mqtt');
        $topic = MachineTopic::create(['machine_connection_id' => $tag->machine_connection_id, 'topic_pattern' => 'machines/+/count', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => 'update_work_order_qty', 'field_path' => '$.qty', 'action_params' => ['order_id' => $order->id], 'is_active' => true]);
        $result = app(ActionExecutor::class)->executeSingle($mapping, ['qty' => 7]);
        $this->assertSame('ok', $result['status'], $result['message']);
        $this->assertEquals(7, $order->fresh()->produced_qty);
    }

    public function test_opt_in_establishes_a_separate_baseline_and_other_channels_stay_legacy(): void
    {
        [$order, $step, $tag] = $this->setupMachine();
        [$other, , $otherTag] = $this->setupMachine('opcua');
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 100);
        $ingestor->ingest($tag, 104);
        $counters = app(MachineCounterService::class);
        $counter = $counters->forSource($tag);
        $counters->configure($counter, ['workstation_id' => $step->workstation_id, 'batch_step_id' => $step->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Migrate one channel'], User::factory()->create()->id);
        $ingestor->ingest($tag, 200, now());
        $ingestor->ingest($tag, 202, now());
        $ingestor->ingest($otherTag, 50);
        $ingestor->ingest($otherTag, 53);
        $this->assertEquals(6, $order->fresh()->produced_qty);
        $this->assertEquals(3, $other->fresh()->produced_qty);
        $this->assertNull($otherTag->counter->configured_at);
    }

    public function test_transfer_compatibility_reports_legacy_channels_but_manual_orders_are_unaffected(): void
    {
        [$order, , $tag] = $this->setupMachine();
        $compatibility = app(MachineCountingCompatibility::class);
        $this->assertContains('Tag #'.$tag->id, $compatibility->transferBlockers());
        $order->update(['counting_source' => 'operator']);
        $this->assertSame([], $compatibility->transferBlockers());
    }

    public function test_runtime_guard_stops_legacy_writes_if_transfer_is_enabled_outside_the_ui(): void
    {
        [$order, $step, $tag] = $this->setupMachine();
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 0);
        $ingestor->ingest($tag, 5);
        $this->assertEquals(0, $order->fresh()->produced_qty);
        $this->assertEquals(0, $step->fresh()->passed_qty);
        $this->assertSame('legacy_incompatible', $tag->counter->readings()->latest('id')->first()->status);
        $this->assertNotNull($step->fresh()->productionBlocker());
    }

    public function test_return_to_legacy_is_audited_and_not_allowed_in_transfer_mode(): void
    {
        [, $step, $tag] = $this->setupMachine();
        $service = app(MachineCounterService::class);
        $counter = $service->forSource($tag);
        $user = User::factory()->create();
        $service->configure($counter, ['workstation_id' => $step->workstation_id, 'batch_step_id' => $step->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Migrate'], $user->id);
        $service->useLegacy($counter, 'Roll back channel configuration', $user->id);
        $this->assertNull($counter->fresh()->configured_at);
        $this->assertSame('legacy_enabled', $counter->readings()->latest('id')->first()->status);
        $service->configure($counter, ['workstation_id' => $step->workstation_id, 'batch_step_id' => $step->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Migrate again'], $user->id);
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->expectException(ValidationException::class);
        $service->useLegacy($counter, 'Unsafe', $user->id);
    }

    public function test_legacy_switch_endpoint_requires_role_and_reason(): void
    {
        [, , $tag] = $this->setupMachine();
        $counter = app(MachineCounterService::class)->forSource($tag);
        $url = '/admin/connectivity/counters/'.$counter->id.'/legacy';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url)->assertForbidden();
        $admin = User::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin->assignRole('Admin');
        $this->actingAs($admin)->postJson($url)->assertUnprocessable();
        $this->post($url, ['note' => 'Keep existing integration'])->assertRedirect();
    }

    public function test_settings_gate_rejects_transfer_and_then_accepts_it_after_migration(): void
    {
        [, $step, $tag] = $this->setupMachine();
        $admin = User::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin->assignRole('Admin');
        $payload = ['production_period' => 'none', 'workflow_mode' => 'status', 'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1, 'schedule_horizon_weeks' => 6, 'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation', 'production_qty_edit_policy' => 'none', 'scanner_mode' => 'hid', 'production_flow_mode' => 'transfer'];
        $this->actingAs($admin)->postJson('/settings/system', $payload)->assertUnprocessable()->assertJsonValidationErrors('production_flow_mode');
        $this->assertSame(ProductionFlow::WHOLE_BATCH, ProductionFlow::mode());
        $service = app(MachineCounterService::class);
        $service->configure($service->forSource($tag), ['workstation_id' => $step->workstation_id, 'batch_step_id' => $step->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Ready for transfer'], $admin->id);
        $this->post('/settings/system', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(ProductionFlow::TRANSFER, ProductionFlow::mode());
    }

    public function test_legacy_mqtt_cannot_write_a_transfer_step(): void
    {
        [$order, $step, $tag] = $this->setupMachine('mqtt');
        $topic = MachineTopic::create(['machine_connection_id' => $tag->machine_connection_id, 'topic_pattern' => 'machine/count', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => 'count_step', 'action_params' => ['step_number' => 1, 'also_count_work_order' => true], 'is_active' => true]);
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $result = app(ActionExecutor::class)->executeSingle($mapping, []);
        $this->assertSame('error', $result['status']);
        $this->assertEquals(0, $step->fresh()->passed_qty);
        $this->assertEquals(0, $order->fresh()->produced_qty);
    }

    public function test_api_setting_endpoint_has_the_same_transfer_gate(): void
    {
        $this->setupMachine();
        $admin = User::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $admin->assignRole('Admin');
        $this->actingAs($admin)->putJson('/api/v1/system/settings/production_flow_mode', ['value' => 'transfer'])->assertUnprocessable();
        $this->assertSame(ProductionFlow::WHOLE_BATCH, ProductionFlow::mode());
    }

    public function test_existing_order_totals_must_not_be_lost_when_switching_to_transfer(): void
    {
        [$order, $step, $tag] = $this->setupMachine();
        $order->update(['produced_qty' => 5]);
        $service = app(MachineCounterService::class);
        $service->configure($service->forSource($tag), ['workstation_id' => $step->workstation_id, 'batch_step_id' => $step->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Migrate'], User::factory()->create()->id);
        $this->assertNotEmpty(app(MachineCountingCompatibility::class)->transferBlockers());
        $step->update(['passed_qty' => 5]);
        $this->assertSame([], app(MachineCountingCompatibility::class)->transferBlockers());
    }
}
