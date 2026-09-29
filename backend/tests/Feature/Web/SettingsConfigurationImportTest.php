<?php

namespace Tests\Feature\Web;

use App\Models\BomItem;
use App\Models\LabelTemplate;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\ScrapReason;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → System → Import: a configuration file prepared by hand or by the
 * export lands with its references intact, whatever ids the database uses.
 */
class SettingsConfigurationImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        // The imported plant timezone is cached process-wide; later tests start from the env zone.
        \App\Support\TimezoneRegistry::flush();

        parent::tearDown();
    }

    private function file(array $data): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('config.json', json_encode($data));
    }

    /** A plant: one line, a bench, a product with a two-step routing, a BOM, sequences, a label, an error code. */
    private function plant(): array
    {
        return [
            'system_settings' => ['language' => 'pl', 'unit_psn_pattern' => '^P-[0-9]+$', 'unit_test_max_attempts' => 3, 'production_flow_mode' => 'transfer', 'app_timezone' => 'Europe/Warsaw', 'not_a_setting' => 'x'],
            'warehouses' => [['id' => 3, 'code' => 'IMP-FG', 'name' => 'Finished goods', 'kind' => 'finished_goods', 'is_default' => true, 'is_active' => true]],
            'lines' => [['id' => 1, 'code' => 'IMP-L', 'name' => 'Imported line', 'is_active' => true, 'warehouse_id' => 3]],
            'workstations' => [
                ['id' => 1, 'code' => 'IMP-A', 'name' => 'Assembly', 'line_id' => 1, 'is_active' => true, 'operator_screens' => ['queue', 'unit_labels']],
                ['id' => 2, 'code' => 'IMP-P', 'name' => 'Packing', 'line_id' => 1, 'is_active' => true],
            ],
            'material_types' => [['id' => 1, 'code' => 'imp_component', 'name' => 'Component']],
            'materials' => [['id' => 1, 'code' => 'IMP-M', 'name' => 'Part', 'material_type_id' => 1, 'unit_of_measure' => 'pcs', 'tracking_type' => 'serial', 'is_active' => true]],
            'product_types' => [['id' => 1, 'code' => 'IMP-PT', 'name' => 'Imported product', 'is_active' => true]],
            'line_product_type' => [['line_id' => 1, 'product_type_id' => 1]],
            'label_templates' => [['id' => 7, 'name' => 'Imported box label', 'type' => 'serial_unit', 'size' => '80x40', 'barcode_format' => 'code128', 'fields_config' => ['serial_no' => true, 'psn' => true], 'is_default' => false, 'is_active' => true]],
            'process_templates' => [['id' => 1, 'product_type_id' => 1, 'name' => 'Imported routing', 'version' => 1, 'is_active' => true]],
            'template_steps' => [
                ['id' => 1, 'process_template_id' => 1, 'step_number' => 1, 'name' => 'Assemble', 'workstation_id' => 1, 'kind' => 'production'],
                ['id' => 2, 'process_template_id' => 1, 'step_number' => 2, 'name' => 'Pack', 'workstation_id' => 2, 'kind' => 'packing', 'config' => ['unit' => 'carton', 'carton_capacity' => 4, 'label_template_id' => 7]],
            ],
            'bom_items' => [['id' => 1, 'process_template_id' => 1, 'template_step_id' => 1, 'material_id' => 1, 'quantity_per_unit' => 1, 'per' => 'unit', 'consumed_at' => 'during', 'scrap_percentage' => 0]],
            'lot_sequences' => [['id' => 1, 'product_type_id' => 1, 'purpose' => 'process_serial', 'name' => 'Imported PSN', 'prefix' => '', 'pattern' => 'P-[seq]', 'pad_size' => 1, 'next_number' => 1, 'reset_period' => 'daily']],
            'scrap_reasons' => [['id' => 1, 'code' => 'IMP-NC', 'name' => 'Imported defect', 'category' => 'method', 'is_active' => true]],
        ];
    }

    public function test_guests_and_non_admins_cannot_import_and_a_file_is_required(): void
    {
        $this->post(route('settings.import'), ['settings_file' => $this->file([])])->assertRedirect(route('login'));
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->actingAs($operator)->post(route('settings.import'), ['settings_file' => $this->file([])])->assertForbidden();
        $this->actingAs($this->admin)->post(route('settings.import'), [])->assertSessionHasErrors('settings_file');
        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => UploadedFile::fake()->createWithContent('x.json', '{broken')])->assertSessionHas('error');
    }

    public function test_references_land_on_the_rows_the_file_created_whatever_ids_they_get(): void
    {
        // Rows already in the database push the new ids away from the file's.
        Line::factory()->count(3)->create();
        ProductType::factory()->count(2)->create();

        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => $this->file($this->plant())])->assertSessionHas('success');

        $line = Line::where('code', 'IMP-L')->firstOrFail();
        $product = ProductType::where('code', 'IMP-PT')->firstOrFail();
        $assembly = Workstation::where('code', 'IMP-A')->firstOrFail();
        $this->assertSame($line->id, $assembly->line_id);
        $this->assertSame(['queue', 'unit_labels'], $assembly->operator_screens);
        $this->assertTrue(DB::table('line_product_type')->where(['line_id' => $line->id, 'product_type_id' => $product->id])->exists());

        $template = ProcessTemplate::where('name', 'Imported routing')->firstOrFail();
        $this->assertSame($product->id, $template->product_type_id);
        $pack = TemplateStep::where('process_template_id', $template->id)->where('step_number', 2)->firstOrFail();
        $this->assertSame(Workstation::where('code', 'IMP-P')->value('id'), $pack->workstation_id);
        $this->assertSame(LabelTemplate::where('name', 'Imported box label')->value('id'), $pack->config['label_template_id']);
        $bom = BomItem::where('process_template_id', $template->id)->firstOrFail();
        $this->assertSame(Material::where('code', 'IMP-M')->value('id'), $bom->material_id);
        $this->assertSame(TemplateStep::where('process_template_id', $template->id)->where('step_number', 1)->value('id'), $bom->template_step_id);
        $this->assertSame($product->id, LotSequence::where('name', 'Imported PSN')->value('product_type_id'));
        $this->assertTrue(ScrapReason::where('code', 'IMP-NC')->exists());
        $this->assertSame(\App\Models\Warehouse::where('code', 'IMP-FG')->value('id'), $line->warehouse_id);

        // Settings: known keys as the form stores them; unknown keys stay out.
        $this->assertSame('"pl"', DB::table('system_settings')->where('key', 'language')->value('value'));
        $this->assertSame('"^P-[0-9]+$"', DB::table('system_settings')->where('key', 'unit_psn_pattern')->value('value'));
        $this->assertFalse(DB::table('system_settings')->where('key', 'not_a_setting')->exists());
        $this->assertSame('Europe/Warsaw', json_decode(DB::table('system_settings')->where('key', 'app_timezone')->value('value')));
        // The flow mode is switched on its own form only.
        $this->assertNotSame('"transfer"', DB::table('system_settings')->where('key', 'production_flow_mode')->value('value'));

        // The same file again updates, it does not duplicate.
        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => $this->file($this->plant())])->assertSessionHas('success');
        $this->assertSame(1, Workstation::where('code', 'IMP-A')->count());
        $this->assertSame(2, TemplateStep::where('process_template_id', $template->id)->count());
        $this->assertSame(1, BomItem::where('process_template_id', $template->id)->count());
        $this->assertSame(1, DB::table('line_product_type')->where(['line_id' => $line->id, 'product_type_id' => $product->id])->count());
    }

    public function test_settings_follow_the_forms_rules_and_references_never_borrow_unrelated_rows(): void
    {
        // A routing deleted and re-created under the same name: the live row is the one the file updates.
        $product = ProductType::factory()->create(['code' => 'IMP-DUP']);
        $trashed = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'name' => 'Routing', 'version' => 1]);
        $trashed->delete();
        $live = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'name' => 'Routing', 'version' => 1]);
        // A row whose id the file happens to use for a line it does not carry.
        $unrelated = Line::factory()->create(['code' => 'IMP-OTHER']);

        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => $this->file([
            'system_settings' => ['unit_psn_pattern' => '^(P-[0-9]+$', 'unit_test_max_attempts' => 0, 'unit_serial_pattern' => '^S-[0-9]+$'],
            'lines' => [['id' => $unrelated->id + 100, 'code' => 'IMP-L2', 'name' => 'Imported line']],
            'workstations' => [['id' => 1, 'code' => 'IMP-W', 'name' => 'Bench', 'line_id' => $unrelated->id]],
            'process_templates' => [['id' => 1, 'product_type_id' => $product->id, 'name' => 'Routing', 'version' => 2]],
        ])])->assertSessionHas('success');

        $this->assertNull(DB::table('system_settings')->where('key', 'unit_psn_pattern')->value('value'), 'a pattern PCRE cannot compile is refused, as on the form');
        $this->assertNotSame('0', DB::table('system_settings')->where('key', 'unit_test_max_attempts')->value('value'));
        $this->assertSame('"^S-[0-9]+$"', DB::table('system_settings')->where('key', 'unit_serial_pattern')->value('value'));
        $this->assertSame(2, (int) $live->fresh()->version);
        $this->assertSame(1, (int) ProcessTemplate::withTrashed()->find($trashed->id)->version);
        $this->assertSoftDeleted($trashed);
        // The file carries lines, so a line id it does not define is not borrowed from whatever row has that id here.
        $this->assertNull(Workstation::where('code', 'IMP-W')->value('line_id'));
    }

    public function test_a_second_default_warehouse_comes_in_as_a_regular_one_and_keeps_its_lines(): void
    {
        \App\Models\Warehouse::factory()->finishedGoods()->isDefault()->create(['code' => 'OLD-FG']);

        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => $this->file([
            'warehouses' => [['id' => 1, 'code' => 'NEW-FG', 'name' => 'New', 'kind' => 'finished_goods', 'is_default' => true, 'is_active' => true]],
            'lines' => [['id' => 1, 'code' => 'IMP-WL', 'name' => 'Line', 'warehouse_id' => 1]],
        ])])->assertSessionHas('success');

        $new = \App\Models\Warehouse::where('code', 'NEW-FG')->firstOrFail();
        $this->assertFalse((bool) $new->is_default);
        $this->assertSame($new->id, Line::where('code', 'IMP-WL')->value('warehouse_id'));
    }

    public function test_the_export_carries_the_new_tables_and_leaves_the_trash_out(): void
    {
        $this->actingAs($this->admin)->post(route('settings.import'), ['settings_file' => $this->file($this->plant())]);
        ScrapReason::factory()->create(['code' => 'GONE'])->delete();

        $export = $this->actingAs($this->admin)->get(route('settings.export'))->assertOk()->json();
        $this->assertContains('IMP-NC', array_column($export['scrap_reasons'], 'code'));
        $this->assertNotContains('GONE', array_column($export['scrap_reasons'], 'code'));
        $this->assertContains('Imported PSN', array_column($export['lot_sequences'], 'name'));
        $this->assertNotEmpty($export['line_product_type']);
    }
}
