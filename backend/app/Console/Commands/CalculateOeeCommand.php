<?php

namespace App\Console\Commands;

use App\Services\Production\OeeCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateOeeCommand extends Command
{
    protected $signature = 'oee:calculate
                            {--date= : Date to calculate (YYYY-MM-DD, default: yesterday)}
                            {--line= : Specific line ID (default: all active lines)}';

    protected $description = 'Calculate OEE records for production lines';

    public function handle(OeeCalculationService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Calculating OEE for {$date->toDateString()}...");

        if ($lineId = $this->option('line')) {
            $line = \App\Models\Line::find($lineId);
            if (! $line) {
                $this->error("Line #{$lineId} not found.");

                return self::FAILURE;
            }
            $record = $service->calculateForDate($line, $date);
            if ($record) {
                $this->line("  {$line->name}: OEE = ".($record->oee_pct ?? 'N/A').'%');
            } else {
                $this->line("  {$line->name}: No data");
            }
        } else {
            $records = $service->calculateAll($date);
            foreach ($records as $record) {
                $lineName = $record->line->name;
                $shiftName = $record->shift?->name ?? 'All day';
                $this->line("  {$lineName} [{$shiftName}]: OEE = ".($record->oee_pct ?? 'N/A').'%');
            }
            $this->info(count($records).' OEE records calculated.');
        }

        return self::SUCCESS;
    }
}
