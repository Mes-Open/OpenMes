<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ERP routing import (`/erp/process-templates/import`) and the products
 * import's `create_default_template` flag: a product and its routing from the
 * ERP are enough to accept the first work order.
 */
class ErpProcessTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private Line $line;

    private Workstation $cut;

    private Workstation $assembly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);

        $this->line = Line::factory()->create(['code' => 'L1']);
        $this->cut = Workstation::factory()->create(['line_id' => $this->line->id, 'code' => 'WS-CUT']);
        $this->assembly = Workstation::factory()->create(['line_id' => $this->line->id, 'code' => 'WS-ASM']);
    }

    private function keyWith(array $scopes, ?int $tenantId = null): string
    {
        [, $plaintext] = ApiKey::issue([
            'name' => 'ERP routing key',
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $tenantId ?? $this->tenantId,
        ]);

        return $plaintext;
    }

    private function import(array $payload, ?string $key = null)
    {
        return $this->withHeader('X-Api-Key', $key ?? $this->keyWith([ApiScope::MasterDataWrite]))
            ->postJson('/api/v1/erp/process-templates/import', $payload);
    }

    private function importProducts(array $payload, ?string $key = null)
    {
        return $this->withHeader('X-Api-Key', $key ?? $this->keyWith([ApiScope::MasterDataWrite]))
            ->postJson('/api/v1/erp/products/import', $payload);
    }

    private function importOrder(string $orderNo, string $productCode)
    {
        return $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::OrdersImport]))
            ->postJson('/api/v1/erp/work-orders/import', ['orders' => [[
                'order_no' => $orderNo,
                'line_code' => 'L1',
                'product_type_code' => $productCode,
                'planned_qty' => 100,
            ]]]);
    }

    /** ERP operations 10 / 20 — the canonical two-step routing. */
    private function routing(string $productCode = 'CHAIR', array $overrides = []): array
    {
        return array_merge([
            'product_type_code' => $productCode,
            'steps' => [
                ['step_number' => 20, 'name' => 'Assembly', 'workstation_code' => 'WS-ASM', 'setup_time_minutes' => 5, 'run_time_per_unit_minutes' => 4, 'instruction' => 'Glue and screw the legs'],
                ['step_number' => 10, 'name' => 'Cutting', 'workstation_code' => 'WS-CUT', 'line_code' => 'L1', 'setup_time_minutes' => 15, 'run_time_per_unit_minutes' => 2.5],
            ],
        ], $overrides);
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_imported_routing_lets_an_erp_work_order_be_accepted(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR', 'name' => 'Chair']);

        // Without a template the order is refused — the problem this endpoint solves.
        $this->importOrder('ERP-0', 'CHAIR')->assertStatus(207);

        $this->import(['templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors', []);

        $template = ProcessTemplate::where('product_type_id', $product->id)->sole();
        $this->assertSame(1, $template->version);
        $this->assertTrue($template->is_active);
        $this->assertSame('Chair', $template->name);
        $this->assertSame($this->tenantId, $template->tenant_id);

        // ERP numbers 10/20 become 1/2, in ERP order.
        $steps = $template->steps()->get();
        $this->assertSame([1, 2], $steps->pluck('step_number')->all());
        $this->assertSame(['Cutting', 'Assembly'], $steps->pluck('name')->all());
        $this->assertSame([$this->cut->id, $this->assembly->id], $steps->pluck('workstation_id')->all());
        $this->assertSame([15, 5], $steps->pluck('setup_time_minutes')->all());
        $this->assertSame(['2.50', '4.00'], $steps->pluck('run_time_per_unit_minutes')->all());
        $this->assertSame([TemplateStep::KIND_PRODUCTION, TemplateStep::KIND_PRODUCTION], $steps->pluck('kind')->all());
        $this->assertSame('Glue and screw the legs', $steps[1]->instruction);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => ProcessTemplate::class,
            'entity_id' => $template->id,
            'action' => 'process_template.erp_imported',
        ]);

        $this->importOrder('ERP-1', 'CHAIR')->assertOk()->assertJsonPath('data.imported', 1);

        $snapshot = WorkOrder::where('order_no', 'ERP-1')->sole()->process_snapshot;
        $this->assertSame(1, $snapshot['template_version']);
        $this->assertCount(2, $snapshot['steps']);
    }

    public function test_template_name_can_be_given(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);

        $this->import(['templates' => [$this->routing('CHAIR', ['template_name' => 'Chair routing SAP'])]])->assertOk();

        $this->assertSame('Chair routing SAP', ProcessTemplate::sole()->name);
    }

    public function test_new_version_becomes_the_chosen_template_and_keeps_bom(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $v1 = ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $product->id, 'version' => 1, 'is_active' => true]);
        $material = Material::factory()->create();
        BomItem::factory()->create([
            'process_template_id' => $v1->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 4,
            'template_step_id' => $v1->steps()->where('step_number', 2)->value('id'),
        ]);

        $this->import(['templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.rows', [['row' => 1, 'code' => 'CHAIR', 'version' => 2]]);

        $v2 = ProcessTemplate::where('product_type_id', $product->id)->where('version', 2)->sole();
        $this->assertTrue($v2->is_active);
        $this->assertTrue($v1->fresh()->is_active, 'earlier versions stay active, as in the admin UI');

        // The recipe moved with the routing, attached to the same step number.
        $copied = BomItem::where('process_template_id', $v2->id)->sole();
        $this->assertSame($material->id, $copied->material_id);
        $this->assertEquals(4, (float) $copied->quantity_per_unit);
        $this->assertSame($v2->steps()->where('step_number', 2)->value('id'), $copied->template_step_id);
        $this->assertSame(1, BomItem::where('process_template_id', $v1->id)->count());

        // With two active versions, a new order deterministically gets the newest.
        $this->importOrder('ERP-2', 'CHAIR')->assertOk();
        $snapshot = WorkOrder::where('order_no', 'ERP-2')->sole()->process_snapshot;
        $this->assertSame(2, $snapshot['template_version']);
        $this->assertSame($material->id, $snapshot['bom'][0]['material_id']);
    }

    public function test_reimporting_an_identical_routing_is_skipped(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);

        $this->import(['templates' => [$this->routing()]])->assertOk()->assertJsonPath('data.imported', 1);
        $this->import(['templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, ProcessTemplate::count());

        // Any change to the routing is a new version.
        $changed = $this->routing();
        $changed['steps'][0]['run_time_per_unit_minutes'] = 4.5;
        $this->import(['templates' => [$changed]])->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertSame([1, 2], ProcessTemplate::orderBy('version')->pluck('version')->all());
    }

    public function test_new_version_does_not_change_the_snapshot_of_an_order_in_progress(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $this->import(['templates' => [$this->routing()]])->assertOk();
        $this->importOrder('ERP-RUN', 'CHAIR')->assertOk();

        $order = WorkOrder::where('order_no', 'ERP-RUN')->sole();
        $order->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $snapshotBefore = $order->fresh()->process_snapshot;
        $v1 = ProcessTemplate::where('product_type_id', $product->id)->sole();
        $v1StepsBefore = $v1->steps()->get()->toArray();

        $changed = $this->routing('CHAIR', ['steps' => [
            ['step_number' => 10, 'name' => 'Laser cutting', 'workstation_code' => 'WS-CUT'],
        ]]);
        $this->import(['templates' => [$changed]])->assertOk()->assertJsonPath('data.imported', 1);
        $this->import(['mode' => 'replace_draft', 'templates' => [$changed]])->assertOk();

        $this->assertSame($snapshotBefore, $order->fresh()->process_snapshot);
        $this->assertSame($v1StepsBefore, $v1->fresh()->steps()->get()->toArray());
        $this->assertTrue($v1->fresh()->is_active);
    }

    public function test_replace_draft_rewrites_an_inactive_template_without_activating_it(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $active = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'version' => 1, 'is_active' => true]);
        $draft = ProcessTemplate::factory()->withSteps(3)->create(['product_type_id' => $product->id, 'version' => 2, 'is_active' => false]);
        $keptStepId = $draft->steps()->where('step_number', 1)->value('id');
        $staleStep = $draft->steps()->where('step_number', 3)->first();

        $this->import(['mode' => 'replace_draft', 'templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.imported', 0);

        $draft->refresh();
        $this->assertFalse($draft->is_active);
        $this->assertSame(['Cutting', 'Assembly'], $draft->steps()->pluck('name')->all());
        // Matching numbers are updated in place, a number no longer listed is soft-deleted.
        $this->assertSame($keptStepId, $draft->steps()->where('step_number', 1)->value('id'));
        $this->assertSoftDeleted('template_steps', ['id' => $staleStep->id]);
        $this->assertSame(1, $active->fresh()->steps()->count());
        $this->assertSame(2, ProcessTemplate::where('product_type_id', $product->id)->count());
    }

    public function test_replace_draft_creates_an_inactive_version_when_there_is_no_draft(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $active = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'version' => 1, 'is_active' => true]);
        $activeSteps = $active->steps()->get()->toArray();

        $this->import(['mode' => 'replace_draft', 'templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $draft = ProcessTemplate::where('product_type_id', $product->id)->where('version', 2)->sole();
        $this->assertFalse($draft->is_active);
        $this->assertSame(2, $draft->steps()->count());
        $this->assertSame($activeSteps, $active->fresh()->steps()->get()->toArray());

        // The draft does not make orders importable on its own, and does not
        // displace the active version.
        $this->importOrder('ERP-D', 'CHAIR')->assertOk();
        $this->assertSame(1, WorkOrder::where('order_no', 'ERP-D')->sole()->process_snapshot['template_version']);
    }

    public function test_replace_draft_leaves_an_inactive_template_a_work_order_was_frozen_from(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $used = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'version' => 1, 'is_active' => false]);
        WorkOrder::factory()->create(['product_type_id' => $product->id, 'line_id' => $this->line->id, 'process_snapshot' => $used->toSnapshot()]);
        $usedSteps = $used->steps()->get()->toArray();

        $this->import(['mode' => 'replace_draft', 'templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertSame($usedSteps, $used->fresh()->steps()->get()->toArray());
        $this->assertSame(2, ProcessTemplate::where('product_type_id', $product->id)->max('version'));
    }

    public function test_replace_draft_leaves_a_retired_version_older_than_the_active_one(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $retired = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'version' => 1, 'is_active' => false]);
        ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'version' => 2, 'is_active' => true]);
        $retiredSteps = $retired->steps()->get()->toArray();

        // Activating a rewritten v1 would change nothing (orders take the newest
        // active version), so the draft goes on top as v3 instead.
        $this->import(['mode' => 'replace_draft', 'templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.rows.0.version', 3);

        $this->assertSame($retiredSteps, $retired->fresh()->steps()->get()->toArray());
        $this->assertFalse(ProcessTemplate::where('version', 3)->sole()->is_active);
    }

    public function test_reimporting_the_active_routing_is_skipped_when_a_draft_sits_on_top(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        $this->import(['templates' => [$this->routing()]])->assertOk();
        // The request cleared the tenant context, so the tenant is set explicitly.
        ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id, 'tenant_id' => $this->tenantId, 'version' => 2, 'is_active' => false]);

        $this->import(['templates' => [$this->routing()]])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(2, ProcessTemplate::where('product_type_id', $product->id)->count());
    }

    public function test_boms_import_attaches_to_the_imported_template(): void
    {
        $product = ProductType::factory()->create(['code' => 'CHAIR']);
        Material::factory()->create(['code' => 'WOOD']);

        $this->import(['templates' => [$this->routing()]])->assertOk();

        $this->withHeader('X-Api-Key', $this->keyWith([ApiScope::MasterDataWrite]))
            ->postJson('/api/v1/erp/boms/import', ['recipes' => [[
                'product_type_code' => 'CHAIR',
                'components' => [['material_code' => 'WOOD', 'quantity_per_unit' => 2]],
            ]]])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $template = ProcessTemplate::where('product_type_id', $product->id)->sole();
        $this->assertSame(1, $template->bomItems()->count());
    }

    public function test_imported_versions_and_steps_are_shown_on_the_admin_process_template_pages(): void
    {
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $admin = tap(User::factory()->create(['tenant_id' => $this->tenantId]), fn (User $u) => $u->assignRole('Admin'));
        $product = ProductType::factory()->create(['code' => 'CHAIR']);

        $this->import(['templates' => [$this->routing()]])->assertOk();
        $changed = $this->routing();
        $changed['steps'][0]['name'] = 'Final assembly';
        $this->import(['templates' => [$changed]])->assertOk();

        $this->actingAs($admin)
            ->get("/admin/product-types/{$product->id}/process-templates")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/process-templates/Index')
                ->where('templates.0.version', 2)
                ->where('templates.0.is_active', true)
                ->where('templates.0.steps_count', 2)
                ->where('templates.1.version', 1)
                ->where('templates.1.is_active', true));

        $v2 = ProcessTemplate::where('version', 2)->sole();

        $this->actingAs($admin)
            ->get("/admin/product-types/{$product->id}/process-templates/{$v2->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/process-templates/Show')
                ->where('processTemplate.steps.0.name', 'Cutting')
                ->where('processTemplate.steps.0.workstation_id', $this->cut->id)
                ->where('processTemplate.steps.0.setup_time_minutes', 15)
                ->where('processTemplate.steps.1.name', 'Final assembly')
                ->where('processTemplate.steps.1.instruction', 'Glue and screw the legs'));
    }

    // ── 207 and rollback ─────────────────────────────────────────────────────

    public function test_unknown_workstation_reports_a_row_error_and_writes_nothing_for_that_row(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);
        $table = ProductType::factory()->create(['code' => 'TABLE']);

        $bad = $this->routing('TABLE');
        $bad['steps'][1]['workstation_code'] = 'WS-NOPE';

        $response = $this->import(['templates' => [$this->routing('CHAIR'), $bad]]);

        $response->assertStatus(207)
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors.0.row', 2)
            ->assertJsonPath('data.errors.0.field', 'steps.1.workstation_code');

        $this->assertDatabaseMissing('process_templates', ['product_type_id' => $table->id]);
        $this->assertSame(2, TemplateStep::count(), 'only the CHAIR routing was written');
    }

    public function test_row_errors_for_unknown_product_line_and_mismatched_references(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);
        $otherLine = Line::factory()->create(['code' => 'L2']);
        Workstation::factory()->create(['line_id' => $otherLine->id, 'code' => 'WS-PAINT']);

        $response = $this->import(['templates' => [
            $this->routing('NOPE'),
            $this->routing('CHAIR', ['steps' => [['step_number' => 1, 'name' => 'Cut', 'line_code' => 'L1']]]),
            $this->routing('CHAIR', ['steps' => [['step_number' => 1, 'name' => 'Cut', 'workstation_code' => 'WS-CUT', 'line_code' => 'L9']]]),
            $this->routing('CHAIR', ['steps' => [['step_number' => 1, 'name' => 'Paint', 'workstation_code' => 'WS-PAINT', 'line_code' => 'L1']]]),
        ]]);

        $response->assertStatus(207)->assertJsonPath('data.imported', 0);
        $this->assertSame(
            [
                [1, 'product_type_code'],
                [2, 'steps.0.line_code'],
                [3, 'steps.0.line_code'],
                [4, 'steps.0.workstation_code'],
            ],
            collect($response->json('data.errors'))->map(fn ($e) => [$e['row'], $e['field']])->all(),
        );
        $this->assertSame("Workstation 'WS-PAINT' not found on line 'L1'", $response->json('data.errors.3.message'));
        $this->assertSame(0, ProcessTemplate::count());
    }

    // ── 422 ──────────────────────────────────────────────────────────────────

    public static function malformedPayloads(): array
    {
        $step = ['step_number' => 10, 'name' => 'Cut'];
        $row = fn (array $steps) => ['templates' => [['product_type_code' => 'CHAIR', 'steps' => $steps]]];

        return [
            'templates missing' => [[], 'templates'],
            'templates empty' => [['templates' => []], 'templates'],
            'product code missing' => [['templates' => [['steps' => [$step]]]], 'templates.0.product_type_code'],
            'steps not a list' => [['templates' => [['product_type_code' => 'CHAIR', 'steps' => 'cut']]], 'templates.0.steps'],
            'steps empty' => [$row([]), 'templates.0.steps'],
            'step number not a number' => [$row([[...$step, 'step_number' => 'abc']]), 'templates.0.steps.0.step_number'],
            'step number zero' => [$row([[...$step, 'step_number' => 0]]), 'templates.0.steps.0.step_number'],
            'step number duplicated' => [$row([$step, [...$step, 'name' => 'Again']]), 'templates.0.steps.1.step_number'],
            'step name missing' => [$row([['step_number' => 10]]), 'templates.0.steps.0.name'],
            'negative setup time' => [$row([[...$step, 'setup_time_minutes' => -1]]), 'templates.0.steps.0.setup_time_minutes'],
            'fractional setup time' => [$row([[...$step, 'setup_time_minutes' => 1.5]]), 'templates.0.steps.0.setup_time_minutes'],
            'run time not a number' => [$row([[...$step, 'run_time_per_unit_minutes' => 'fast']]), 'templates.0.steps.0.run_time_per_unit_minutes'],
            'requires_confirmation not a boolean' => [$row([[...$step, 'requires_confirmation' => 'maybe']]), 'templates.0.steps.0.requires_confirmation'],
            'unknown mode' => [['mode' => 'overwrite', ...$row([$step])], 'mode'],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function test_malformed_payload_is_rejected_with_422(array $payload, string $errorKey): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);

        $this->import($payload)->assertStatus(422)->assertJsonValidationErrors($errorKey);

        $this->assertSame(0, ProcessTemplate::count());
    }

    public function test_the_same_step_number_in_two_routings_is_not_a_duplicate(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);
        ProductType::factory()->create(['code' => 'TABLE']);

        $this->import(['templates' => [$this->routing('CHAIR'), $this->routing('TABLE')]])
            ->assertOk()
            ->assertJsonPath('data.imported', 2);
    }

    public function test_too_many_rows_is_rejected_with_422(): void
    {
        $rows = array_fill(0, 501, ['product_type_code' => 'CHAIR', 'steps' => [['step_number' => 1, 'name' => 'Cut']]]);

        $this->import(['templates' => $rows])->assertStatus(422)->assertJsonValidationErrors('templates');
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_import_requires_a_key_with_the_master_data_scope(): void
    {
        ProductType::factory()->create(['code' => 'CHAIR']);
        $payload = ['templates' => [$this->routing()]];

        $this->postJson('/api/v1/erp/process-templates/import', $payload)->assertStatus(401);

        $this->import($payload, $this->keyWith([ApiScope::OrdersImport]))->assertStatus(403);

        [$inactive, $plaintext] = ApiKey::issue([
            'name' => 'Revoked key',
            'scopes' => [ApiScope::MasterDataWrite->value],
            'tenant_id' => $this->tenantId,
        ]);
        $inactive->update(['is_active' => false]);
        $this->import($payload, $plaintext)->assertStatus(401);

        $this->assertSame(0, ProcessTemplate::count());
    }

    // ── Tenant isolation ─────────────────────────────────────────────────────

    public function test_import_is_scoped_to_the_keys_tenant(): void
    {
        $tenantB = Tenant::create(['name' => 'Other tenant'])->id;
        app(TenantContext::class)->set($tenantB);
        $productB = ProductType::factory()->create(['code' => 'ONLY-B']);
        $lineB = Line::factory()->create(['code' => 'LB']);
        Workstation::factory()->create(['line_id' => $lineB->id, 'code' => 'WS-B']);
        app(TenantContext::class)->set($this->tenantId);
        ProductType::factory()->create(['code' => 'CHAIR']);

        // Tenant A's key can see neither B's product type nor B's workstation.
        $response = $this->import(['templates' => [
            $this->routing('ONLY-B'),
            $this->routing('CHAIR', ['steps' => [['step_number' => 1, 'name' => 'Cut', 'workstation_code' => 'WS-B']]]),
        ]]);

        $response->assertStatus(207);
        $this->assertSame([1, 2], collect($response->json('data.errors'))->pluck('row')->all());
        $this->assertSame('steps.0.workstation_code', $response->json('data.errors.1.field'));
        $this->assertSame(0, ProcessTemplate::withoutGlobalScopes()->count());

        // Tenant B's key writes B's template, under B's tenant.
        $this->import(
            ['templates' => [$this->routing('ONLY-B', ['steps' => [['step_number' => 1, 'name' => 'Cut', 'workstation_code' => 'WS-B']]])]],
            $this->keyWith([ApiScope::MasterDataWrite], $tenantB),
        )->assertOk();

        $template = ProcessTemplate::withoutGlobalScopes()->sole();
        $this->assertSame($productB->id, $template->product_type_id);
        $this->assertSame($tenantB, $template->tenant_id);
    }

    // ── Products import: create_default_template ─────────────────────────────

    public function test_create_default_template_gives_a_new_product_type_an_orderable_template(): void
    {
        $this->importProducts([
            'create_default_template' => true,
            'products' => [['code' => 'STOOL', 'name' => 'Stool']],
        ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.rows', [['row' => 1, 'code' => 'STOOL', 'template_created' => true]]);

        $template = ProcessTemplate::sole();
        $this->assertSame(1, $template->version);
        $this->assertTrue($template->is_active);
        $this->assertSame('Stool', $template->name);
        $this->assertSame(['Production'], $template->steps()->pluck('name')->all());
        $this->assertSame(1, $template->steps()->value('step_number'));

        $this->importOrder('ERP-S1', 'STOOL')->assertOk()->assertJsonPath('data.imported', 1);
    }

    public function test_default_step_name_follows_the_locale_or_the_envelope(): void
    {
        App::setLocale('pl');

        $this->importProducts(['create_default_template' => true, 'products' => [['code' => 'STOOL']]])->assertOk();
        $this->assertSame('Produkcja', TemplateStep::sole()->name);

        $this->importProducts([
            'create_default_template' => true,
            'default_step_name' => 'Assembly',
            'products' => [['code' => 'BENCH']],
        ])->assertOk();
        $this->assertSame('Assembly', ProductType::where('code', 'BENCH')->sole()->processTemplates()->sole()->steps()->sole()->name);
    }

    public function test_create_default_template_leaves_a_type_that_already_has_a_template(): void
    {
        $withActive = ProductType::factory()->create(['code' => 'CHAIR']);
        ProcessTemplate::factory()->withSteps(3)->create(['product_type_id' => $withActive->id, 'is_active' => true]);
        $withDraft = ProductType::factory()->create(['code' => 'TABLE']);
        ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $withDraft->id, 'is_active' => false]);
        $stepsBefore = TemplateStep::orderBy('id')->get()->toArray();

        $response = $this->importProducts([
            'create_default_template' => true,
            'products' => [['code' => 'CHAIR', 'name' => 'Chair'], ['code' => 'TABLE'], ['code' => 'STOOL']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.rows', [['row' => 3, 'code' => 'STOOL', 'template_created' => true]]);

        $this->assertSame(1, $withActive->processTemplates()->count());
        $this->assertSame(1, $withDraft->processTemplates()->count());
        $this->assertSame($stepsBefore, TemplateStep::whereIn('process_template_id', ProcessTemplate::whereIn('product_type_id', [$withActive->id, $withDraft->id])->pluck('id'))->orderBy('id')->get()->toArray());
    }

    public function test_without_the_flag_products_import_creates_no_template(): void
    {
        $response = $this->importProducts(['products' => [['code' => 'STOOL']]]);
        $response->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertArrayNotHasKey('rows', $response->json('data'));

        $this->importProducts(['create_default_template' => false, 'products' => [['code' => 'BENCH']]])
            ->assertOk();

        $this->assertSame(0, ProcessTemplate::count());
        $this->assertSame(0, AuditLog::where('action', 'process_template.erp_imported')->count());
    }

    public function test_create_default_template_skips_rows_the_import_skipped(): void
    {
        ProductType::factory()->create(['code' => 'STOOL']);

        $this->importProducts([
            'create_default_template' => true,
            'strategy' => 'skip_existing',
            'products' => [['code' => 'STOOL']],
        ])->assertOk()->assertJsonPath('data.skipped', 1);

        $this->assertSame(0, ProcessTemplate::count());
    }

    public function test_create_default_template_must_be_a_boolean(): void
    {
        $this->importProducts(['create_default_template' => 'maybe', 'products' => [['code' => 'STOOL']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('create_default_template');

        $this->importProducts(['create_default_template' => true, 'default_step_name' => str_repeat('x', 256), 'products' => [['code' => 'STOOL']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('default_step_name');

        $this->assertSame(0, ProductType::count());
    }
}
