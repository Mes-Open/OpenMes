# Quality Gate Enforcement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A recorded operator output that fails an admin-configured "expected result" (a boolean that should be `true`, a number outside a min/max range, or a select value other than the passing one) automatically raises a blocking `Issue`, which — via OpenMES's *existing* `WorkOrder::isBlocked()` / `BatchStep::canStart()` logic — stops the *next* station from starting. No new blocking mechanism is invented; this plan only adds the missing "was the recorded value actually good?" evaluation and wires it into the blocking system that already exists.

**Architecture:** Three new columns on `template_step_outputs` (`expected_min`, `expected_max`, `expected_value`) hold the optional pass criterion per output. A new `OutputGateEvaluator` service reads a `BatchStepOutputValue` against its parent output's criterion and returns pass/fail. A new Eloquent observer on `BatchStepOutputValue::created` calls the evaluator and, on failure, calls the existing `IssueService::createIssue()` with the seeded `IN_PROCESS_QC_FAIL` issue type — which is already `is_blocking = true` and already causes `IssueService::blockWorkOrder()` to run. Admin authoring (the React "Operator outputs" form) and the store/validation path gain three new optional fields to set the criterion.

**Tech Stack:** Laravel 12 (PHP), Eloquent Observers, PHPUnit 11 (Feature tests), React + Inertia.js (admin authoring UI). Existing packages only — no new dependencies.

**Spec:** This plan is self-contained; there is no separate spec document. The requirement traces directly to the Yotta Energy Tulip Phase 1 proposal's UAT criterion *"system prevents movement to Top Balance unless Voltage Test is passed"* and *"Failed quality checks route unit to defect/hold/rework flow and update unit status"* — verified as currently unmet by live testing on `D:\projects\Zmx-Projects\openmes` (cloned OpenMES v0.21.0, `Mes-Open/OpenMes`, work order `WO-YB1-TEST-FAIL`: every gated station accepted a recorded failure and the batch still reached `DONE` with zero blocking).

## Global Constraints

- PHP/Laravel conventions only — no new Composer or npm packages.
- Every new PHP file needs a one-line class-level docblock explaining *why*, matching the existing style (see `BatchStepEventObserver`, `IssueService`).
- Hooks that react to a model save must never break the underlying write: wrap in `try/catch`, `Log::warning()` on failure (mirrors `BatchStepEventObserver::updated()`).
- Reuse `IssueService::createIssue()` for all Issue creation — do not call `Issue::create()` directly (it skips the blocking side-effect).
- The seeded issue type is `IN_PROCESS_QC_FAIL` (`backend/database/seeders/IssueTypesSeeder.php:79-85`, `is_blocking = true`). Do not create a new issue type for v1.
- v1 supports gates on `boolean`, `number`, and `select` output types only. `text`, `date`, `picture` outputs are left ungated (return "pass" unconditionally) — YAGNI, no proposal station needs a text/date/picture pass criterion.
- v1 supports exactly one passing value for `select` (not a set of passing values). If Yotta later needs multiple passing options, that's a follow-up migration, not part of this plan.
- All new migrations follow the existing naming convention: `YYYY_MM_DD_HHMMSS_snake_case_description.php` (see `backend/database/migrations/2026_08_18_110000_create_template_step_outputs_table.php` for the sibling table this extends).
- Run tests via: `docker compose exec backend php artisan test --filter=<TestClass>` (the stack is already running as `openmes-backend` et al. per `docker-compose.yml`). If working outside the container, `composer test` runs the full suite (`backend/composer.json:70-73`).

---

## File Structure

