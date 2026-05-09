<div>
    {{-- Active Downtime Banner --}}
    @if($activeDowntime)
        <div class="flex items-center gap-3 p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg"
             x-data="{ elapsed: '' }"
             x-init="
                 const start = new Date('{{ $activeDowntime->started_at->toIso8601String() }}');
                 const tick = () => {
                     const diff = Math.floor((Date.now() - start) / 60000);
                     elapsed = diff + ' min';
                 };
                 tick(); setInterval(tick, 10000);
             ">
            <div class="flex-shrink-0">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                    Downtime: <strong>{{ $activeDowntime->reason->name }}</strong>
                    <span class="text-red-600 dark:text-red-300 ml-2" x-text="elapsed"></span>
                </p>
                @if($activeDowntime->notes)
                    <p class="text-xs text-red-600 dark:text-red-400 truncate">{{ $activeDowntime->notes }}</p>
                @endif
            </div>
            <button wire:click="stopDowntime" class="btn-touch btn-danger text-sm shrink-0">
                Stop Downtime
            </button>
        </div>
    @else
        {{-- Report Button --}}
        <button wire:click="openModal" class="btn-touch btn-danger-outline text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Report Downtime
        </button>
    @endif

    {{-- Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="$set('showModal', false)">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Report Downtime</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason *</label>
                            <select wire:model="reasonId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100">
                                <option value="0">-- Select reason --</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->id }}">
                                        {{ $reason->name }}
                                        @if($reason->is_planned) (planned) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (optional)</label>
                            <textarea wire:model="notes" rows="2" placeholder="Short description..."
                                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100"></textarea>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button wire:click="$set('showModal', false)" class="btn-touch btn-secondary">Cancel</button>
                        <button wire:click="startDowntime" class="btn-touch btn-danger">Start Downtime</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
