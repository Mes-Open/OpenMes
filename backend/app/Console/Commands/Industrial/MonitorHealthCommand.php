<?php

namespace App\Console\Commands\Industrial;

use Illuminate\Console\Command;
use App\Models\Workstation;
use App\Models\Tool;
use App\Services\Industrial\PredictiveMaintenanceService;

class MonitorHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'industrial:monitor-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze health of workstations and tools to predict failures';

    /**
     * Execute the console command.
     */
    public function handle(PredictiveMaintenanceService $service)
    {
        $this->info('Starting health analysis...');

        $workstations = Workstation::where('is_active', true)->get();
        foreach ($workstations as $ws) {
            $service->analyzeWorkstationHealth($ws);
            $this->line("Analyzed workstation: {$ws->name} (Prob: " . number_format($ws->failure_probability, 1) . "%)");
        }

        $tools = Tool::where('status', '!=', 'retired')->get();
        foreach ($tools as $tool) {
            $service->analyzeToolHealth($tool);
            $this->line("Analyzed tool: {$tool->name} (Prob: " . number_format($tool->failure_probability, 1) . "%)");
        }

        $this->info('Health analysis complete.');
    }
}
