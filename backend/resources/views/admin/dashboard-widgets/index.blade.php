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

    <div class="space-y-3" x-data="{
        dragIndex: null,
        dragOverIndex: null,
        items: {{ $widgets->pluck('id')->toJson() }},
        dragStart(i) { this.dragIndex = i },
        dragOver(i) { this.dragOverIndex = i },
        drop(i) {
            if (this.dragIndex === null || this.dragIndex === i) { this.dragIndex = null; this.dragOverIndex = null; return; }
            const item = this.items.splice(this.dragIndex, 1)[0];
            this.items.splice(i, 0, item);
            this.dragIndex = null;
            this.dragOverIndex = null;
            fetch('{{ route('admin.dashboard-widgets.reorder') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ order: this.items })
            });
        },
        dragEnd() { this.dragIndex = null; this.dragOverIndex = null; }
    }">
        @foreach($widgets as $index => $widget)
            <div class="card flex items-center gap-4 transition-all"
                 draggable="true"
                 @dragstart="dragStart({{ $index }})"
                 @dragover.prevent="dragOver({{ $index }})"
                 @drop.prevent="drop({{ $index }})"
                 @dragend="dragEnd()"
                 :class="{
                    'bg-blue-50 border-blue-200 border-dashed': dragOverIndex === {{ $index }} && dragIndex !== {{ $index }},
                    'opacity-50': dragIndex === {{ $index }}
                 }">

                {{-- Drag handle --}}
                <span class="cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 8zm0 6a2 2 0 10.001 4.001A2 2 0 007 14zm6-8a2 2 0 10-.001-4.001A2 2 0 0013 6zm0 2a2 2 0 10.001 4.001A2 2 0 0013 8zm0 6a2 2 0 10.001 4.001A2 2 0 0013 14z"/>
                    </svg>
                </span>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-800">{{ $widget->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $widget->source === 'builtin' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                            {{ $widget->source === 'builtin' ? 'Built-in' : $widget->module_name }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $widget->zone }}</span>
                    </div>
                    @if($widget->description)
                        <p class="text-xs text-gray-500 mt-0.5">{{ $widget->description }}</p>
                    @endif
                </div>

                {{-- Toggle --}}
                <form action="{{ route('admin.dashboard-widgets.toggle', $widget) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                        {{ $widget->enabled
                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                        {{ $widget->enabled ? __('Enabled') : __('Disabled') }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-400 mt-4 text-center">Drag widgets to reorder. Modules can register additional widgets.</p>
</div>
@endsection
