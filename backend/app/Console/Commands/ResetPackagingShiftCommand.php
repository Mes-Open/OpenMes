<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPackagingShiftCommand extends Command
{
    protected $signature = 'packaging:reset-shift';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('Reset packed_qty counters on work_orders for new shift start');
    }

    public function handle(): int
    {
        $count = DB::table('work_orders')
            ->where('packed_qty', '>', 0)
            ->update(['packed_qty' => 0]);

        $this->info("Reset packed_qty on {$count} work orders.");

        return self::SUCCESS;
    }
}
