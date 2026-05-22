<?php

namespace App\Console\Commands;

use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * List workers whose ISA-95 skill certifications have expired or will expire
 * within the next N days. Run manually or via a scheduler; not registered in
 * the scheduler by default.
 */
class CertificationExpiryCheck extends Command
{
    protected $signature = 'certs:check-expiry {--days=30 : Lookahead window in days for expiring certs}';

    protected $description = 'List workers with expired or soon-to-expire certifications';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('--days must be non-negative.');
            return self::INVALID;
        }

        $today = now()->toDateString();
        $cut   = now()->addDays($days)->toDateString();

        $rows = DB::table('worker_skills as ws')
            ->join('workers as w', 'w.id', '=', 'ws.worker_id')
            ->join('skills as s', 's.id', '=', 'ws.skill_id')
            ->whereNotNull('ws.certified_until')
            ->where('ws.certified_until', '<=', $cut)
            ->orderBy('ws.certified_until')
            ->orderBy('w.name')
            ->select([
                'w.code as worker_code',
                'w.name as worker_name',
                's.code as skill_code',
                's.name as skill_name',
                'ws.cert_level',
                'ws.certified_until',
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No certifications expire on or before {$cut}.");
            return self::SUCCESS;
        }

        $expired  = 0;
        $expiring = 0;
        $tableRows = [];
        foreach ($rows as $row) {
            $isExpired = $row->certified_until < $today;
            $isExpired ? $expired++ : $expiring++;
            $tableRows[] = [
                $row->worker_code,
                $row->worker_name,
                $row->skill_code,
                $row->skill_name,
                $row->cert_level,
                $row->certified_until,
                $isExpired ? 'EXPIRED' : 'EXPIRING',
            ];
        }

        $this->table(
            ['Worker', 'Name', 'Skill', 'Skill name', 'Level', 'Until', 'Status'],
            $tableRows,
        );
        $this->info("{$expired} expired, {$expiring} expiring within {$days} day(s).");

        return self::SUCCESS;
    }
}
