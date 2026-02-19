@extends('layouts.app')

@section('title', 'Process Template - ' . $processTemplate->name)

@section('content')
<div class="max-w-7xl mx-auto" x-data="{ editingStep: null, showAddForm: false }">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.process-templates.index', $productType) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Templates
        </a>
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-gray-800">{{ $processTemplate->name }}</h1>
                    @if($processTemplate->is_active)
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                    @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                    @endif
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">v{{ $processTemplate->version }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ $productType->name }} • {{ $processTemplate->steps->count() }} steps</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.product-types.process-templates.edit', [$productType, $processTemplate]) }}" class="btn-touch btn-secondary">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Template
                </a>
                <button @click="showAddForm = true" class="btn-touch btn-primary">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Step
                </button>
            </div>
        </div>
    </div>

    <!-- Add Step Form -->
    <div x-show="showAddForm" x-cloak class="card mb-6" x-transition>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">Add New Step</h2>
            <button @click="showAddForm = false" class="text-gray-600 hover:text-gray-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.product-types.process-templates.add-step', [$productType, $processTemplate]) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="form-label">Step Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input w-full"
                        placeholder="e.g., Attach component A"
                        required
                    >
                </div>
                <div>
                    <label for="workstation_id" class="form-label">Workstation (Optional)</label>
                    <select name="workstation_id" id="workstation_id" class="form-input w-full">
                        <option value="">No specific workstation</option>
                        @foreach($workstations as $workstation)
                            <option value="{{ $workstation->id }}">{{ $workstation->name }} ({{ $workstation->line->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="instruction" class="form-label">Instructions</label>
                    <textarea
                        id="instruction"
                        name="instruction"
                        rows="3"
                        class="form-input w-full"
                        placeholder="Detailed instructions for this step..."
                    ></textarea>
                </div>
                <div>
                    <label for="estimated_duration_minutes" class="form-label">Estimated Duration (minutes)</label>
                    <input
                        type="number"
                        id="estimated_duration_minutes"
                        name="estimated_duration_minutes"
                        min="0"
                        class="form-input w-full"
                        placeholder="e.g., 15"
                    >
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="showAddForm = false" class="btn-touch btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-touch btn-primary">
                    Add Step
                </button>
            </div>
        </form>
    </div>

    <!-- Steps List -->
    <div class="flex items-center gap-2 mb-4">
        <h2 class="text-xl font-bold text-gray-800">Production Steps</h2>
        <span x-data="{ show: false }" class="relative inline-flex items-center" @mouseenter="show = true" @mouseleave="show = false">
            <span class="w-5 h-5 rounded-full bg-gray-200 text-gray-500 text-xs flex items-center justify-center cursor-default select-none font-bold hover:bg-blue-100 hover:text-blue-600 transition-colors">i</span>
            <div x-show="show" x-cloak class="absolute left-7 top-0 z-20 w-72 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-xl leading-relaxed">
                <strong class="block mb-1 text-white">Kroki produkcji</strong>
                Lista operacji wykonywanych podczas produkcji — w kolejności od góry do dołu (krok 1 = pierwszy do wykonania). Użyj strzałek ↑↓ aby zmienić kolejność. Nowy krok zawsze trafia na koniec listy. Szacowany czas służy do rozliczania wydajności operatora.
            </div>
        </span>
        <span class="text-sm text-gray-500">(od pierwszego do ostatniego)</span>
    </div>

    @if($processTemplate->steps->count() > 0)
        <div class="space-y-3">
            @foreach($processTemplate->steps as $step)
                <div class="card">
                    <!-- View Mode -->
                    <div x-show="editingStep !== {{ $step->id }}">
                        <div class="flex items-start justify-between">
                            <div class="flex gap-4 flex-1">
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-lg font-bold text-blue-600">{{ $step->step_number }}</span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-gray-800">{{ $step->name }}</h3>
                                            @if($step->workstation)
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                    </svg>
                                                    {{ $step->workstation->name }} ({{ $step->workstation->line->name }})
                                                </p>
                                            @endif
                                            @if($step->estimated_duration_minutes)
                                                <p class="text-sm text-gray-600">
                                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    ~{{ $step->estimated_duration_minutes }} min
                                                </p>
                                            @endif
                                        </div>
                                        <!-- Actions -->
                                        <div class="flex gap-1 ml-4">
                                            <button @click="editingStep = {{ $step->id }}" class="text-blue-600 hover:text-blue-800 p-2" title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            @if(!$loop->first)
                                                <form method="POST" action="{{ route('admin.product-types.process-templates.move-step-up', [$productType, $processTemplate, $step]) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-gray-600 hover:text-gray-800 p-2" title="Move up">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                            @if(!$loop->last)
                                                <form method="POST" action="{{ route('admin.product-types.process-templates.move-step-down', [$productType, $processTemplate, $step]) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-gray-600 hover:text-gray-800 p-2" title="Move down">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.product-types.process-templates.delete-step', [$productType, $processTemplate, $step]) }}" class="inline" onsubmit="return confirm('Delete this step?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    @if($step->instruction)
                                        <div class="mt-2 p-3 bg-gray-50 rounded-lg">
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $step->instruction }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Mode -->
                    <div x-show="editingStep === {{ $step->id }}" x-cloak>
                        <form method="POST" action="{{ route('admin.product-types.process-templates.update-step', [$productType, $processTemplate, $step]) }}">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Step Name</label>
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $step->name }}"
                                        class="form-input w-full"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="form-label">Workstation (Optional)</label>
                                    <select name="workstation_id" class="form-input w-full">
                                        <option value="">No specific workstation</option>
                                        @foreach($workstations as $workstation)
                                            <option value="{{ $workstation->id }}" {{ $step->workstation_id == $workstation->id ? 'selected' : '' }}>
                                                {{ $workstation->name }} ({{ $workstation->line->name }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label">Instructions</label>
                                    <textarea
                                        name="instruction"
                                        rows="3"
                                        class="form-input w-full"
                                    >{{ $step->instruction }}</textarea>
                                </div>
                                <div>
                                    <label class="form-label">Estimated Duration (minutes)</label>
                                    <input
                                        type="number"
                                        name="estimated_duration_minutes"
                                        value="{{ $step->estimated_duration_minutes }}"
                                        min="0"
                                        class="form-input w-full"
                                    >
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-4">
                                <button type="button" @click="editingStep = null" class="btn-touch btn-secondary">
                                    Cancel
                                </button>
                                <button type="submit" class="btn-touch btn-primary">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-12">
            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-lg font-medium text-gray-700">No production steps yet</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">Add steps to define the manufacturing process for this product.</p>
            <button @click="showAddForm = true" class="inline-block btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add First Step
            </button>
        </div>
    @endif
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
