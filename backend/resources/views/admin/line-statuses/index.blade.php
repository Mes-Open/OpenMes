@extends('layouts.app')

@section('title', 'Line Statuses')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Line Statuses', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Line Statuses</h1>
            <p class="text-gray-600 mt-1">Global kanban statuses available on all production lines. You can also add line-specific statuses from the line detail page.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Global Statuses List -->
    <div class="card mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Global Statuses</h2>

        @if($globalStatuses->isEmpty())
            <p class="text-sm text-gray-500 text-center py-6">No global statuses yet. Add one below.</p>
        @else
            <div class="space-y-2 mb-4">
                @foreach($globalStatuses as $status)
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg" x-data="{ editing: false }">
                        <span class="inline-block w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $status->color }}"></span>

                        <div class="flex-1 min-w-0" x-show="!editing">
                            <span class="font-medium text-gray-800">{{ $status->name }}</span>
                            @if($status->is_default)
                                <span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">default</span>
                            @endif
                            <span class="ml-2 text-xs text-gray-400">order: {{ $status->sort_order }}</span>
                        </div>

                        <!-- Inline edit form -->
                        <form method="POST" action="{{ route('admin.line-statuses.update', $status) }}"
                              class="flex-1 flex items-center gap-2" x-show="editing" x-cloak>
                            @csrf
                            @method('PUT')
                            <input type="color" name="color" value="{{ $status->color }}"
                                   class="w-8 h-8 rounded cursor-pointer border border-gray-300 p-0.5" required>
                            <input type="text" name="name" value="{{ $status->name }}"
                                   class="form-input flex-1 py-1.5 text-sm" required>
                            <input type="number" name="sort_order" value="{{ $status->sort_order }}"
                                   class="form-input w-16 py-1.5 text-sm" min="0">
                            <label class="flex items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="is_default" value="1"
                                       {{ $status->is_default ? 'checked' : '' }}> Default
                            </label>
                            <button type="submit" class="btn-touch btn-primary py-1.5 text-sm">Save</button>
                            <button type="button" @click="editing = false" class="btn-touch btn-secondary py-1.5 text-sm">Cancel</button>
                        </form>

                        <div class="flex items-center gap-1 flex-shrink-0" x-show="!editing">
                            <button type="button" @click="editing = true"
                                    class="text-gray-400 hover:text-blue-600 p-1.5 rounded" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <form method="POST" action="{{ route('admin.line-statuses.destroy', $status) }}"
                                  onsubmit="return confirm('Delete status \'{{ $status->name }}\'? Work orders using it will be unassigned.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-600 p-1.5 rounded" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Add new global status -->
        <form method="POST" action="{{ route('admin.line-statuses.store') }}"
              class="border-t border-gray-200 pt-4 flex items-end gap-3 flex-wrap">
            @csrf
            <div class="flex flex-col gap-1">
                <label class="form-label text-xs">Color</label>
                <input type="color" name="color" value="#6B7280"
                       class="w-10 h-10 rounded cursor-pointer border border-gray-300 p-0.5" required>
            </div>
            <div class="flex flex-col gap-1 flex-1 min-w-[160px]">
                <label class="form-label text-xs">Name</label>
                <input type="text" name="name" placeholder="e.g. On Hold"
                       class="form-input py-1.5 text-sm" required maxlength="100">
            </div>
            <div class="flex flex-col gap-1 w-20">
                <label class="form-label text-xs">Order</label>
                <input type="number" name="sort_order" value="0" min="0"
                       class="form-input py-1.5 text-sm">
            </div>
            <div class="flex items-center gap-1 pb-1">
                <input type="checkbox" name="is_default" value="1" id="new_is_default">
                <label for="new_is_default" class="text-sm text-gray-600">Default</label>
            </div>
            <button type="submit" class="btn-touch btn-primary py-1.5 text-sm">
                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Status
            </button>
        </form>
    </div>

    <div class="text-sm text-gray-500">
        <p>To add line-specific statuses (only visible on one line), go to <a href="{{ route('admin.lines.index') }}" class="text-blue-600 hover:underline">Production Lines</a> and open a line's detail page.</p>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
