<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoadSampleDataRequest;
use App\Models\Line;
use App\Support\DemoDatasetRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * What an admin sees the first time they sign in to an empty system.
 *
 * This used to be a five-screen wizard: pick your feature modules, then build a
 * line, a product, a routing and a work order by hand. It asked which parts of
 * the product you wanted before you had seen any of them, and the four build
 * steps produced one of each — not enough to show anything, and thrown away as
 * soon as real data arrived.
 *
 * It is now one screen with one decision: install an example company, or start
 * empty. Both options are on screen together, because a first-run screen that
 * only offers "yes" is a wall rather than a choice. Modules are all enabled on
 * install and can be turned off later in Settings → System → Modules, once the
 * shop knows what it uses.
 */
class OnboardingController extends Controller
{
    public function index()
    {
        if ($this->isCompleted()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('onboarding/Welcome', [
            'datasets' => DemoDatasetRegistry::forDisplay(),
        ]);
    }

    /**
     * Install the chosen example company and go straight to the dashboard.
     *
     * Shares LoadSampleDataRequest with Settings → Data, so the dataset key is
     * validated against the registry in both places and a hand-crafted post
     * cannot name an arbitrary seeder class.
     */
    public function store(LoadSampleDataRequest $request)
    {
        $dataset = $request->validated()['dataset'];

        try {
            foreach (DemoDatasetRegistry::seedersFor($dataset) as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Could not load sample data: :msg', ['msg' => $e->getMessage()]));
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'sample_data_loaded'],
            ['value' => json_encode($dataset), 'updated_at' => now()],
        );

        $this->markCompleted();

        return redirect()->route('admin.dashboard')->with('success', __(
            'Sample data loaded successfully: :company. Lines, work orders, operators and product types have been created.',
            ['company' => DemoDatasetRegistry::labelFor($dataset)],
        ));
    }

    /**
     * Start with an empty system. The example companies stay available from
     * Settings → Data, so this is a "not now" rather than a "never".
     */
    public function skip(Request $request)
    {
        $this->markCompleted();
        $request->session()->forget('onboarding');

        return redirect()->route('admin.dashboard')
            ->with('success', __('Starting with an empty system. You can load an example company later from Settings → Data.'));
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
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(true), 'updated_at' => now()],
        );
    }
}
