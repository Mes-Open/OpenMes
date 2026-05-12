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

    <div x-data="widgetManager()" class="space-y-2">
        <template x-for="(widget, index) in widgets" :key="widget.id">
            <div class="card flex items-center gap-4 transition-all select-none"
                 draggable="true"
                 @dragstart="onDragStart($event, index)"
                 @dragover.prevent="onDragOver(index)"
                 @drop.prevent="onDrop(index)"
                 @dragend="onDragEnd()"
                 :class="{
                    'ring-2 ring-blue-400 ring-dashed bg-blue-50 dark:bg-blue-900/20': overIndex === index && dragIndex !== index,
                    'opacity-40 scale-95': dragIndex === index
                 }">

                <span class="cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 8zm0 6a2 2 0 10.001 4.001A2 2 0 007 14zm6-8a2 2 0 10-.001-4.001A2 2 0 0013 6zm0 2a2 2 0 10.001 4.001A2 2 0 0013 8zm0 6a2 2 0 10.001 4.001A2 2 0 0013 14z"/>
                    </svg>
                </span>

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

                <button @click="toggleWidget(widget)" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shrink-0"
                        :class="widget.enabled
                            ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400'"
                        x-text="widget.enabled ? '{{ __("Enabled") }}' : '{{ __("Disabled") }}'">
                </button>
            </div>
        </template>
    </div>

    <p class="text-xs text-gray-400 mt-4 text-center">{{ __('Drag widgets to reorder. Modules can register additional widgets.') }}</p>
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

@push('scripts')
<script>
function widgetManager() {
    return {
        widgets: {!! json_encode($widgetData) !!},
        toggleBase: '{{ url("admin/dashboard-widgets") }}',
        dragIndex: null,
        overIndex: null,

        onDragStart(e, i) {
            this.dragIndex = i;
            e.dataTransfer.effectAllowed = 'move';
        },
        onDragOver(i) {
            this.overIndex = i;
        },
        onDrop(i) {
            if (this.dragIndex === null || this.dragIndex === i) {
                this.dragIndex = null;
                this.overIndex = null;
                return;
            }
            const item = this.widgets.splice(this.dragIndex, 1)[0];
            this.widgets.splice(i, 0, item);
            this.dragIndex = null;
            this.overIndex = null;
            this.saveOrder();
        },
        onDragEnd() {
            this.dragIndex = null;
            this.overIndex = null;
        },
        saveOrder() {
            fetch('{{ route("admin.dashboard-widgets.reorder") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ order: this.widgets.map(w => w.id) })
            });
        },
        toggleWidget(widget) {
            widget.enabled = !widget.enabled;
            fetch(this.toggleBase + '/' + widget.id + '/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                }
            });
        }
    };
}
</script>
@endpush
@endsection
