@extends('layouts.app')

@section('title', __('Maintenance Events'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Maintenance Events'), 'url' => null],
]" />

@php
    /**
     * Group events chronologically.
     *  - overdue            : pending/in_progress and scheduled_at < now()
     *  - this_week          : pending/in_progress and scheduled_at <= endOfWeek (not overdue)
     *  - later              : pending/in_progress and scheduled_at >  endOfWeek
     *  - recently_completed : completed/cancelled with updated_at >= now-30d
     */
    $now        = now();
    $endOfWeek  = $now->copy()->endOfWeek();
    $thirtyDays = $now->copy()->subDays(30);

    $grouped = [
        'overdue'             => collect(),
        'this_week'           => collect(),
        'later'               => collect(),
        'recently_completed'  => collect(),
    ];

    foreach ($events as $e) {
        $isOpen = in_array($e->status, ['pending', 'in_progress'], true);

        if ($isOpen) {
            if ($e->scheduled_at && $e->scheduled_at->lt($now)) {
                $grouped['overdue']->push($e);
            } elseif ($e->scheduled_at && $e->scheduled_at->lte($endOfWeek)) {
                $grouped['this_week']->push($e);
            } else {
                $grouped['later']->push($e);
            }
        } elseif (in_array($e->status, ['completed', 'cancelled'], true)
                  && $e->updated_at && $e->updated_at->gte($thirtyDays)) {
            $grouped['recently_completed']->push($e);
        }
    }

    $hasAnyFilter = request()->hasAny(['search', 'status', 'event_type', 'line_id']);
    $totalOnPage  = $events->count();
    $totalAll     = $events->total();
@endphp

<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Maintenance Events') }}</h1>
            <p class="text-gray-600 mt-1">
                {{ __('Showing :n of :total', ['n' => $totalOnPage, 'total' => $totalAll]) }}
            </p>
        </div>
        <a href="{{ route('admin.maintenance-events.create') }}"
           class="btn-touch btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Schedule event') }}
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
    <form method="GET" action="{{ route('admin.maintenance-events.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-input w-full"
                       placeholder="{{ __('Search title…') }}">
            </div>
            <div>
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-input w-full">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach(['pending','in_progress','completed','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>
                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Type') }}</label>
                <select name="event_type" class="form-input w-full">
                    <option value="">{{ __('All types') }}</option>
                    @foreach(['planned','corrective','inspection'] as $t)
                        <option value="{{ $t }}" @selected(request('event_type') === $t)>
                            {{ ucfirst($t) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Line') }}</label>
                <select name="line_id" class="form-input w-full">
                    <option value="">{{ __('All lines') }}</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" @selected(request('line_id') == $line->id)>
                            {{ $line->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Filter') }}</button>
            <a href="{{ route('admin.maintenance-events.index') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
        </div>
    </form>

    {{-- Empty state --}}
    @if($events->isEmpty())
        <div class="card text-center py-16">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/>
            </svg>
            @if($hasAnyFilter)
                <p class="text-gray-500 text-lg mb-2">{{ __('No maintenance events match your filters.') }}</p>
                <p class="text-gray-400 text-sm mb-4">{{ __('Try clearing the filters or adjusting your search.') }}</p>
                <a href="{{ route('admin.maintenance-events.index') }}" class="btn-touch btn-secondary inline-flex items-center gap-2">
                    {{ __('Clear filters') }}
                </a>
            @else
                <p class="text-gray-500 text-lg mb-2">{{ __('No maintenance events yet.') }}</p>
                <p class="text-gray-400 text-sm mb-4">{{ __('Schedule your first event to keep equipment healthy.') }}</p>
                <a href="{{ route('admin.maintenance-events.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Schedule first event') }}
                </a>
            @endif
        </div>
    @else
        {{-- Overdue --}}
        @if($grouped['overdue']->isNotEmpty())
            <div class="mt-2">
                <h2 class="text-sm font-semibold text-red-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                    {{ __('Overdue') }} ({{ $grouped['overdue']->count() }})
                </h2>
                <div class="space-y-2">
                    @foreach($grouped['overdue'] as $event)
                        @include('admin.maintenance-events.partials.card', ['event' => $event, 'isOverdue' => true])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- This week --}}
        @if($grouped['this_week']->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-sm font-semibold text-blue-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75"/>
                    </svg>
                    {{ __('This week') }} ({{ $grouped['this_week']->count() }})
                </h2>
                <div class="space-y-2">
                    @foreach($grouped['this_week'] as $event)
                        @include('admin.maintenance-events.partials.card', ['event' => $event, 'isOverdue' => false])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Later --}}
        @if($grouped['later']->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    {{ __('Later') }} ({{ $grouped['later']->count() }})
                </h2>
                <div class="space-y-2">
                    @foreach($grouped['later'] as $event)
                        @include('admin.maintenance-events.partials.card', ['event' => $event, 'isOverdue' => false])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recently completed --}}
        @if($grouped['recently_completed']->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-sm font-semibold text-green-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    {{ __('Completed (last 30 days)') }} ({{ $grouped['recently_completed']->count() }})
                </h2>
                <div class="space-y-2">
                    @foreach($grouped['recently_completed'] as $event)
                        @include('admin.maintenance-events.partials.card', ['event' => $event, 'isOverdue' => false, 'compact' => true])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $events->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
