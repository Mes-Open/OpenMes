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
    public function __construct(protected WorkOrderService $workOrderService) {}

    public static function shouldShowWizard(): bool
    {
        $completed = json_decode(
            DB::table('system_settings')->where('key', 'onboarding_completed')->value('value') ?? 'true',
            true
        );

        return !$completed && Line::count() === 0;
    }

    public function index()
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(false)]
        );

        session()->forget([
            'onboarding_line_id',
            'onboarding_product_type_id',
            'onboarding_template_id',
        ]);

        return redirect()->route('onboarding.step1');
    }

    public function step1()
    {
        return view('onboarding.step1-line', [
            'data' => session('onboarding_line', []),
        ]);
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:lines,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $line = Line::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        session(['onboarding_line_id' => $line->id]);

        return redirect()->route('onboarding.step2');
    }

    public function step2()
    {
        if (!session('onboarding_line_id')) {
            return redirect()->route('onboarding.step1');
        }

        return view('onboarding.step2-product-type', [
            'line' => Line::find(session('onboarding_line_id')),
        ]);
    }

    public function storeStep2(Request $request)
    {
        if (!session('onboarding_line_id')) {
            return redirect()->route('onboarding.step1');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:product_types,code',
            'name' => 'required|string|max:255',
            'unit_of_measure' => 'nullable|string|max:50',
        ]);

        $productType = ProductType::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'unit_of_measure' => $validated['unit_of_measure'] ?: 'pcs',
            'is_active' => true,
        ]);

        $line = Line::find(session('onboarding_line_id'));
        $line->productTypes()->attach($productType->id);

        session(['onboarding_product_type_id' => $productType->id]);

        return redirect()->route('onboarding.step3');
    }

    public function step3()
    {
        if (!session('onboarding_product_type_id')) {
            return redirect()->route('onboarding.step2');
        }

        return view('onboarding.step3-process-template', [
            'productType' => ProductType::find(session('onboarding_product_type_id')),
        ]);
    }

    public function storeStep3(Request $request)
    {
        if (!session('onboarding_product_type_id')) {
            return redirect()->route('onboarding.step2');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);

        $productTypeId = session('onboarding_product_type_id');

        $template = ProcessTemplate::create([
            'product_type_id' => $productTypeId,
            'name' => $validated['name'],
            'version' => 1,
            'is_active' => true,
        ]);

        foreach ($validated['steps'] as $index => $stepData) {
            TemplateStep::create([
                'process_template_id' => $template->id,
                'step_number' => $index + 1,
                'name' => $stepData['name'],
                'estimated_duration_minutes' => $stepData['estimated_duration_minutes'] ?? null,
            ]);
        }

        session(['onboarding_template_id' => $template->id]);

        return redirect()->route('onboarding.step4');
    }

    public function step4()
    {
        if (!session('onboarding_line_id') || !session('onboarding_product_type_id')) {
            return redirect()->route('onboarding.step1');
        }

        return view('onboarding.step4-work-order', [
            'line' => Line::find(session('onboarding_line_id')),
            'productType' => ProductType::find(session('onboarding_product_type_id')),
        ]);
    }

    public function storeStep4(Request $request)
    {
        if (!session('onboarding_line_id') || !session('onboarding_product_type_id')) {
            return redirect()->route('onboarding.step1');
        }

        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no',
            'planned_qty' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:2000',
        ]);

        $this->workOrderService->createWorkOrder([
            'order_no' => $validated['order_no'],
            'line_id' => session('onboarding_line_id'),
            'product_type_id' => session('onboarding_product_type_id'),
            'planned_qty' => $validated['planned_qty'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->markCompleted();

        return redirect()->route('onboarding.complete');
    }

    public function complete()
    {
        $line = session('onboarding_line_id') ? Line::find(session('onboarding_line_id')) : null;
        $productType = session('onboarding_product_type_id') ? ProductType::find(session('onboarding_product_type_id')) : null;

        session()->forget([
            'onboarding_line_id',
            'onboarding_product_type_id',
            'onboarding_template_id',
        ]);

        return view('onboarding.complete', [
            'line' => $line,
            'productType' => $productType,
        ]);
    }

    public function skip()
    {
        $this->markCompleted();

        session()->forget([
            'onboarding_line_id',
            'onboarding_product_type_id',
            'onboarding_template_id',
        ]);

        return redirect()->route('admin.dashboard')
            ->with('info', 'Onboarding wizard skipped. You can re-launch it from Settings.');
    }

    protected function markCompleted(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(true)]
        );
    }
}
