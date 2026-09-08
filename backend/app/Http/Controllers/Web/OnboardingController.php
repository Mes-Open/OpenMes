<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ModuleRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function index()
    {
        if ($this->isCompleted()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('onboarding.modules');
    }

    /**
     * Step 1 — choose which optional feature modules the MES exposes. Reuses the
     * existing ModuleRegistry / enabled_modules mechanism (changeable later in
     * Settings → System → Modules).
     */
    public function modules()
    {
        return Inertia::render('onboarding/Modules', [
            // step 0 = the preset screen precedes the wizard (no stepper); the
            // wizard's own steps start at 1 (Line) after a preset is chosen.
            'step' => 0,
            'modules' => ModuleRegistry::forForm(),
            'presets' => ModuleRegistry::PRESETS,
        ]);
    }

    public function storeModules(Request $request)
    {
        $validated = $request->validate([
            'preset' => ['required', Rule::in(['light', 'advanced', 'custom'])],
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', Rule::in(ModuleRegistry::optionalKeys())],
        ]);

        ModuleRegistry::save(ModuleRegistry::modulesForPreset(
            $validated['preset'],
            $validated['enabled_modules'] ?? [],
        ));

        return redirect()->route('onboarding.step1');
    }

    public function step1()
    {
        return Inertia::render('onboarding/Step1', ['step' => 1]);
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:lines,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $line = Line::create([...$validated, 'is_active' => true]);
        $line->users()->attach(auth()->id());

        $request->session()->put('onboarding.line_id', $line->id);

        return redirect()->route('onboarding.step2');
    }

    public function step2(Request $request)
    {
        if (! $request->session()->has('onboarding.line_id')) {
            return redirect()->route('onboarding.step1');
        }

        return Inertia::render('onboarding/Step2', ['step' => 2]);
    }

    public function storeStep2(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:product_types,code',
            'name' => 'required|string|max:255',
            'unit_of_measure' => 'nullable|string|max:20',
        ]);

        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        $validated['is_active'] = true;

        $productType = ProductType::create($validated);

        $lineId = $request->session()->get('onboarding.line_id');
        if ($lineId) {
            Line::find($lineId)?->productTypes()->attach($productType->id);
        }

        $request->session()->put('onboarding.product_type_id', $productType->id);

        return redirect()->route('onboarding.step3');
    }

    public function step3(Request $request)
    {
        if (! $request->session()->has('onboarding.product_type_id')) {
            return redirect()->route('onboarding.step1');
        }

        return Inertia::render('onboarding/Step3', ['step' => 3]);
    }

    public function storeStep3(Request $request)
    {
        // The step is not repeatable: a resubmit (double click, browser back,
        // Inertia retry) would otherwise insert a second template and violate
        // the (product_type_id, version) unique index.
        if ($request->session()->has('onboarding.template_id')) {
            return redirect()->route('onboarding.step4');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.estimated_duration_minutes' => 'nullable|integer|min:0',
        ]);

        $productTypeId = $request->session()->get('onboarding.product_type_id');

        try {
            $template = DB::transaction(function () use ($validated, $productTypeId) {
                // Same versioning rule as the admin UI and the API: never assume v1.
                $nextVersion = ProcessTemplate::where('product_type_id', $productTypeId)->max('version') + 1;

                $template = ProcessTemplate::create([
                    'product_type_id' => $productTypeId,
                    'name' => $validated['name'],
                    'version' => $nextVersion,
                    'is_active' => true,
                ]);

                foreach ($validated['steps'] as $i => $stepData) {
                    TemplateStep::create([
                        'process_template_id' => $template->id,
                        'step_number' => $i + 1,
                        'name' => $stepData['name'],
                        'estimated_duration_minutes' => $stepData['estimated_duration_minutes'] ?? null,
                    ]);
                }

                return $template;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The session guard above stops sequential replays, but two truly
            // concurrent same-session POSTs (rapid double click) can both read an
            // empty table and pick the same (product_type_id, version) — a
            // FOR UPDATE lock can't serialise the first insert because there are
            // no rows to lock yet. Rather than a cross-writer lock, treat the loser
            // idempotently: a template now exists, so adopt the latest one and
            // continue instead of surfacing a 500.
            $template = ProcessTemplate::where('product_type_id', $productTypeId)
                ->orderByDesc('version')
                ->firstOrFail();
        }

        $request->session()->put('onboarding.template_id', $template->id);

        return redirect()->route('onboarding.step4');
    }

    public function step4(Request $request)
    {
        if (! $request->session()->has('onboarding.template_id')) {
            return redirect()->route('onboarding.step1');
        }

        return Inertia::render('onboarding/Step4', ['step' => 4]);
    }

    public function storeStep4(Request $request, WorkOrderService $workOrderService)
    {
        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no',
            'planned_qty' => 'required|numeric|min:0.01|max:99999999',
            'description' => 'nullable|string',
        ]);

        $workOrderService->createWorkOrder([
            'order_no' => $validated['order_no'],
            'line_id' => $request->session()->get('onboarding.line_id'),
            'product_type_id' => $request->session()->get('onboarding.product_type_id'),
            'planned_qty' => $validated['planned_qty'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('onboarding.complete');
    }

    public function complete(Request $request)
    {
        $this->markCompleted();
        $request->session()->forget('onboarding');

        return Inertia::render('onboarding/Complete', ['step' => 5]);
    }

    public function skip(Request $request)
    {
        $this->markCompleted();
        $request->session()->forget('onboarding');

        return redirect()->route('admin.dashboard')->with('success', 'Onboarding skipped. You can re-launch it from Settings.');
    }

    public static function shouldShowWizard(): bool
    {
        $completed = json_decode(
            DB::table('system_settings')->where('key', 'onboarding_completed')->value('value') ?? 'true',
            true
        );

        return ! $completed && Line::count() === 0;
    }

    private function isCompleted(): bool
    {
        return ! self::shouldShowWizard();
    }

    private function markCompleted(): void
    {
        DB::table('system_settings')
            ->where('key', 'onboarding_completed')
            ->update(['value' => json_encode(true)]);
    }
}
