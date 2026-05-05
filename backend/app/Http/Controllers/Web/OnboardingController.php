<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function index()
    {
        if ($this->isCompleted()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('onboarding.step1');
    }

    public function step1()
    {
        return view('onboarding.step1-line');
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

        return view('onboarding.step2-product-type');
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

        return view('onboarding.step3-process-template');
    }

    public function storeStep3(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.estimated_duration_minutes' => 'nullable|integer|min:0',
        ]);

        $productTypeId = $request->session()->get('onboarding.product_type_id');

        $template = ProcessTemplate::create([
            'product_type_id' => $productTypeId,
            'name' => $validated['name'],
            'version' => 1,
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

        $request->session()->put('onboarding.template_id', $template->id);

        return redirect()->route('onboarding.step4');
    }

    public function step4(Request $request)
    {
        if (! $request->session()->has('onboarding.template_id')) {
            return redirect()->route('onboarding.step1');
        }

        return view('onboarding.step4-work-order');
    }

    public function storeStep4(Request $request, WorkOrderService $workOrderService)
    {
        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no',
            'planned_qty' => 'required|numeric|min:0.01',
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

        return view('onboarding.complete');
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
