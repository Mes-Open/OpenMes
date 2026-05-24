@extends('layouts.app')

@section('title', __('Process Segments'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Process Segments'), 'url' => null],
]" />

@php
    /**
     * Segment type → tailwind colour mapping (badges).
     */
    $typeColors = [
        'production'  => 'bg-blue-100 text-blue-800',
        'inspection'  => 'bg-amber-100 text-amber-800',
        'maintenance' => 'bg-orange-100 text-orange-800',
        'setup'       => 'bg-gray-100 text-gray-700',
        'cleaning'    => 'bg-green-100 text-green-800',
        'transport'   => 'bg-purple-100 text-purple-800',
        'other'       => 'bg-gray-100 text-gray-700',
    ];

    $hasAnyFilter = request()->hasAny(['search', 'segment_type', 'workstation_type_id', 'is_active']);
@endphp

<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Process Segments') }}</h1>
            <p class="text-gray-600 mt-1">
                {{ __('Reusable operation definitions (ISA-95) — independent of products.') }}
            </p>
        </div>
        <a href="{{ route('admin.process-segments.create') }}"
           class="btn-touch btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Define segment') }}
        </a>
    </div>

    {{-- Session notices --}}
    @if(session('success'))
        <div class="card mb-4 border-l-4 border-green-400 bg-green-50">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="card mb-4 border-l-4 border-red-400 bg-red-50">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.process-segments.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-input w-full"
                       placeholder="{{ __('Search code or name…') }}">
            </div>
            <div>
                <label class="form-label">{{ __('Type') }}</label>
                <select name="segment_type" class="form-input w-full">
                    <option value="">{{ __('All types') }}</option>
                    @foreach(\App\Models\ProcessSegment::TYPES as $t)
                        <option value="{{ $t }}" @selected(request('segment_type') === $t)>
                            {{ ucfirst($t) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Workstation Type') }}</label>
                <select name="workstation_type_id" class="form-input w-full">
                    <option value="">{{ __('All') }}</option>
                    @foreach($workstationTypes as $wt)
                        <option value="{{ $wt->id }}" @selected(request('workstation_type_id') == $wt->id)>
                            {{ $wt->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Active') }}</label>
                <select name="is_active" class="form-input w-full">
                    <option value="">{{ __('All') }}</option>
                    <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Filter') }}</button>
            <a href="{{ route('admin.process-segments.index') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
        </div>
    </form>

    @if($segments->isEmpty())
        <div class="card text-center py-16">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
            </svg>
            @if($hasAnyFilter)
                <p class="text-gray-500 text-lg mb-2">{{ __('No segments match your filters.') }}</p>
                <a href="{{ route('admin.process-segments.index') }}" class="btn-touch btn-secondary inline-flex items-center gap-2 mt-2">
                    {{ __('Clear filters') }}
                </a>
            @else
                <p class="text-gray-500 text-lg mb-2">{{ __('No process segments yet.') }}</p>
                <p class="text-gray-400 text-sm mb-4">{{ __('Standardise operations across products by defining your first segment.') }}</p>
                <a href="{{ route('admin.process-segments.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Define your first process segment') }}
                </a>
            @endif
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                        <th class="py-2 pr-3">{{ __('Code') }}</th>
                        <th class="py-2 pr-3">{{ __('Name') }}</th>
                        <th class="py-2 pr-3">{{ __('Type') }}</th>
                        <th class="py-2 pr-3">{{ __('Workstation Type') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Duration') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Operators') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Used by') }}</th>
                        <th class="py-2 pr-3">{{ __('Status') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($segments as $seg)
                        @php $color = $typeColors[$seg->segment_type] ?? 'bg-gray-100 text-gray-700'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 pr-3 font-mono text-xs text-gray-700">{{ $seg->code }}</td>
                            <td class="py-2 pr-3">
                                <a href="{{ route('admin.process-segments.show', $seg) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ $seg->name }}
                                </a>
                            </td>
                            <td class="py-2 pr-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                    {{ ucfirst($seg->segment_type) }}
                                </span>
                            </td>
                            <td class="py-2 pr-3 text-gray-600">
                                {{ $seg->workstationType?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-3 text-right text-gray-700">
                                @if($seg->estimated_duration_minutes)
                                    {{ $seg->estimated_duration_minutes }} {{ __('min') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right text-gray-700">{{ $seg->required_operators }}</td>
                            <td class="py-2 pr-3 text-right">
                                @if(($seg->template_steps_count ?? 0) > 0)
                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-medium">{{ $seg->template_steps_count }}</span>
                                @else
                                    <span class="text-xs text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3">
                                @if($seg->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">
                                <div class="inline-flex gap-1">
                                    <a href="{{ route('admin.process-segments.show', $seg) }}"
                                       class="text-blue-600 hover:text-blue-800 p-1" title="{{ __('View') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.process-segments.edit', $seg) }}"
                                       class="text-gray-600 hover:text-gray-800 p-1" title="{{ __('Edit') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.process-segments.destroy', $seg) }}"
                                          class="inline"
                                          @if(($seg->template_steps_count ?? 0) > 0)
                                            title="{{ __('Used by templates — cannot delete') }}"
                                          @endif
                                          onsubmit="return confirm('{{ __('Delete this process segment?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="p-1 {{ ($seg->template_steps_count ?? 0) > 0 ? 'text-gray-300 cursor-not-allowed' : 'text-red-600 hover:text-red-800' }}"
                                                @disabled(($seg->template_steps_count ?? 0) > 0)
                                                title="{{ __('Delete') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $segments->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
