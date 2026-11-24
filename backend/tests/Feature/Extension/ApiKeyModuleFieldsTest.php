<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A module can hang its own field on the ERP key form — the way a multi-plant
 * module binds a key to one plant — through the same three seams as the user
 * and line forms: display hook, validation filter, persist hook. Plus a filter
 * for the short labels shown on the key's row.
 */
class ApiKeyModuleFieldsTest extends TestCase
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

    public function test_without_a_module_the_form_and_rows_carry_nothing_extra(): void
    {
        $response = $this->actingAs($this->admin)->get('/settings/api-tokens')->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['erpKeyHooks']);

        $this->actingAs($this->admin)->postJson('/settings/api-keys', [
            'name' => 'Odoo', 'scopes' => ['erp:masterdata:read'],
        ])->assertCreated()->assertJsonPath('data.labels', []);
    }

    public function test_a_module_field_is_shown_validated_persisted_and_labelled(): void
    {
        $saved = null;
        app(HookRegistry::class)->listen('display.settings.api_keys.form.fields', fn () => [
            'name' => 'module_test_plant',
            'type' => 'select',
            'label' => 'Plant',
            'options' => [['value' => 'north', 'label' => 'North']],
        ]);
        app(FilterRegistry::class)->addFilter('validation.api_keys', fn (array $rules) => $rules + [
            'module_test_plant' => ['nullable', 'in:north'],
        ]);
        app(HookRegistry::class)->listen('persist.api_keys', function (array $ctx) use (&$saved) {
            $saved = $ctx;
        });
        app(FilterRegistry::class)->addFilter('api_keys.labels', fn (array $labels, array $ctx) => [
            ...$labels, 'Plant: '.$ctx['apiKey']->name,
        ]);

        $props = $this->actingAs($this->admin)->get('/settings/api-tokens')->assertOk()->viewData('page')['props'];
        $this->assertSame('module_test_plant', $props['erpKeyHooks']['display.settings.api_keys.form.fields'][0]['name']);

        $this->actingAs($this->admin)->postJson('/settings/api-keys', [
            'name' => 'Odoo', 'scopes' => ['erp:masterdata:read'], 'module_test_plant' => 'south',
        ])->assertUnprocessable()->assertJsonValidationErrors('module_test_plant');
        $this->assertSame(0, ApiKey::count());

        $this->actingAs($this->admin)->postJson('/settings/api-keys', [
            'name' => 'Odoo', 'scopes' => ['erp:masterdata:read'], 'module_test_plant' => 'north',
        ])->assertCreated()->assertJsonPath('data.labels', ['Plant: Odoo']);

        $this->assertSame('store', $saved['action']);
        $this->assertSame('north', $saved['input']['module_test_plant']);
        $this->assertSame(ApiKey::first()->id, $saved['apiKey']->id);

        $this->actingAs($this->admin)->getJson('/settings/api-keys')
            ->assertOk()->assertJsonPath('data.0.labels', ['Plant: Odoo']);
    }
}
