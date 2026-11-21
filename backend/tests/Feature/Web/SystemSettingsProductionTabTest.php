<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Settings → System → Production: what the tab posts is validated, and a
 * field the tab does not show cannot fail the save.
 */
class SystemSettingsProductionTabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** What the settings page posts from the Production tab, with the given changes. */
    private function form(array $changes = []): array
    {
        return array_merge([
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_flow_mode' => 'whole_batch',
            'production_qty_edit_policy' => 'none',
            'production_qty_edit_window_minutes' => 1,
            'scanner_mode' => 'hid',
            'allow_overproduction' => false,
            'force_sequential_steps' => true,
            'workstation_routing_enabled' => false,
            'standard_weekly_hours' => 40,
            'default_pay_type' => 'hourly',
            'default_pay_rate' => null,
        ], $changes);
    }

    private function stored(string $key): mixed
    {
        $raw = DB::table('system_settings')->where('key', $key)->value('value');

        return $raw === null ? null : json_decode($raw, true);
    }

    public function test_a_hidden_correction_window_does_not_block_saving_another_policy(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'production_qty_edit_window_minutes'], ['value' => json_encode(15)]);

        // The admin typed 61 into the window, then picked "No corrections": the
        // window field disappears from the page, but its value is still posted.
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->form([
                'production_qty_edit_policy' => 'none',
                'production_qty_edit_window_minutes' => 61,
                'scanner_mode' => 'manual',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('none', $this->stored('production_qty_edit_policy'));
        $this->assertSame('manual', $this->stored('scanner_mode'));
        // The window the admin had set is kept for when the timed policy returns.
        $this->assertSame(15, $this->stored('production_qty_edit_window_minutes'));
    }

    public function test_the_window_is_saved_with_the_timed_policy(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->form([
                'production_qty_edit_policy' => 'timed',
                'production_qty_edit_window_minutes' => 30,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('timed', $this->stored('production_qty_edit_policy'));
        $this->assertSame(30, $this->stored('production_qty_edit_window_minutes'));
    }

    public static function invalidTimedWindows(): array
    {
        return ['zero' => [0], 'negative' => [-5], 'above an hour' => [61], 'not a number' => ['abc'], 'empty' => [null]];
    }

    #[DataProvider('invalidTimedWindows')]
    public function test_an_invalid_window_is_rejected_with_the_timed_policy(mixed $minutes): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->form([
                'production_qty_edit_policy' => 'timed',
                'production_qty_edit_window_minutes' => $minutes,
            ]))
            ->assertSessionHasErrors('production_qty_edit_window_minutes');

        $this->assertNotSame('timed', $this->stored('production_qty_edit_policy'));
    }

    public static function invalidValues(): array
    {
        return [
            'unknown workflow mode' => ['workflow_mode', 'kanban'],
            'unknown tracking mode' => ['production_tracking_mode', 'per_piece'],
            'unknown flow mode' => ['production_flow_mode', 'continuous'],
            'unknown period' => ['production_period', 'daily'],
            'unknown correction policy' => ['production_qty_edit_policy', 'always'],
            'unknown scanner mode' => ['scanner_mode', 'serial'],
            'unknown pay type' => ['default_pay_type', 'monthly'],
            'negative pay rate' => ['default_pay_rate', -1],
            'text pay rate' => ['default_pay_rate', 'ten'],
            'zero weekly hours' => ['standard_weekly_hours', 0],
            'more hours than a week has' => ['standard_weekly_hours', 169],
            'overproduction not a boolean' => ['allow_overproduction', 'maybe'],
            'sequential steps not a boolean' => ['force_sequential_steps', 'maybe'],
            'routing not a boolean' => ['workstation_routing_enabled', 'maybe'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_an_invalid_value_is_rejected_with_a_readable_message(string $field, mixed $value): void
    {
        $before = DB::table('system_settings')->orderBy('key')->pluck('value', 'key')->all();

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->form([$field => $value]))
            ->assertSessionHasErrors($field);

        // A sentence, not an untranslated key such as "validation.in".
        $this->assertStringNotContainsString('validation.', session('errors')->first($field));
        // Nothing of the form was written.
        $this->assertSame($before, DB::table('system_settings')->orderBy('key')->pluck('value', 'key')->all());
    }

    // ── The REST API applies the tab's rules ────────────────────────────────

    public static function invalidApiValues(): array
    {
        return [
            'unknown tracking mode' => ['production_tracking_mode', 'per_piece'],
            'unknown correction policy' => ['production_qty_edit_policy', 'always'],
            'window above an hour' => ['production_qty_edit_window_minutes', 61],
            'negative window' => ['production_qty_edit_window_minutes', -5],
            'unknown scanner mode' => ['scanner_mode', 'serial'],
            'routing not a boolean' => ['workstation_routing_enabled', 'maybe'],
            'unknown pay type' => ['default_pay_type', 'monthly'],
            'negative pay rate' => ['default_pay_rate', -1],
            'zero weekly hours' => ['standard_weekly_hours', 0],
        ];
    }

    #[DataProvider('invalidApiValues')]
    public function test_the_api_rejects_what_the_settings_tab_rejects(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode('kept')]);
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/system/settings/{$key}", ['value' => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');

        $this->assertSame('kept', $this->stored($key));
    }

    public static function validApiValues(): array
    {
        return [
            'tracking mode' => ['production_tracking_mode', 'hybrid'],
            'correction policy' => ['production_qty_edit_policy', 'timed'],
            'window' => ['production_qty_edit_window_minutes', 30],
            'scanner mode' => ['scanner_mode', 'manual'],
            'routing' => ['workstation_routing_enabled', true],
            'pay type' => ['default_pay_type', 'weekly'],
            'pay rate' => ['default_pay_rate', 12.5],
            'no pay rate' => ['default_pay_rate', null],
            'weekly hours' => ['standard_weekly_hours', 37.5],
        ];
    }

    #[DataProvider('validApiValues')]
    public function test_the_api_accepts_what_the_settings_tab_accepts(string $key, mixed $value): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode('kept')]);
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/system/settings/{$key}", ['value' => $value])
            ->assertOk();

        $this->assertSame($value, $this->stored($key));
    }
}
