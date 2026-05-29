<?php

namespace App\View\Components;

use App\Models\MaintenanceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;
use Illuminate\View\View;

class MaintenanceReminder extends Component
{
    public $upcoming;

    public function __construct()
    {
        // Maintenance events due within the next 2 hours
        $this->upcoming = MaintenanceEvent::with(['line', 'workstation'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours(2))
            ->orderBy('scheduled_at')
            ->get();
    }

    public function shouldRender(): bool
    {
        return $this->upcoming->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.maintenance-reminder');
    }
}