| File | Responsibility |
|---|---|
| `backend/database/migrations/2026_09_04_120000_add_expected_result_to_template_step_outputs.php` | New (create) — adds `expected_min`, `expected_max`, `expected_value` columns |
| `backend/app/Models/TemplateStepOutput.php` | Modify — fillable/casts for the 3 new columns, `hasExpectedResult()` helper |
| `backend/app/Services/WorkOrder/OutputGateEvaluator.php` | New (create) — pure pass/fail evaluation, one method per gated type |
| `backend/tests/Unit/OutputGateEvaluatorTest.php` | New (create) — unit tests for the evaluator, no DB/HTTP |
| `backend/app/Observers/BatchStepOutputValueObserver.php` | New (create) — reacts to `BatchStepOutputValue::created`, raises the Issue on fail |
| `backend/app/Providers/AppServiceProvider.php` | Modify — register the new observer (`boot()`, next to the existing observer block at line 145) |
| `backend/app/Http/Requests/StoreTemplateStepOutputRequest.php` | Modify — validation rules for the 3 new fields |
| `backend/app/Http/Controllers/Web/Admin/TemplateStepOutputController.php` | Modify — persist the 3 new fields on `store()` |
| `backend/resources/js/Pages/admin/process-templates/Show.jsx` | Modify — authoring form fields for the criterion, list badge showing it's configured |
| `backend/tests/Feature/StepTypedOutputsTest.php` | Modify — add admin-authoring assertions for the new fields (mirrors existing tests in this file) |
| `backend/tests/Feature/QualityGateEnforcementTest.php` | New (create) — the end-to-end regression test: fail → Issue raised → next step blocked → resolve → unblocked |

---

## Task 1: Migration + Model — the expected-result columns

**Files:**
- Create: `backend/database/migrations/2026_09_04_120000_add_expected_result_to_template_step_outputs.php`
- Modify: `backend/app/Models/TemplateStepOutput.php`
- Test: `backend/tests/Unit/TemplateStepOutputExpectedResultTest.php`

**Interfaces:**
- Produces: `TemplateStepOutput::hasExpectedResult(): bool`, and the fillable/cast columns `expected_min` (float|null), `expected_max` (float|null), `expected_value` (string|null) that Task 2's evaluator and Task 4's controller both read/write.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepOutput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateStepOutputExpectedResultTest extends TestCase
{
    use RefreshDatabase;

    private function output(array $attrs = []): TemplateStepOutput
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $step = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1]);

        return TemplateStepOutput::create(array_merge([
            'process_template_id' => $template->id,
            'template_step_id' => $step->id,
            'key' => 'output_test', 'label' => 'Test', 'value_type' => 'number',
        ], $attrs));
    }

    public function test_output_without_a_criterion_has_no_expected_result(): void
    {
        $output = $this->output();

        $this->assertFalse($output->hasExpectedResult());
    }

    public function test_output_with_a_number_range_has_an_expected_result(): void
    {
        $output = $this->output(['expected_min' => 3.20, 'expected_max' => 4.25]);

        $this->assertTrue($output->hasExpectedResult());
        $this->assertEqualsWithDelta(3.20, (float) $output->expected_min, 0.0001);
        $this->assertEqualsWithDelta(4.25, (float) $output->expected_max, 0.0001);
    }

    public function test_output_with_a_boolean_criterion_has_an_expected_result(): void
    {
        $output = $this->output(['value_type' => 'boolean', 'expected_value' => '1']);

        $this->assertTrue($output->hasExpectedResult());
        $this->assertSame('1', $output->expected_value);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=TemplateStepOutputExpectedResultTest`
Expected: FAIL — `expected_min`/`expected_max`/`expected_value` are not fillable / column doesn't exist / `hasExpectedResult` method doesn't exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional pass criterion for a typed operator output (#quality-gate). A number
 * output can set expected_min/expected_max (either or both — one-sided bounds
 * are fine); a boolean or select output sets expected_value (boolean: '1'/'0';
 * select: the single passing option string). Null on all three = no gate,
 * current "must be recorded" behaviour is unchanged. Evaluated by
 * App\Services\WorkOrder\OutputGateEvaluator on every recorded value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_step_outputs', function (Blueprint $table) {
            $table->decimal('expected_min', 12, 4)->nullable()->after('options');
            $table->decimal('expected_max', 12, 4)->nullable()->after('expected_min');
            $table->string('expected_value', 255)->nullable()->after('expected_max');
        });
    }

    public function down(): void
    {
        Schema::table('template_step_outputs', function (Blueprint $table) {
            $table->dropColumn(['expected_min', 'expected_max', 'expected_value']);
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `backend/app/Models/TemplateStepOutput.php`, extend `$fillable` (currently lines 42-52) to add the three columns, extend `casts()` (currently lines 54-61), and add the helper method:

```php
    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'key',
        'label',
        'value_type',
        'unit',
        'options',
        'is_required',
        'sort_order',
        'expected_min',
        'expected_max',
        'expected_value',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'expected_min' => 'decimal:4',
            'expected_max' => 'decimal:4',
        ];
    }
