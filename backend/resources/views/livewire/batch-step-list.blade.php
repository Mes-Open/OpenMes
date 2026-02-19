<div wire:poll.5s class="space-y-4">
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
                                <h4 class="text-lg font-bold text-gray-800">{{ $step->name }}</h4>
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
                                        Started: {{ \Carbon\Carbon::parse($step->started_at)->format('d M Y H:i') }}
                                        @if($step->startedBy) by {{ $step->startedBy->name }} @endif
                                    </p>
                                @endif

                                @if($step->completed_at)
                                    <p>
                                        Completed: {{ \Carbon\Carbon::parse($step->completed_at)->format('d M Y H:i') }}
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
