<div wire:poll.5s class="space-y-4">
    {{-- Material Allocation Confirmation Modal --}}
    @if($showAllocationConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="cancelAllocation">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-lg w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Material Allocation</h3>
                            <p class="text-sm text-gray-500">Starting this batch will reserve the following materials:</p>
                        </div>
                    </div>

                    <div class="border rounded-lg overflow-hidden mb-4">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-700">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Required</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">In Stock</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                @foreach($allocationPreview as $item)
                                    <tr class="{{ !$item['sufficient'] ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $item['material_name'] }}</span>
                                            <span class="text-xs text-gray-400 font-mono ml-1">{{ $item['material_code'] }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono">
                                            {{ number_format($item['required_qty'], 2) }} {{ $item['unit_of_measure'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono {{ !$item['sufficient'] ? 'text-red-600 font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $item['material_exists'] ? number_format($item['available_qty'], 2) : 'N/A' }} {{ $item['unit_of_measure'] }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($item['sufficient'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Low</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @php $hasInsufficient = collect($allocationPreview)->contains(fn($i) => !$i['sufficient']); @endphp

                    @if($hasInsufficient)
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                            <p class="text-sm text-amber-800 font-medium">
                                Some materials have insufficient stock. Proceeding will result in negative stock levels.
                            </p>
                        </div>
                    @endif

                    <div class="flex gap-3 justify-end">
                        <button wire:click="cancelAllocation" class="btn-touch btn-secondary">
                            Cancel
                        </button>
                        <button wire:click="confirmAllocation" class="btn-touch btn-primary">
                            Confirm & Start
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($batch)
        <!-- Batch Info -->
        <div class="card bg-blue-50 border border-blue-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Batch #{{ $batch->batch_number }}</h3>
                    <p class="text-sm text-gray-600">Target qty: {{ number_format($batch->target_qty, 2) }}</p>
                    @if($batch->produced_qty)
                        <p class="text-sm text-gray-600">Produced: {{ number_format($batch->produced_qty, 2) }}</p>
                    @endif
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    @if($batch->status === 'PENDING') bg-gray-100 text-gray-800
                    @elseif($batch->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                    @elseif($batch->status === 'DONE') bg-green-100 text-green-800
                    @else bg-gray-100 text-gray-600
                    @endif">
                    {{ ucfirst(strtolower(str_replace('_', ' ', $batch->status))) }}
                </span>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session()->has('success'))
            <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif
        @if(session()->has('error'))
            <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <!-- Steps List -->
        <div class="space-y-3">
            @foreach($batch->steps->sortBy('step_number') as $step)
                @php
                    $estimated = $estimatedDurations[$step->step_number] ?? null;
                    $isOverTime = $estimated && $step->duration_minutes !== null && $step->duration_minutes > $estimated;
                    $efficiency = ($estimated && $step->duration_minutes > 0)
                        ? round(($estimated / $step->duration_minutes) * 100)
                        : null;
                @endphp
                <div class="card
                    @if($step->status === 'DONE') bg-green-50 border border-green-200
                    @elseif($step->status === 'IN_PROGRESS') bg-blue-50 border border-blue-200
                    @else bg-white
                    @endif">

                    <div class="flex items-start justify-between gap-4">
                        <!-- Step Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="flex items-center justify-center h-8 w-8 rounded-full text-sm font-bold flex-shrink-0
                                    @if($step->status === 'DONE') bg-green-500 text-white
                                    @elseif($step->status === 'IN_PROGRESS') bg-blue-500 text-white
                                    @else bg-gray-300 text-gray-700
                                    @endif">
                                    {{ $step->step_number }}
                                </span>
                                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $step->name }}</h4>
                                @if($step->workstation)
                                    @php
                                        $isMyStation = session('selected_workstation_id') == $step->workstation_id;
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $isMyStation ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $step->workstation->name }}
                                    </span>
                                @endif
                                @if($estimated)
                                    <span class="text-xs text-gray-400 font-normal">est. {{ $estimated }}min</span>
                                @endif
                            </div>

                            @if($step->instruction)
                                <p class="text-sm text-gray-600 mb-2 ml-11">{{ $step->instruction }}</p>
                            @endif

                            <!-- Step Details -->
                            <div class="ml-11 text-sm text-gray-600 space-y-1">
                                @if($step->started_at)
                                    <p>
                                        Started: {{ \Carbon\Carbon::parse($step->started_at)->translatedFormat('d M Y H:i') }}
                                        @if($step->startedBy) by {{ $step->startedBy->name }} @endif
                                    </p>
                                @endif

                                @if($step->completed_at)
                                    <p>
                                        Completed: {{ \Carbon\Carbon::parse($step->completed_at)->translatedFormat('d M Y H:i') }}
                                        @if($step->completedBy) by {{ $step->completedBy->name }} @endif
                                    </p>
                                @endif

                                @if($step->duration_minutes !== null)
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium {{ $isOverTime ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $step->duration_minutes }}min actual
                                        </span>
                                        @if($estimated)
                                            <span class="text-gray-400">/ {{ $estimated }}min estimated</span>
                                            @if($efficiency !== null)
                                                <span class="px-1.5 py-0.5 rounded text-xs font-medium
                                                    {{ $efficiency >= 100 ? 'bg-green-100 text-green-700' : ($efficiency >= 75 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                    {{ $efficiency }}% efficiency
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                @elseif($step->status === 'IN_PROGRESS' && $step->started_at)
                                    <p class="text-blue-600">
                                        In progress: {{ \Carbon\Carbon::parse($step->started_at)->diffForHumans(null, true) }}
                                        @if($estimated)
                                            / est. {{ $estimated }}min
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2 min-w-[110px]">
                            @if($step->status === 'PENDING')
                                @php
                                    $previousStep = $batch->steps->where('step_number', $step->step_number - 1)->first();
                                    $canStart = !$previousStep || in_array($previousStep->status, ['DONE', 'SKIPPED']);
                                @endphp
                                <button
                                    wire:click="startStep({{ $step->id }})"
                                    @disabled(!$canStart)
                                    class="btn-touch btn-primary text-sm {{ !$canStart ? 'opacity-50 cursor-not-allowed' : '' }}"
                                >
                                    Start
                                </button>
                                @if(!$canStart)
                                    <p class="text-xs text-gray-400 text-center">Complete step {{ $step->step_number - 1 }} first</p>
                                @endif
                            @elseif($step->status === 'IN_PROGRESS')
                                <button
                                    wire:click="completeStep({{ $step->id }})"
                                    class="btn-touch btn-success text-sm"
                                >
                                    Complete
                                </button>
                            @else
                                <span class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium flex items-center gap-2 justify-center">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Done
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-8">
            <p class="text-gray-500">Batch not found</p>
        </div>
    @endif
</div>
