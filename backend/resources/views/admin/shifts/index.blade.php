@extends('layouts.app')

@section('title', 'Shifts')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ showForm: false, editId: null, form: {} }">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Shifts</h1>
            <p class="text-sm text-gray-500 mt-0.5">Define morning, afternoon and night shifts per line.</p>
        </div>
        <button @click="showForm = !showForm; editId = null; form = { days_of_week: [1,2,3,4,5] }"
                class="btn-touch btn-primary text-sm px-4 py-2">
            + New Shift
        </button>
    </div>

    {{-- Create form --}}
    <div x-show="showForm && !editId" x-cloak
         x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="card mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">New Shift</h2>
        @include('admin.shifts._form', ['action' => route('admin.shifts.store'), 'method' => 'POST', 'shift' => null])
    </div>

    {{-- Shifts list --}}
    @if($shifts->isEmpty())
        <div class="card text-center py-12 text-gray-500">
            No shifts defined yet. Create one to get started.
        </div>
    @else
    <div class="space-y-3">
        @foreach($shifts as $shift)
        <div class="card" x-data="{ editing: false }">
            <div x-show="!editing">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="font-semibold text-gray-800">{{ $shift->name }}</span>
                            <span class="font-mono text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded px-2 py-0.5">
                                {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                            </span>
                            @if(!$shift->is_active)
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Inactive</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-2 text-sm text-gray-500 flex-wrap">
                            <span>{{ $shift->line?->name ?? 'All lines' }}</span>
                            <span class="flex gap-1">
                                @foreach([1,2,3,4,5,6,7] as $d)
                                    <span class="w-7 h-7 flex items-center justify-center rounded text-xs font-medium
                                        {{ in_array($d, $shift->days_of_week) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400' }}">
                                        {{ \App\Models\Shift::dayName($d) }}
                                    </span>
                                @endforeach
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="editing = true"
                                class="text-sm text-blue-600 hover:underline">Edit</button>
                        <form method="POST" action="{{ route('admin.shifts.destroy', $shift) }}"
                              onsubmit="return confirm('Delete shift {{ $shift->name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-red-500 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Inline edit form --}}
            <div x-show="editing" x-cloak>
                <h3 class="font-bold text-gray-800 mb-3">Edit Shift</h3>
                @include('admin.shifts._form', [
                    'action' => route('admin.shifts.update', $shift),
                    'method' => 'PUT',
                    'shift'  => $shift,
                ])
                <button @click="editing = false" class="mt-2 text-sm text-gray-500 hover:underline">Cancel</button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
