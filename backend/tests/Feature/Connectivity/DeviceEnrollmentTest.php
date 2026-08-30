<?php

namespace Tests\Feature\Connectivity;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\DevicePairingCode;
use App\Models\DeviceToken;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-enrolling HTTP sensors: a device redeems a one-time pairing code for a
 * long-lived token, then pulses under it to count production. The token is
 * revocable only from the admin panel (a soft delete), never by the device.
 */
class DeviceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: DevicePairingCode, 1: string} */
    private function pairingCode(array $overrides = []): array
    {
        return DevicePairingCode::issue(array_merge(['name' => 'Beam sensor'], $overrides));
    }

    private function enroll(string $plaintext, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/devices/enroll', array_merge([
            'pairing_code' => $plaintext,
            'name' => 'Beam sensor A',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ], $body));
    }

    public function test_a_valid_pairing_code_enrolls_the_device_and_issues_a_token(): void
    {
        [, $plain] = $this->pairingCode(['line_id' => Line::factory()->create()->id]);

        $response = $this->enroll($plain);

        $response->assertStatus(201)
            ->assertJsonPath('device.name', 'Beam sensor A')
            ->assertJsonPath('device.mac_address', 'AA:BB:CC:DD:EE:FF');

        $token = $response->json('token');
        $this->assertStringStartsWith(DeviceToken::PREFIX, $token);

        // A REST connection wired with a count_step mapping was created.
        $this->assertDatabaseHas('machine_connections', [
            'protocol' => MachineConnection::PROTOCOL_REST,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $this->assertDatabaseHas('topic_mappings', ['action_type' => TopicMapping::ACTION_COUNT_STEP]);
        $this->assertNotNull(DeviceToken::findByPlaintext($token));
    }

    public function test_the_pairing_code_is_spent_after_a_successful_enrollment(): void
    {
        [$code, $plain] = $this->pairingCode();

        $this->enroll($plain)->assertStatus(201);

        $code->refresh();
        $this->assertNotNull($code->used_at);
        $this->assertFalse($code->is_active);

        // A second attempt with the same code is rejected.
        $this->enroll($plain, ['mac_address' => 'AA:BB:CC:DD:EE:00'])->assertStatus(422);
    }

    public function test_an_unknown_pairing_code_is_rejected(): void
    {
        $this->enroll(DevicePairingCode::PREFIX.'doesnotexist')->assertStatus(422);
        $this->assertDatabaseCount('machine_connections', 0);
    }

    public function test_an_expired_pairing_code_is_rejected(): void
    {
        [, $plain] = $this->pairingCode(['expires_at' => now()->subMinute()]);

        $this->enroll($plain)->assertStatus(422);
        $this->assertDatabaseCount('machine_connections', 0);
    }

    public function test_the_mac_address_is_validated(): void
    {
        [, $plain] = $this->pairingCode();

        $this->enroll($plain, ['mac_address' => 'not-a-mac'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mac_address');
    }

    public function test_a_pulse_counts_the_bound_station_step(): void
    {
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        [, $plain] = $this->pairingCode(['line_id' => $line->id, 'workstation_id' => $workstation->id]);
        $token = $this->enroll($plain)->json('token');

        // A running order on the line, with a step bound to the sensor's station.
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_MACHINE,
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 3,
            'workstation_id' => $workstation->id, 'passed_qty' => 0,
        ]);

        $this->withToken($token)->postJson('/api/v1/devices/pulse')->assertStatus(202);

        $this->assertSame(1, $step->fresh()->passed_qty);
    }

    public function test_an_unbound_device_feeds_the_work_order_produced_qty(): void
    {
        $line = Line::factory()->create();
        [, $plain] = $this->pairingCode(['line_id' => $line->id]); // no workstation
        $token = $this->enroll($plain)->json('token');

        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_MACHINE,
        ]);
        Batch::factory()->create(['work_order_id' => $wo->id]);

        $this->withToken($token)->postJson('/api/v1/devices/pulse', ['qty' => 4])->assertStatus(202);

        $this->assertEqualsWithDelta(4.0, (float) $wo->fresh()->produced_qty, 0.0001);
    }

    public function test_a_repeated_idempotency_key_counts_once(): void
    {
        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        [, $plain] = $this->pairingCode(['line_id' => $line->id, 'workstation_id' => $workstation->id]);
        $token = $this->enroll($plain)->json('token');

        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => WorkOrder::COUNTING_MACHINE,
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 3,
            'workstation_id' => $workstation->id, 'passed_qty' => 0,
        ]);

        // Same key twice — the retry is acknowledged but not double-counted.
        $this->withToken($token)->postJson('/api/v1/devices/pulse', ['idempotency_key' => 'abc-1'])->assertStatus(202);
        $this->withToken($token)->postJson('/api/v1/devices/pulse', ['idempotency_key' => 'abc-1'])
            ->assertStatus(202)->assertJsonPath('idempotent_replay', true);

        $this->assertSame(1, $step->fresh()->passed_qty);
    }

    public function test_a_pulse_without_a_token_is_unauthorized(): void
    {
        $this->postJson('/api/v1/devices/pulse')->assertStatus(401);
    }

    public function test_a_pulse_with_a_garbage_token_is_unauthorized(): void
    {
        $this->withToken(DeviceToken::PREFIX.'nope')->postJson('/api/v1/devices/pulse')->assertStatus(401);
    }

    public function test_removing_the_device_revokes_the_token_and_blocks_pulses(): void
    {
        $line = Line::factory()->create();
        [, $plain] = $this->pairingCode(['line_id' => $line->id]);
        $token = $this->enroll($plain)->json('token');

        // Panel-side revoke = soft delete of the connection, which cascades to the
        // device token — the sole revoke path.
        $connection = MachineConnection::where('protocol', MachineConnection::PROTOCOL_REST)->firstOrFail();
        $connection->delete();

        $this->withToken($token)->postJson('/api/v1/devices/pulse')->assertStatus(401);
        $this->assertNull(DeviceToken::findByPlaintext($token));
    }
}
