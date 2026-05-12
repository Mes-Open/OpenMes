@extends('layouts.app')

@section('title', __('Dashboard Setup'))

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Dashboard Setup') }}</h1>
            <p class="text-gray-500 text-sm mt-0.5">{{ __('Enable, disable, and reorder dashboard widgets') }}</p>
        </div>
    </div>

    @php
        $widgetData = $widgets->map(function($w) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'zone' => $w->zone,
                'description' => $w->description,
                'source' => $w->source,
                'module_name' => $w->module_name,
                'enabled' => $w->enabled,
            ];
        })->values();
    @endphp

    <div x-data="widgetManager()" class="space-y-2">
        <template x-for="(widget, index) in widgets" :key="widget.id">
            <div class="card flex items-center gap-3">

                {{-- Move buttons --}}
                <div class="flex flex-col shrink-0">
                    <button @click="moveUp(index)" :disabled="index === 0"
                            class="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-20 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>
                    <button @click="moveDown(index)" :disabled="index === widgets.length - 1"
                            class="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-20 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Position number --}}
                <span class="text-sm font-mono text-gray-400 w-6 text-center shrink-0" x-text="index + 1"></span>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200" x-text="widget.name"></h3>
                        <span class="px-2 py-0.5 rounded-full text-xs"
                              :class="widget.source === 'builtin' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'"
                              x-text="widget.source === 'builtin' ? 'Built-in' : widget.module_name"></span>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400" x-text="widget.zone"></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5" x-text="widget.description" x-show="widget.description"></p>
                </div>

                {{-- Toggle --}}
                <button @click="widget.enabled = !widget.enabled; dirty = true" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shrink-0"
                        :class="widget.enabled
                            ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300'
                            : 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900 dark:text-red-300'"
                        x-text="widget.enabled ? '{{ __("Enabled") }}' : '{{ __("Disabled") }}'">
                </button>
            </div>
        </template>

        <div class="flex justify-between items-center mt-6">
            <div>
                <p class="text-xs text-gray-400">{{ __('Use arrows to reorder. Modules can register additional widgets.') }}</p>
                <p x-show="dirty" x-cloak class="text-xs text-orange-600 font-medium mt-1">{{ __('You have unsaved changes!') }}</p>
            </div>
            <button @click="saveAll()" class="btn-touch btn-primary" :class="dirty ? 'animate-pulse ring-2 ring-blue-400' : ''">{{ __('Save') }}</button>
        </div>

        {{-- Toast --}}
        <div x-show="saved" x-cloak x-transition
             class="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>{{ __('Saved! Redirecting...') }}</span>
        </div>
    </div>
</div>

@push('scripts')
<script>
function widgetManager() {
    return {
        widgets: {!! json_encode($widgetData) !!},
        saved: false,
        dirty: false,

        moveUp(index) {
            if (index === 0) return;
            const item = this.widgets.splice(index, 1)[0];
            this.widgets.splice(index - 1, 0, item);
            this.dirty = true;
        },
        moveDown(index) {
            if (index >= this.widgets.length - 1) return;
            const item = this.widgets.splice(index, 1)[0];
            this.widgets.splice(index + 1, 0, item);
            this.dirty = true;
        },
        async saveAll() {
            await fetch('{{ route("admin.dashboard-widgets.save-all") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    widgets: this.widgets.map(w => ({ id: w.id, enabled: w.enabled }))
                })
            });
            this.saved = true;
            setTimeout(() => { window.location.href = '{{ route("admin.dashboard") }}'; }, 1200);
        }
    };
}
</script>
@endpush
@endsection
