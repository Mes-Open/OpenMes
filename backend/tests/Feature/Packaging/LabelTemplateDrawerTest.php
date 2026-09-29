<?php

namespace Tests\Feature\Packaging;

use App\Models\LabelTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Label templates are created and edited in a drawer on the list, like every other admin list. */
class LabelTemplateDrawerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function template(array $overrides = []): LabelTemplate
    {
        return LabelTemplate::create(array_merge([
            'name' => 'Carton A', 'type' => LabelTemplate::TYPE_CARTON, 'size' => '100x100', 'barcode_format' => 'code128',
            'fields_config' => ['carton_no' => true, 'qr' => true], 'is_default' => true, 'is_active' => true,
        ], $overrides));
    }

    public function test_the_edit_and_create_routes_land_on_the_list_with_the_drawer_open(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin)->get("/packaging/label-templates/{$template->id}/edit")
            ->assertRedirect("/packaging/label-templates?edit={$template->id}");
        $this->actingAs($this->admin)->get('/packaging/label-templates/create')
            ->assertRedirect('/packaging/label-templates?create=1');

        $this->actingAs($this->admin)->get("/packaging/label-templates?edit={$template->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('packaging/label-templates/Index')
                ->where('editTemplate.id', $template->id)
                ->where('editTemplate.fields_config.carton_no', true)
                ->where('openCreate', false));

        // The drawer's option lists come on a partial reload only.
        $this->actingAs($this->admin)->get('/packaging/label-templates')
            ->assertInertia(fn (Assert $page) => $page->missing('types')->missing('availableFields'));
        $this->actingAs($this->admin)->get('/packaging/label-templates', ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'packaging/label-templates/Index', 'X-Inertia-Partial-Data' => 'types,defaultFieldsByType', 'X-Inertia-Version' => \Inertia\Inertia::getVersion()])
            ->assertOk()
            ->assertJsonPath('props.types.carton', 'Carton (units list)')
            ->assertJsonPath('props.defaultFieldsByType.carton.carton_no', true);
    }

    public function test_every_template_type_previews_on_sample_data_saved_or_not(): void
    {
        // A guest first: actingAs() sticks for the rest of the test.
        $this->get('/packaging/label-templates/'.$this->template()->id.'/preview')->assertRedirect('/login');

        foreach (array_keys(LabelTemplate::TYPES) as $type) {
            $template = $this->template(['name' => "T {$type}", 'type' => $type, 'size' => '100x50', 'is_default' => false]);
            $this->actingAs($this->admin)->get("/packaging/label-templates/{$template->id}/preview")
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        // The drawer's unsaved values render the same way, with only the ticked fields.
        $this->actingAs($this->admin)->get('/packaging/label-templates/preview?type=serial_unit&size=80x40&barcode_format=code128&fields[serial_no]=1&fields[qr]=1&fields[barcode]=0')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->get('/packaging/label-templates/preview?type=nope&size=80x40&barcode_format=code128')->assertStatus(302);

        // EAN-13 cannot encode an order number or a serial: the label still prints (CODE 128 instead of a 500).
        foreach (array_keys(LabelTemplate::TYPES) as $type) {
            $this->actingAs($this->admin)->get("/packaging/label-templates/preview?type={$type}&size=100x50&barcode_format=ean13&fields[barcode]=1&fields[qr]=1")
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_a_carton_label_is_always_one_page_whatever_the_size(): void
    {
        $generator = app(\App\Services\Packaging\LabelGenerator::class);
        foreach (['62x29', '80x40', '100x50', '100x100'] as $size) {
            $template = new LabelTemplate(['name' => 'p', 'type' => LabelTemplate::TYPE_CARTON, 'size' => $size, 'barcode_format' => 'code128',
                'fields_config' => ['carton_no' => true, 'wo_number' => true, 'product' => true, 'quantity' => true, 'barcode' => true, 'qr' => true, 'prod_date' => true, 'location' => true]]);
            $pdf = $generator->pdfPreview($template)->output();
            $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), "{$size}: the six sample units spill onto a second page");
        }
    }

    public function test_each_type_offers_only_the_fields_its_layout_prints(): void
    {
        $this->assertNotContains('pallet_no', LabelTemplate::fieldsForType(LabelTemplate::TYPE_SERIAL_UNIT));
        $this->assertContains('psn', LabelTemplate::fieldsForType(LabelTemplate::TYPE_SERIAL_UNIT));
        $this->assertContains('carton_no', LabelTemplate::fieldsForType(LabelTemplate::TYPE_CARTON));
        $this->assertNotContains('logo', array_keys(LabelTemplate::AVAILABLE_FIELDS));
        $this->assertSame(['serial_no' => 'SN-1001-0001', 'ok' => true], ['serial_no' => 'SN-1001-0001', 'ok' => app(\App\Services\Packaging\LabelGenerator::class)->barcodePng('SN-1001-0001', 'ean13') !== null]);
    }

    public function test_saving_from_the_drawer_stays_on_the_list(): void
    {
        $payload = ['name' => 'Big carton', 'type' => 'carton', 'size' => '150x100', 'barcode_format' => 'code128', 'fields' => ['carton_no' => true, 'qr' => false], 'is_default' => true, 'is_active' => true];

        $this->actingAs($this->admin)->from('/packaging/label-templates')->post('/packaging/label-templates', $payload + ['stay' => 1])
            ->assertRedirect('/packaging/label-templates')
            ->assertSessionHas('success');
        $created = LabelTemplate::where('name', 'Big carton')->firstOrFail();
        $this->assertTrue($created->fields_config['carton_no']);
        $this->assertFalse($created->fields_config['qr']);
        $this->assertArrayNotHasKey('logo', $created->fields_config, 'no unused field is stored');

        $this->actingAs($this->admin)->from('/packaging/label-templates')->put("/packaging/label-templates/{$created->id}", array_merge($payload, ['name' => 'Big carton v2', 'stay' => 1]))
            ->assertRedirect('/packaging/label-templates');
        $this->assertSame('Big carton v2', $created->fresh()->name);

        // The list's synced rows now carry the field config the drawer edits.
        $this->assertContains('fields_config', app(\App\Sync\ShapeRegistry::class)->find('label_templates')->columns());
    }
}