```

Add below the `values()` relation (currently ending at line 76):

```php
    /**
     * True when this output has a configured pass criterion. Callers (the
     * OutputGateEvaluator) treat "no criterion" as "always passes" — the
     * required-field gate (is_required) is unaffected either way.
     */
    public function hasExpectedResult(): bool
    {
        return $this->expected_min !== null
            || $this->expected_max !== null
            || $this->expected_value !== null;
    }
```

- [ ] **Step 5: Run the migration and the test**

Run: `docker compose exec backend php artisan migrate`
Run: `docker compose exec backend php artisan test --filter=TemplateStepOutputExpectedResultTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_09_04_120000_add_expected_result_to_template_step_outputs.php backend/app/Models/TemplateStepOutput.php backend/tests/Unit/TemplateStepOutputExpectedResultTest.php
git commit -m "feat: add optional pass criterion columns to template step outputs"
```

---

## Task 2: OutputGateEvaluator service

**Files:**
- Create: `backend/app/Services/WorkOrder/OutputGateEvaluator.php`
- Test: `backend/tests/Unit/OutputGateEvaluatorTest.php`

**Interfaces:**
- Consumes: `TemplateStepOutput::hasExpectedResult()`, `expected_min`/`expected_max`/`expected_value` (Task 1); `BatchStepOutputValue::output()` relation, `value_boolean`/`value_number`/`value_text` (existing, `backend/app/Models/BatchStepOutputValue.php:55-57`).
- Produces: `OutputGateEvaluator::passes(BatchStepOutputValue $value): bool` — consumed by Task 3's observer.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\BatchStepOutputValue;
use App\Models\TemplateStepOutput;
use App\Services\WorkOrder\OutputGateEvaluator;
use Tests\TestCase;

class OutputGateEvaluatorTest extends TestCase
{
    private function evaluator(): OutputGateEvaluator
    {
        return new OutputGateEvaluator();
    }

    private function valueFor(TemplateStepOutput $output, array $attrs): BatchStepOutputValue
    {
        $value = new BatchStepOutputValue($attrs);
        $value->setRelation('output', $output);

        return $value;
    }

    public function test_a_value_with_no_configured_criterion_always_passes(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'boolean']);
        $value = $this->valueFor($output, ['value_boolean' => false]);

        $this->assertTrue($this->evaluator()->passes($value));
    }

    public function test_boolean_true_passes_when_true_is_expected(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'boolean', 'expected_value' => '1']);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_boolean' => true])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_boolean' => false])));
    }

    public function test_number_inside_the_range_passes(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'number', 'expected_min' => 3.20, 'expected_max' => 4.25]);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_number' => 3.87])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => 5.50])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => 3.10])));
    }

    public function test_number_range_can_be_one_sided(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'number', 'expected_min' => 0.0]);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_number' => 100.0])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => -0.5])));
    }

    public function test_select_matches_the_single_passing_option(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'select', 'expected_value' => 'Release']);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Release'])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Reject'])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Hold'])));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=OutputGateEvaluatorTest`
Expected: FAIL — class `App\Services\WorkOrder\OutputGateEvaluator` not found.

- [ ] **Step 3: Write the evaluator**

