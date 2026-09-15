<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Machine\MachineCounterService;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMachineCounterDemo extends Command
{
    protected $signature = 'machine-counters:demo {user : Admin or supervisor ID} {--force : Allow creation outside local/testing}';

    protected $description = 'Create an isolated demo order, two batches and a simulated counter (never resets existing data)';

    public function handle(MachineCounterService $service, TenantContext $tenant): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Demo data is disabled outside local/testing. Use --force only on an isolated development installation.');

            return self::FAILURE;
        }
        $user = User::findOrFail($this->argument('user'));
        if (! $user->hasAnyRole(['Admin', 'Supervisor'])) {
            $this->error('Select an admin or supervisor.');

            return self::FAILURE;
        }
        $tenant->set($user->tenant_id);
        try {
            $counter = DB::transaction(function () use ($service, $user) {
                $code = 'DEMO-COUNT-'.strtoupper(Str::random(6));
                $line = Line::create(['code' => $code, 'name' => $code, 'is_active' => true]);
                $ws = Workstation::create(['line_id' => $line->id, 'code' => $code, 'name' => 'Demo counting station', 'is_active' => true]);
                $product = ProductType::create(['code' => $code, 'name' => 'Demo counter product', 'unit_of_measure' => 'pcs', 'is_active' => true]);
                $order = WorkOrder::create(['order_no' => $code, 'line_id' => $line->id, 'product_type_id' => $product->id,
                    'planned_qty' => 20, 'produced_qty' => 0, 'counting_source' => 'machine', 'status' => WorkOrder::STATUS_IN_PROGRESS,
                    'description' => 'Isolated machine counter demonstration. Do not schedule real production.']);
                $first = null;
                foreach ([1, 2] as $number) {
                    $batch = Batch::create(['work_order_id' => $order->id, 'batch_number' => $number, 'target_qty' => 10, 'produced_qty' => 0, 'status' => Batch::STATUS_IN_PROGRESS, 'started_at' => now()]);
                    $step = BatchStep::create(['batch_id' => $batch->id, 'step_number' => 1, 'name' => 'Demo batch '.$number,
                        'workstation_id' => $ws->id, 'status' => BatchStep::STATUS_IN_PROGRESS, 'started_at' => now(), 'passed_qty' => 0, 'scrap_qty' => 0]);
                    $first ??= $step;
                }
                $connection = MachineConnection::create(['name' => $code, 'line_id' => $line->id, 'protocol' => 'rest', 'is_active' => true]);
                $tag = MachineTag::create(['machine_connection_id' => $connection->id, 'workstation_id' => $ws->id, 'name' => $code, 'address' => 'demo', 'signal_type' => 'good_count', 'is_active' => true]);
                $counter = $service->forSource($tag);
                $counter->update(['is_simulated' => true]);
                $service->configure($counter, ['workstation_id' => $ws->id, 'batch_step_id' => $first->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Isolated demo setup'], $user->id);

                return $counter;
            });
            $this->info(url('/admin/connectivity/counters?counter='.$counter->id));
        } finally {
            $tenant->clear();
        }

        return self::SUCCESS;
    }
}
