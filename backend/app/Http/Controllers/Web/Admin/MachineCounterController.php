<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MachineCounterRequest;
use App\Models\BatchStep;
use App\Models\MachineCounter;
use App\Models\MachineTag;
use App\Models\TopicMapping;
use App\Models\Workstation;
use App\Services\Machine\MachineCounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class MachineCounterController extends Controller
{
    public function __construct(private readonly MachineCounterService $counters) {}

    public function index(Request $request)
    {
        $selected = $request->integer('counter');
        $counters = MachineCounter::whereHas('connection')->with(['tag', 'mapping.topic', 'connection', 'step.batch.workOrder', 'workstation'])->orderBy('id')->get();
        $counter = $counters->firstWhere('id', $selected) ?? $counters->first();
        $tags = MachineTag::whereHas('connection')->whereIn('signal_type', ['good_count', 'reject_count', 'cycle_complete'])->get();
        $mappings = TopicMapping::whereHas('topic.machineConnection')->whereIn('action_type', [TopicMapping::ACTION_COUNT_STEP, TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY])->get();
        $steps = BatchStep::whereHas('batch.workOrder', fn ($q) => $q->whereIn('counting_source', ['machine', 'both'])->whereNotIn('status', \App\Models\WorkOrder::TERMINAL_STATUSES))
            ->whereNotIn('status', [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])->with(['batch.workOrder', 'workstation'])->get();

        return Inertia::render('admin/connectivity/Counters', [
            'counters' => $counters->map(fn ($c) => [...$c->toArray(), 'label' => $c->tag?->name ?? ('MQTT #'.$c->topic_mapping_id)]),
            'selectedId' => $counter?->id,
            'readings' => $counter?->readings()->when($request->boolean('review'), fn ($q) => $q->whereNull('reviewed_at')->whereIn('status', ['unassigned', 'blocked', 'partial', 'quality_unknown']))->orderByDesc('id')->paginate(40)->withQueryString(),
            'sources' => $tags->map(fn ($t) => ['type' => 'tag', 'id' => $t->id, 'label' => $t->name])->concat(
                $mappings->map(fn ($m) => ['type' => 'mapping', 'id' => $m->id, 'label' => 'MQTT #'.$m->id.' '.$m->description]))->values(),
            'workstations' => Workstation::whereHas('line')->get(['id', 'name', 'line_id']),
            'steps' => $steps->map(fn ($s) => ['id' => $s->id, 'workstation_id' => $s->workstation_id,
                'label' => $s->batch->workOrder->order_no.' / #'.$s->batch_id.' / '.$s->name.' (#'.$s->id.')',
                'passed_qty' => $s->passed_qty, 'scrap_qty' => $s->scrap_qty, 'status' => $s->status]),
            'canSimulate' => (bool) $counter?->is_simulated,
        ]);
    }

    public function register(MachineCounterRequest $request)
    {
        $data = $request->validated();
        $source = $data['source_type'] === 'tag'
            ? MachineTag::whereHas('connection')->whereIn('signal_type', ['good_count', 'reject_count', 'cycle_complete'])->findOrFail($data['source_id'])
            : TopicMapping::whereHas('topic.machineConnection')->whereIn('action_type', [TopicMapping::ACTION_COUNT_STEP, TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY])->findOrFail($data['source_id']);
        $counter = $this->counters->forSource($source);

        return redirect('/admin/connectivity/counters?counter='.$counter->id);
    }

    public function configure(MachineCounterRequest $request, int $counter)
    {
        $this->counters->configure($this->counter($counter), $request->validated(), $request->user()->id);

        return back()->with('success', __('Counter configuration saved.'));
    }

    public function useLegacy(MachineCounterRequest $request, int $counter)
    {
        $this->counters->useLegacy($this->counter($counter), $request->validated('note'), $request->user()->id);

        return back()->with('success', __('Legacy counting enabled.'));
    }

    public function rebaseline(MachineCounterRequest $request, int $counter)
    {
        $this->counters->rebaseline($this->counter($counter), $request->validated('note'), $request->user()->id);

        return back()->with('success', __('The next fresh reading will establish the baseline.'));
    }

    public function review(MachineCounterRequest $request, int $counter, int $reading)
    {
        $this->counters->review($this->counter($counter), $reading, $request->validated(), $request->user()->id);

        return back()->with('success', __('Reading review saved.'));
    }

    public function simulate(MachineCounterRequest $request, int $counter)
    {
        abort_unless($this->counter($counter)->is_simulated, 404);
        $data = $request->validated();
        $channel = $this->counter($counter);
        $at = isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();
        if ($channel->tag) {
            app(\App\Services\Machine\MachineSignalIngestor::class)->ingest($channel->tag, $data['value'], $at, $data['event_id'] ?? null);
        } else {
            $this->counters->ingest($channel, $data['value'], $at, $data['event_id'] ?? null);
        }

        return back()->with('success', __('Test reading recorded.'));
    }

    private function counter(int $id): MachineCounter
    {
        return MachineCounter::whereHas('connection')->findOrFail($id);
    }
}
