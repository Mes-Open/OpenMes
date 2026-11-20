<?php

namespace Tests\Feature\Web;

use App\Models\CustomFieldDefinition;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\Tool;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin form that carries custom fields, against every field type: the
 * create and edit endpoints validate, coerce and store each type (files
 * included), the forms ship the definitions and the stored values, and an
 * update that leaves the fields alone does not wipe them.
 */
class CustomFieldEntityFormsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ?Line $wsLine = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function defineAll(string $entity): void
    {
        $defs = [
            ['f_text', 'text', []], ['f_area', 'textarea', []], ['f_num', 'number', ['min' => 0, 'max' => 100]],
            ['f_int', 'integer', []], ['f_bool', 'boolean', []], ['f_date', 'date', []], ['f_dt', 'datetime', []],
            ['f_sel', 'select', ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]],
            ['f_multi', 'multiselect', ['options' => [['value' => 'x', 'label' => 'X'], ['value' => 'y', 'label' => 'Y']]]],
            ['f_file', 'file', []], ['f_img', 'image', []],
        ];
        foreach ($defs as $i => [$key, $type, $config]) {
            CustomFieldDefinition::create(['entity_type' => $entity, 'key' => $key, 'label' => strtoupper($key),
                'type' => $type, 'required' => false, 'position' => $i, 'config' => $config]);
        }
        CustomFieldDefinition::create(['entity_type' => $entity, 'key' => 'f_req', 'label' => 'Required one',
            'type' => 'text', 'required' => true, 'position' => 99, 'config' => []]);
    }

    private function values(): array
    {
        return ['f_text' => 'hello', 'f_area' => "line1\nline2", 'f_num' => '12.5', 'f_int' => '7', 'f_bool' => '1',
            'f_date' => '2026-11-19', 'f_dt' => '2026-11-19T10:30', 'f_sel' => 'b', 'f_multi' => ['x', 'y'], 'f_req' => 'yes'];
    }

    public static function entities(): array
    {
        return [
            'material' => ['material'], 'product_type' => ['product_type'], 'line' => ['line'],
            'workstation' => ['workstation'], 'worker' => ['worker'], 'shift' => ['shift'], 'tool' => ['tool'],
            'work_order' => ['work_order'],
        ];
    }

    /** [store URL, create payload, model class, record URL, update payload] for an entity form. */
    private function spec(string $entity): array
    {
        return match ($entity) {
            'material' => ['/admin/materials', fn () => ['code' => 'M1', 'name' => 'M', 'material_type_id' => MaterialType::factory()->create()->id],
                Material::class, fn ($m) => "/admin/materials/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name, 'material_type_id' => $m->material_type_id]],
            'product_type' => ['/admin/product-types', fn () => ['code' => 'PT1', 'name' => 'PT'],
                ProductType::class, fn ($m) => "/admin/product-types/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name]],
            'line' => ['/admin/lines', fn () => ['code' => 'L1', 'name' => 'L'],
                Line::class, fn ($m) => "/admin/lines/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name]],
            'workstation' => ['/admin/lines/'.($this->wsLine ??= Line::factory()->create())->id.'/workstations', fn () => ['code' => 'WS1', 'name' => 'WS'],
                Workstation::class, fn ($m) => "/admin/lines/{$m->line_id}/workstations/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name]],
            'worker' => ['/admin/workers', fn () => ['code' => 'W1', 'name' => 'Worker'],
                Worker::class, fn ($m) => "/admin/workers/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name]],
            'shift' => ['/admin/shifts', fn () => ['code' => 'S1', 'name' => 'Shift', 'start_time' => '06:00', 'end_time' => '14:00'],
                Shift::class, fn ($m) => "/admin/shifts/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name, 'start_time' => '06:00', 'end_time' => '14:00']],
            'tool' => ['/admin/tools', fn () => ['code' => 'T1', 'name' => 'Tool'],
                Tool::class, fn ($m) => "/admin/tools/{$m->id}", fn ($m) => ['code' => $m->code, 'name' => $m->name]],
            'work_order' => ['/admin/work-orders', function () {
                $line = Line::factory()->create();
                $pt = ProductType::factory()->create();
                ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $pt->id]);

                return ['order_no' => 'WO-P1', 'line_id' => $line->id, 'product_type_id' => $pt->id, 'planned_qty' => 10];
            }, WorkOrder::class, fn ($m) => "/admin/work-orders/{$m->id}", fn ($m) => ['order_no' => $m->order_no, 'planned_qty' => 10, 'status' => $m->status]],
        };
    }

    #[DataProvider('entities')]
    public function test_create_stores_every_type(string $entity): void
    {
        $this->defineAll($entity);
        [$url, $base, $class] = $this->spec($entity);

        $r = $this->actingAs($this->admin)->post($url, array_merge($base(), [
            'custom_fields' => $this->values(),
            'custom_field_files' => ['f_file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
                'f_img' => UploadedFile::fake()->image('pic.png', 20, 20)],
        ]));
        $r->assertSessionHasNoErrors();

        $model = $class::latest('id')->first();
        $this->assertNotNull($model, "$entity not created");
        $cf = $model->custom_fields;
        $this->assertSame('hello', $cf['f_text'] ?? null, "$entity text");
        $this->assertSame("line1\nline2", $cf['f_area'] ?? null, "$entity textarea");
        $this->assertSame(12.5, $cf['f_num'] ?? null, "$entity number");
        $this->assertSame(7, $cf['f_int'] ?? null, "$entity integer");
        $this->assertTrue($cf['f_bool'] ?? null, "$entity bool");
        $this->assertSame('2026-11-19', $cf['f_date'] ?? null, "$entity date");
        $this->assertSame('2026-11-19T10:30', $cf['f_dt'] ?? null, "$entity datetime");
        $this->assertSame('b', $cf['f_sel'] ?? null, "$entity select");
        $this->assertSame(['x', 'y'], $cf['f_multi'] ?? null, "$entity multiselect");
        $this->assertSame('doc.pdf', $cf['f_file']['name'] ?? null, "$entity file");
        $this->assertSame('pic.png', $cf['f_img']['name'] ?? null, "$entity image");
        Storage::assertExists($cf['f_file']['path']);
        Storage::assertExists($cf['f_img']['path']);
    }

    #[DataProvider('entities')]
    public function test_validation(string $entity): void
    {
        $this->defineAll($entity);
        [$url, $base] = $this->spec($entity);
        $payload = $base();

        $vals = $this->values();
        unset($vals['f_req']);
        $this->actingAs($this->admin)->post($url, array_merge($payload, ['custom_fields' => $vals]))
            ->assertSessionHasErrors('custom_fields.f_req');

        $this->post($url, array_merge($payload, ['custom_fields' => array_merge($this->values(), ['f_sel' => 'zzz', 'f_num' => '500', 'f_int' => '1.5', 'f_multi' => ['nope']])]))
            ->assertSessionHasErrors(['custom_fields.f_sel', 'custom_fields.f_num', 'custom_fields.f_int', 'custom_fields.f_multi.0']);
    }

    #[DataProvider('entities')]
    public function test_required_enforced_when_custom_fields_omitted(string $entity): void
    {
        $this->defineAll($entity);
        [$url, $base] = $this->spec($entity);
        $this->actingAs($this->admin)->post($url, $base())->assertSessionHasErrors('custom_fields.f_req');
    }

    #[DataProvider('entities')]
    public function test_update_and_edit_form(string $entity): void
    {
        $this->defineAll($entity);
        [$url, $base, $class, $updUrl, $updBase] = $this->spec($entity);
        $this->actingAs($this->admin)->post($url, array_merge($base(), [
            'custom_fields' => $this->values(),
            'custom_field_files' => ['f_file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
        $model = $class::latest('id')->first();
        $filePath = $model->custom_fields['f_file']['path'];

        // Edit form must ship the definitions and the stored values.
        $edit = $this->get($updUrl($model).'/edit');
        $edit->assertOk();
        $props = $edit->viewData('page')['props'];
        $this->assertNotEmpty($props['customFields'] ?? [], "$entity edit form has no customFields definitions");
        $json = json_encode($props);
        $this->assertStringContainsString('hello', $json, "$entity edit form does not carry stored custom values");

        // Update: change values, no new file -> file must survive.
        $vals = array_merge($this->values(), ['f_text' => 'changed', 'f_sel' => 'a', 'f_bool' => '0']);
        $this->put($updUrl($model), array_merge($updBase($model), ['custom_fields' => $vals]))->assertSessionHasNoErrors();
        $cf = $model->fresh()->custom_fields;
        $this->assertSame('changed', $cf['f_text'] ?? null, "$entity update text");
        $this->assertSame('a', $cf['f_sel'] ?? null, "$entity update select");
        $this->assertFalse($cf['f_bool'] ?? null, "$entity update bool false");
        $this->assertSame($filePath, $cf['f_file']['path'] ?? null, "$entity file lost on update");

        // Clearing an optional value removes it.
        $vals['f_text'] = '';
        $this->put($updUrl($model), array_merge($updBase($model), ['custom_fields' => $vals]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('f_text', $model->fresh()->custom_fields, "$entity clear text");

        // Remove the file.
        $this->put($updUrl($model), array_merge($updBase($model), ['custom_fields' => $vals, 'custom_field_files_remove' => ['f_file']]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('f_file', $model->fresh()->custom_fields ?? [], "$entity file remove");
        Storage::assertMissing($filePath);

        // Updating the record without touching custom fields keeps them.
        $this->put($updUrl($model), $updBase($model));
        $this->assertSame('a', $model->fresh()->custom_fields['f_sel'] ?? null, "$entity values wiped by unrelated update");
    }

    #[DataProvider('entities')]
    public function test_create_form_ships_definitions(string $entity): void
    {
        $this->defineAll($entity);
        [$url] = $this->spec($entity);
        $r = $this->actingAs($this->admin)->get($url.'/create');
        $r->assertOk();
        $this->assertNotEmpty($r->viewData('page')['props']['customFields'] ?? [], "$entity create form has no customFields");
    }

    public function test_issue_admin_and_operator_paths(): void
    {
        $this->defineAll('issue');
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $type = IssueType::factory()->create();
        $base = ['work_order_id' => $wo->id, 'issue_type_id' => $type->id, 'title' => 'T', 'effect' => 'none'];

        $this->actingAs($this->admin)->post('/admin/issues', $base)->assertSessionHasErrors('custom_fields.f_req');
        $this->post('/admin/issues', array_merge($base, ['custom_fields' => $this->values()]))->assertSessionHasNoErrors();
        $this->assertSame(['x', 'y'], \App\Models\Issue::latest('id')->first()->custom_fields['f_multi'] ?? null);

        $op = User::factory()->create();
        $op->assignRole('Operator');
        $this->actingAs($op)->withSession(['selected_line_id' => $wo->line_id])
            ->post('/operator/issue', array_merge($base, ['custom_fields' => $this->values()]))->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\Issue::count());
        $this->assertSame('hello', \App\Models\Issue::latest('id')->first()->custom_fields['f_text'] ?? null);
    }

    public function test_definition_key_reusable_after_soft_delete(): void
    {
        $payload = ['entity_type' => 'material', 'key' => 'dup', 'label' => 'Dup', 'type' => 'text'];
        $this->actingAs($this->admin)->post('/admin/custom-fields', $payload)->assertSessionHasNoErrors();
        $def = CustomFieldDefinition::sole();
        $this->delete("/admin/custom-fields/{$def->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($def);
        $this->post('/admin/custom-fields', $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, CustomFieldDefinition::count());
    }

    public function test_deactivated_definition_is_not_shown_or_required(): void
    {
        $this->defineAll('tool');
        $req = CustomFieldDefinition::where('key', 'f_req')->sole();
        $this->actingAs($this->admin)->post("/admin/custom-fields/{$req->id}/toggle-active");
        $this->post('/admin/tools', ['code' => 'T9', 'name' => 'X'])->assertSessionHasNoErrors();
    }

    public function test_definition_edit_change_type_to_select_and_options(): void
    {
        $this->actingAs($this->admin)->post('/admin/custom-fields', ['entity_type' => 'tool', 'key' => 'k', 'label' => 'K', 'type' => 'text'])->assertSessionHasNoErrors();
        $def = CustomFieldDefinition::sole();
        $this->get("/admin/custom-fields/{$def->id}/edit")->assertOk();
        $this->put("/admin/custom-fields/{$def->id}", ['entity_type' => 'tool', 'key' => 'k', 'label' => 'K2', 'type' => 'select',
            'config' => ['options' => [['value' => 'v', 'label' => 'V']]]])->assertSessionHasNoErrors();
        $this->assertSame('select', $def->fresh()->type->value);
        $this->assertSame('v', $def->fresh()->config['options'][0]['value']);
    }

    public function test_file_download_route(): void
    {
        $this->defineAll('tool');
        $this->actingAs($this->admin)->post('/admin/tools', ['code' => 'T1', 'name' => 'Tool', 'custom_fields' => ['f_req' => 'y'],
            'custom_field_files' => ['f_file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')]])->assertSessionHasNoErrors();
        $path = Tool::sole()->custom_fields['f_file']['path'];
        $this->get('/admin/custom-field-files/'.basename($path))->assertOk();
        $op = User::factory()->create();
        $op->assignRole('Operator');
        $this->actingAs($op)->get('/admin/custom-field-files/'.basename($path))->assertForbidden();
    }
}
