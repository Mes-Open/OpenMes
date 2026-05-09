<?php

namespace App\Livewire;

use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Services\Production\DowntimeService;
use Livewire\Component;

class DowntimeReporter extends Component
{
    public int $lineId;

    public ?int $workstationId = null;

    public bool $showModal = false;

    public ?int $activeDowntimeId = null;

    public int $reasonId = 0;

    public string $notes = '';

    public function mount(int $lineId, ?int $workstationId = null): void
    {
        $this->lineId = $lineId;
        $this->workstationId = $workstationId;
        $this->loadActiveDowntime();
    }

    public function loadActiveDowntime(): void
    {
        $active = ProductionDowntime::where('line_id', $this->lineId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        $this->activeDowntimeId = $active?->id;
    }

    public function openModal(): void
    {
        $this->showModal = true;
        $this->reasonId = 0;
        $this->notes = '';
    }

    public function startDowntime(): void
    {
        if (! $this->reasonId) {
            session()->flash('error', 'Please select a reason.');

            return;
        }

        $line = Line::find($this->lineId);
        if (! $line) {
            return;
        }

        $downtime = app(DowntimeService::class)->start(
            $line,
            $this->reasonId,
            auth()->user(),
            $this->workstationId,
            $this->notes ?: null
        );

        $this->activeDowntimeId = $downtime->id;
        $this->showModal = false;
        session()->flash('success', 'Downtime started.');
    }

    public function stopDowntime(): void
    {
        if (! $this->activeDowntimeId) {
            return;
        }

        $downtime = ProductionDowntime::find($this->activeDowntimeId);
        if ($downtime) {
            app(DowntimeService::class)->stop($downtime);
        }

        $this->activeDowntimeId = null;
        session()->flash('success', 'Downtime stopped. Duration: '.$downtime->duration_minutes.' min.');
    }

    public function render()
    {
        $reasons = DowntimeReason::active()->orderBy('name')->get();
        $activeDowntime = $this->activeDowntimeId
            ? ProductionDowntime::with('reason')->find($this->activeDowntimeId)
            : null;

        return view('livewire.downtime-reporter', compact('reasons', 'activeDowntime'));
    }
}
