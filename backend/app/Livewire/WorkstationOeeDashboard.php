<?php

namespace App\Livewire;

use App\Models\Workstation;
use App\Services\Analytics\OeeCalculationService;
use Livewire\Component;

class WorkstationOeeDashboard extends Component
{
    public $workstationId;
    public $oeeData;

    public function mount($workstationId)
    {
        $this->workstationId = $workstationId;
        $this->refreshOee();
    }

    /**
     * Periodically refresh OEE for real-time monitoring.
     */
    public function refreshOee()
    {
        $workstation = Workstation::find($this->workstationId);
        $oeeService = app(OeeCalculationService::class);

        $this->oeeData = $oeeService->calculateOee(
            $workstation,
            now()->startOfDay()->toDateTimeString(),
            now()->toDateTimeString()
        );
    }

    public function render()
    {
        return view('livewire.workstation-oee-dashboard');
    }
}
