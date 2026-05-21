@extends('layouts.app')

@section('title', __('Maintenance Schedules'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Maintenance Schedules'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Maintenance Schedules') }}</h1>
            <p class="text-gray-600 mt-1">
                {{ __('Recurring preventive maintenance — events are generated automatically.') }}
            </p>
        </div>
        <a href="{{ route('admin.maintenance-schedules.create') }}"
           class="btn-touch btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('New schedule') }}
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
    <form method="GET" action="{{ route('admin.maintenance-schedules.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-input w-full"
                       placeholder="{{ __('Search name…') }}">
            </div>
            <div>
                <label class="form-label">{{ __('Frequency') }}</label>
                <select name="frequency" class="form-input w-full">
                    <option value="">{{ __('All frequencies') }}</option>
                    @foreach(\App\Models\MaintenanceSchedule::FREQUENCIES as $f)
                        <option value="{{ $f }}" @selected(request('frequency') === $f)>
                            {{ __(ucfirst(str_replace('_',' ', $f))) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Status') }}</label>
                <select name="is_active" class="form-input w-full">
                    <option value="">{{ __('All') }}</option>
                    <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Filter') }}</button>
            <a href="{{ route('admin.maintenance-schedules.index') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
        </div>
    </form>

    @if($schedules->isEmpty())
        <div class="card text-center py-16">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75"/>
            </svg>
            <p class="text-gray-500 text-lg mb-2">{{ __('No maintenance schedules yet.') }}</p>
            <p class="text-gray-400 text-sm mb-4">{{ __('Create one to auto-generate preventive maintenance events.') }}</p>
            <a href="{{ route('admin.maintenance-schedules.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Create first schedule') }}
            </a>
        </div>
    @else
        <div class="card overflow-x-auto p-0">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Name') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Target') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Frequency') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Next due') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Last executed') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">{{ __('Status') }}</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-700">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $schedule)
                        @php
                            $target = $schedule->line?->name
                                ?? $schedule->workstation?->name
                                ?? $schedule->tool?->name
                                ?? '—';
                            $freqLabel = $schedule->interval_value > 1
                                ? __('Every :n :unit', [
                                    'n' => $schedule->interval_value,
                                    'unit' => str_replace('_', ' ', $schedule->frequency),
                                  ])
                                : __(ucfirst(str_replace('_', ' ', $schedule->frequency)));
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $schedule->name }}</div>
                                @if($schedule->description)
                                    <div class="text-xs text-gray-500 truncate max-w-xs">{{ $schedule->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $target }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $freqLabel }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $schedule->next_due_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $schedule->last_executed_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($schedule->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.maintenance-schedules.edit', $schedule) }}"
                                       class="text-blue-600 hover:text-blue-800 text-xs">{{ __('Edit') }}</a>

                                    @if($schedule->is_active)
                                        <form method="POST" action="{{ route('admin.maintenance-schedules.generate-now', $schedule) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="text-emerald-600 hover:text-emerald-800 text-xs"
                                                    onclick="return confirm('{{ __('Generate an event now for this schedule?') }}')">
                                                {{ __('Generate now') }}
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.maintenance-schedules.destroy', $schedule) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800 text-xs"
                                                onclick="return confirm('{{ __('Delete this schedule? Existing generated events will keep history.') }}')">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $schedules->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