```php
<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStepOutputValue;
use App\Models\TemplateStepOutput;

/**
 * Answers "did this recorded value meet its station's pass criterion?" — the
 * piece that was missing before #quality-gate: a required output only ever
 * checked "was something recorded", never "was the recorded thing good". No
 * criterion configured on the output = always passes (today's behaviour,
 * unchanged). text/date/picture outputs have no gate support in v1.
 */
class OutputGateEvaluator
{
    public function passes(BatchStepOutputValue $value): bool
    {
        $output = $value->output;

        if (! $output || ! $output->hasExpectedResult()) {
            return true;
        }

        return match ($output->value_type) {
            TemplateStepOutput::TYPE_BOOLEAN => $this->passesBoolean($output, $value),
            TemplateStepOutput::TYPE_NUMBER => $this->passesNumber($output, $value),
            TemplateStepOutput::TYPE_SELECT => $this->passesSelect($output, $value),
            default => true,
        };
    }

    private function passesBoolean(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        $expected = filter_var($output->expected_value, FILTER_VALIDATE_BOOLEAN);

        return $value->value_boolean === $expected;
    }

    private function passesNumber(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        if ($value->value_number === null) {
            return true;
        }

        $recorded = (float) $value->value_number;

        if ($output->expected_min !== null && $recorded < (float) $output->expected_min) {
            return false;
        }

        if ($output->expected_max !== null && $recorded > (float) $output->expected_max) {
            return false;
        }

        return true;
    }

    private function passesSelect(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        return $value->value_text === $output->expected_value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=OutputGateEvaluatorTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/WorkOrder/OutputGateEvaluator.php backend/tests/Unit/OutputGateEvaluatorTest.php
git commit -m "feat: add OutputGateEvaluator to check recorded values against a pass criterion"
```

---

## Task 3: Observer — auto-raise a blocking Issue on failure

**Files:**
- Create: `backend/app/Observers/BatchStepOutputValueObserver.php`
- Modify: `backend/app/Providers/AppServiceProvider.php:145` (register the observer, right after the `WorkOrderPlacement::observe(...)` line)
- Test: `backend/tests/Feature/QualityGateEnforcementTest.php`

**Interfaces:**
- Consumes: `OutputGateEvaluator::passes()` (Task 2); `IssueService::createIssue(array $data): Issue` (existing, `backend/app/Services/IssueService.php:15-41`); `Issue::SOURCE_IN_PROCESS` (existing, `backend/app/Models/Issue.php:28`); `IssueType` model, seeded row `code = 'IN_PROCESS_QC_FAIL'`.
- Produces: the observed side-effect later tasks and the end-to-end test rely on — recording a failing value creates an `Issue` row and flips `WorkOrder::status` to `WorkOrder::STATUS_BLOCKED`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepOutputValue;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepOutput;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end regression for #quality-gate: a recorded value that fails its
 * output's expected result must raise a blocking Issue and stop the *next*
 * BatchStep from starting — reusing WorkOrder::isBlocked()/BatchStep::canStart(),
 * not a new mechanism. Mirrors the manual browser test run against
 * WO-YB1-TEST-FAIL that discovered the gap this plan closes.
 */
class QualityGateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Batch $batch;

    private BatchStep $step1;

    private BatchStep $step2;

    private TemplateStepOutput $gatedOutput;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'web']);
        }
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        IssueType::create([
            'code' => 'IN_PROCESS_QC_FAIL', 'name' => 'In-process Quality — Control Failed',
            'severity' => 'HIGH', 'is_blocking' => true, 'is_active' => true,
        ]);

        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $step1Def = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Voltage Test']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Top Balance']);

        $this->gatedOutput = TemplateStepOutput::create([
            'process_template_id' => $template->id, 'template_step_id' => $step1Def->id,
            'key' => 'pack_voltage', 'label' => 'Pack Voltage (V)', 'value_type' => 'number',
            'is_required' => true, 'expected_min' => 3.20, 'expected_max' => 4.25,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id, 'product_type_id' => $productType->id,
            'process_snapshot' => ['template_id' => $template->id],
        ]);
        $this->batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);
        $this->step1 = BatchStep::factory()->create([
            'batch_id' => $this->batch->id, 'step_number' => 1, 'name' => 'Voltage Test',
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
        $this->step2 = BatchStep::factory()->create([
            'batch_id' => $this->batch->id, 'step_number' => 2, 'name' => 'Top Balance',
            'status' => BatchStep::STATUS_PENDING,
        ]);
    }

    public function test_an_out_of_range_value_raises_a_blocking_issue_and_stops_the_next_step(): void
    {
        $this->assertTrue($this->step2->fresh()->canStart(), 'sanity: next step is startable before any value is recorded');

        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 5.50, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $issue = Issue::where('batch_step_id', $this->step1->id)->first();
        $this->assertNotNull($issue, 'expected an Issue to be auto-raised');
        $this->assertSame('IN_PROCESS_QC_FAIL', $issue->issueType->code);
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertSame(Issue::SOURCE_IN_PROCESS, $issue->source);

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->batch->fresh()->workOrder->status);
        $this->assertFalse($this->step2->fresh()->canStart(), 'the NEXT step must now be blocked');
    }

    public function test_an_in_range_value_raises_no_issue(): void
    {
        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 3.87, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $this->assertSame(0, Issue::where('batch_step_id', $this->step1->id)->count());
        $this->assertTrue($this->step2->fresh()->canStart());
    }

    public function test_resolving_the_issue_unblocks_the_next_step(): void
    {
        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 5.50, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);
        $this->assertFalse($this->step2->fresh()->canStart());

        $issue = Issue::where('batch_step_id', $this->step1->id)->firstOrFail();
        app(IssueService::class)->resolveIssue($issue, 'Retested, voltage now in spec.');

        $this->assertTrue($this->step2->fresh()->canStart(), 'resolving the blocking issue must unblock the next step');
    }

    public function test_an_output_with_no_configured_criterion_behaves_exactly_as_before(): void
    {
        $ungated = TemplateStepOutput::create([
            'process_template_id' => $this->gatedOutput->process_template_id,
            'template_step_id' => $this->gatedOutput->template_step_id,
            'key' => 'note', 'label' => 'Note', 'value_type' => 'text',
        ]);

        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $ungated->id,
            'value_text' => 'anything at all', 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $this->assertSame(0, Issue::count());
        $this->assertTrue($this->step2->fresh()->canStart());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=QualityGateEnforcementTest`
Expected: FAIL on the first two tests — no `Issue` row is created, `canStart()` stays `true` after the out-of-range value (the observer doesn't exist yet). The third and fourth tests may pass vacuously (nothing to fail on) — that's fine, they'll stay green through the rest of this task.

- [ ] **Step 3: Write the observer**

```php
<?php

namespace App\Observers;

use App\Models\BatchStepOutputValue;
use App\Models\Issue;
use App\Models\IssueType;
use App\Services\IssueService;
use App\Services\WorkOrder\OutputGateEvaluator;
use Illuminate\Support\Facades\Log;

/**
 * A recorded output that fails its configured expected result
 * (TemplateStepOutput expected_min/expected_max/expected_value) auto-raises a
 * blocking Issue on the work order — reusing WorkOrder::isBlocked() /
 * BatchStep::canStart() (BatchStep.php:309-320) rather than inventing new
 * blocking logic. An open blocking Issue on a work order already stops every
 * step's canStart() on that order, so this is the whole enforcement mechanism.
 * Best-effort like the sibling BatchStepEventObserver: a throwing evaluator or
 * issue-service call must never break the operator's save.
 */
class BatchStepOutputValueObserver
{
    public function __construct(
        private readonly OutputGateEvaluator $evaluator,
        private readonly IssueService $issues,
    ) {
    }

    public function created(BatchStepOutputValue $value): void
    {
        try {
            $value->loadMissing(['output', 'batchStep.batch']);

            if ($this->evaluator->passes($value)) {
                return;
            }

            $issueType = IssueType::where('code', 'IN_PROCESS_QC_FAIL')->first();

            if (! $issueType) {
                Log::warning('Quality gate failed but IN_PROCESS_QC_FAIL issue type is missing — no issue raised', [
                    'batch_step_output_value_id' => $value->id,
                ]);

                return;
            }

            $this->issues->createIssue([
                'work_order_id' => $value->batchStep->batch->work_order_id,
                'batch_step_id' => $value->batch_step_id,
                'issue_type_id' => $issueType->id,
                'source' => Issue::SOURCE_IN_PROCESS,
                'title' => __('Quality gate failed: :label', ['label' => $value->output->label]),
                'description' => __('Recorded value did not meet the expected result configured for this step.'),
                'reported_by_id' => $value->recorded_by_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BatchStepOutputValue quality-gate hook failed', ['error' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 4: Register the observer**

In `backend/app/Providers/AppServiceProvider.php`, immediately after line 145 (`\App\Models\WorkOrderPlacement::observe(\App\Observers\WorkOrderPlacementEventObserver::class);`), add:

```php

        // Quality gates (#quality-gate): a recorded output that fails its
        // configured expected result auto-raises a blocking Issue, which the
        // existing WorkOrder::isBlocked()/BatchStep::canStart() checks already
        // use to stop the next station — see BatchStepOutputValueObserver.
        \App\Models\BatchStepOutputValue::observe(\App\Observers\BatchStepOutputValueObserver::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=QualityGateEnforcementTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Observers/BatchStepOutputValueObserver.php backend/app/Providers/AppServiceProvider.php backend/tests/Feature/QualityGateEnforcementTest.php
git commit -m "feat: auto-raise a blocking issue when a recorded output fails its quality gate"
```

---

## Task 4: Admin authoring — validation + persistence

**Files:**
- Modify: `backend/app/Http/Requests/StoreTemplateStepOutputRequest.php`
- Modify: `backend/app/Http/Controllers/Web/Admin/TemplateStepOutputController.php`
- Modify: `backend/tests/Feature/StepTypedOutputsTest.php` (add new test methods; existing tests in this file must keep passing unmodified)

**Interfaces:**
- Consumes: Task 1's `expected_min`/`expected_max`/`expected_value` columns.
- Produces: the POST `/admin/product-types/{productType}/process-templates/{processTemplate}/outputs` endpoint now accepts and persists the three new optional fields — consumed by Task 5's React form.

- [ ] **Step 1: Write the failing test**

Add to `backend/tests/Feature/StepTypedOutputsTest.php` (inside the existing `StepTypedOutputsTest` class, in the "Admin authoring" section after `test_select_output_requires_options`):

```php
    public function test_admin_sets_a_number_range_criterion(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->admin)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id,
            'key' => 'pack_voltage', 'label' => 'Pack Voltage', 'value_type' => 'number',
            'expected_min' => '3.20', 'expected_max' => '4.25',
        ])->assertRedirect();

        $this->assertDatabaseHas('template_step_outputs', [
            'key' => 'pack_voltage', 'expected_min' => 3.2, 'expected_max' => 4.25,
        ]);
    }

    public function test_admin_sets_a_boolean_pass_criterion(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->admin)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id,
            'key' => 'passed', 'label' => 'Passed?', 'value_type' => 'boolean', 'expected_value' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('template_step_outputs', ['key' => 'passed', 'expected_value' => '1']);
    }

    public function test_expected_max_below_expected_min_is_rejected(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->admin)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id,
            'key' => 'x', 'label' => 'X', 'value_type' => 'number',
            'expected_min' => '10', 'expected_max' => '5',
        ])->assertSessionHasErrors('expected_max');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=StepTypedOutputsTest`
Expected: the 3 new tests FAIL (columns aren't validated/persisted yet); all pre-existing tests in this file still PASS.

- [ ] **Step 3: Update the form request**

In `backend/app/Http/Requests/StoreTemplateStepOutputRequest.php`, add to the `rules()` array (currently lines 21-30), after `'is_required' => ['sometimes', 'boolean'],`:

```php
            'expected_min' => ['nullable', 'numeric'],
            'expected_max' => ['nullable', 'numeric', 'gte:expected_min'],
            'expected_value' => ['nullable', 'string', 'max:255'],
```

- [ ] **Step 4: Update the controller**

In `backend/app/Http/Controllers/Web/Admin/TemplateStepOutputController.php`, add to the `$processTemplate->outputs()->create([...])` array (currently lines 28-37), after `'is_required' => $request->boolean('is_required'),`:

```php
            'expected_min' => $request->validated('expected_min'),
            'expected_max' => $request->validated('expected_max'),
            'expected_value' => $request->validated('expected_value'),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=StepTypedOutputsTest`
Expected: PASS (all tests in the file, old and new).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Requests/StoreTemplateStepOutputRequest.php backend/app/Http/Controllers/Web/Admin/TemplateStepOutputController.php backend/tests/Feature/StepTypedOutputsTest.php
git commit -m "feat: accept and persist an optional pass criterion when authoring an output"
```

---

## Task 5: Admin UI — author the criterion in the process-template editor

**Files:**
- Modify: `backend/resources/js/Pages/admin/process-templates/Show.jsx:583-739`

**Interfaces:**
- Consumes: the `/outputs` POST endpoint now accepting `expected_min`, `expected_max`, `expected_value` (Task 4).
- Produces: nothing new for later tasks — this is the UI leaf. Manually verified (no PHPUnit coverage for a `.jsx` file); this codebase has no JS test runner configured, so verification is via the browser, matching how every other admin-authoring form in this file was hand-verified.

- [ ] **Step 1: Extend the form state**

At line 583, change:

```jsx
    const outputForm = useForm({ key: '', label: '', value_type: 'text', unit: '', options: '', is_required: false, template_step_id: step.id });
```

to:

```jsx
    const outputForm = useForm({
        key: '', label: '', value_type: 'text', unit: '', options: '', is_required: false,
        expected_min: '', expected_max: '', expected_value: '', template_step_id: step.id,
    });
```

- [ ] **Step 2: Reset the new fields on success**

At line 596, change:

```jsx
            onSuccess: () => outputForm.reset('key', 'label', 'unit', 'options', 'is_required'),
```

to:

```jsx
            onSuccess: () => outputForm.reset('key', 'label', 'unit', 'options', 'is_required', 'expected_min', 'expected_max', 'expected_value'),
```

- [ ] **Step 3: Add the conditional criterion fields to the form**

After the existing select-options block (currently lines 728-730, the `{outputForm.data.value_type === 'select' && (...)}` block) and before the `Required` checkbox label (line 731), insert:

```jsx
                    {outputForm.data.value_type === 'number' && (
                        <span className="flex items-center gap-1">
                            <input
                                type="number" step="any" value={outputForm.data.expected_min}
                                onChange={(e) => outputForm.setData('expected_min', e.target.value)}
                                placeholder={__('pass min')} className="form-input text-sm py-1 w-[80px]"
                            />
                            <span className="text-xs text-om-faint">–</span>
                            <input
                                type="number" step="any" value={outputForm.data.expected_max}
                                onChange={(e) => outputForm.setData('expected_max', e.target.value)}
                                placeholder={__('pass max')} className="form-input text-sm py-1 w-[80px]"
                            />
                        </span>
                    )}
                    {outputForm.data.value_type === 'boolean' && (
                        <label className="flex items-center gap-1.5 text-xs text-om-muted">
                            <input
                                type="checkbox" checked={outputForm.data.expected_value === '1'}
                                onChange={(e) => outputForm.setData('expected_value', e.target.checked ? '1' : '')}
                            />
                            {__('Must be Yes to pass')}
                        </label>
                    )}
                    {outputForm.data.value_type === 'select' && outputForm.data.options.trim() !== '' && (
                        <select
                            value={outputForm.data.expected_value}
                            onChange={(e) => outputForm.setData('expected_value', e.target.value)}
                            className="form-select text-sm py-1"
                        >
                            <option value="">{__('No pass criterion')}</option>
                            {outputForm.data.options.split(',').map((s) => s.trim()).filter(Boolean).map((opt) => (
                                <option key={opt} value={opt}>{__('Pass: :option', { option: opt })}</option>
                            ))}
                        </select>
                    )}
```

- [ ] **Step 4: Show the configured criterion in the outputs list**

In the list item (currently lines 704-711), after the `is_required` badge, insert:

```jsx
                                {(o.expected_min !== null || o.expected_max !== null) && (
                                    <span className="text-[10px] uppercase px-1.5 py-0.5 rounded bg-om-chip text-om-muted">
                                        {__('pass')}: {o.expected_min ?? '–'}–{o.expected_max ?? '–'}
                                    </span>
                                )}
                                {o.expected_value && (
                                    <span className="text-[10px] uppercase px-1.5 py-0.5 rounded bg-om-chip text-om-muted">
                                        {__('pass')}: {o.expected_value}
                                    </span>
                                )}
```

- [ ] **Step 5: Build and manually verify**

Run: `docker compose exec backend npm run build` (or, for live-edit during verification, `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d` per the project's dev-overlay workflow documented in `README.md`).

In the browser: open a process template step, add a `number` output with a pass min/max — confirm the two inputs appear only for `number`, submit, and confirm the "pass: X–Y" badge shows in the list. Repeat for `boolean` (checkbox appears, badge shows `pass: 1` after submit) and `select` (dropdown populates from the typed options, badge shows the chosen passing value).

- [ ] **Step 6: Commit**

```bash
git add backend/resources/js/Pages/admin/process-templates/Show.jsx
git commit -m "feat: author a pass criterion for number/boolean/select operator outputs"
```

---

## Self-Review

**1. Spec coverage** — every requirement traced back to the live-tested gap is covered:
- *"system prevents movement... unless passed"* → Task 3 (observer blocks `canStart()` on the next step via the existing `WorkOrder::isBlocked()` path).
- *"Failed quality checks route unit to defect/hold/rework flow"* → Task 3 (auto-raises an `Issue` using the exact same `IN_PROCESS_QC_FAIL` type and `IssueService` the manual defect/hold/rework flow already uses — an operator or supervisor resolves it through the existing Issue UI, no new UI needed there).
- Admin needs a way to *configure* what "passed" means per station → Tasks 1, 4, 5.
- Must not regress the current "must be recorded" behavior for ungated outputs → Task 3's fourth test (`test_an_output_with_no_configured_criterion_behaves_exactly_as_before`).
- Must be reversible/inspectable by a human (not a silent auto-fail) → the created `Issue` is visible in the existing Issues UI and dashboard; resolving it unblocks production (Task 3's third test) — this mirrors the proposal's "or dispositioned" UAT language (a supervisor can review and clear a failed gate, not just be stuck).

**2. Placeholder scan** — no TBD/TODO, no "add appropriate error handling," no "similar to Task N" without repeated code. Every step has real, complete code.

**3. Type consistency** — `OutputGateEvaluator::passes(BatchStepOutputValue $value): bool` (Task 2) is the exact signature the observer calls in Task 3. `TemplateStepOutput::hasExpectedResult(): bool` (Task 1) is the exact method the evaluator calls. Column names (`expected_min`, `expected_max`, `expected_value`) are identical across the migration, model casts/fillable, form request, controller, React form state, and every test — checked by re-reading each task's file list against the others.

---

## What this plan deliberately does *not* build (out of scope, noted for later)

- **Blocking the *current* step's own completion** (vs. only the next step's start) — not built. The proposal's language ("prevents movement to Top Balance") is about the *next* station, which this plan covers exactly; blocking the current step too would need `BatchStep::completeStep()` to check the evaluator directly (a different, larger change touching the completion path itself, not just observation).
- **Multiple passing values for a `select` output** — v1 is single-value only (see Global Constraints).
- **Per-output override of which `IssueType` gets raised** — hardcoded to `IN_PROCESS_QC_FAIL` for v1; a `fail_issue_type_id` column would be the natural extension.
- **The spec-range `InspectionPlan`/`InspectionResult` subsystem** (material-scoped, separately identified as a gap in the original mapping doc) — untouched; this plan is scoped entirely to `TemplateStepOutput`/`BatchStepOutputValue`, the process-step side, not incoming material inspection.
