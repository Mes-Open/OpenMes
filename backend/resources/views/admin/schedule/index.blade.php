@extends('layouts.app')

@section('title', __('Production Schedule'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Production Schedule'), 'url' => null],
]" />

<div>

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Production Schedule') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('Week') }} {{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}
                @if($currentShift)
                    &nbsp;·&nbsp;
                    <span class="text-green-600 font-medium">
                        {{ __('Current shift') }}: {{ $currentShift->name }} ({{ substr($currentShift->start_time, 0, 5) }}–{{ substr($currentShift->end_time, 0, 5) }})
                    </span>
                @endif
            </p>
        </div>

        {{-- Filters --}}
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.schedule', ['week' => $weekStart->copy()->subWeek()->format('Y-\WW'), 'line_id' => $lineId]) }}"
               class="btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200" title="{{ __('Previous week') }}">
                &larr;
            </a>
            <form method="GET" class="flex items-center gap-2 flex-wrap">
                <input type="week" name="week" value="{{ $weekStart->format('Y-\WW') }}"
                       class="form-input text-sm py-2 min-h-0"
                       onchange="this.form.submit()">
                <select name="line_id" onchange="this.form.submit()"
                        class="form-input text-sm py-2 min-h-0">
                    <option value="">{{ __('All Lines') }}</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" {{ $lineId == $line->id ? 'selected' : '' }}>
                            {{ $line->name }}
                        </option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admin.schedule', ['week' => $weekStart->copy()->addWeek()->format('Y-\WW'), 'line_id' => $lineId]) }}"
               class="btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200" title="{{ __('Next week') }}">
                &rarr;
            </a>
            @if(!$weekStart->isCurrentWeek())
                <a href="{{ route('admin.schedule', ['line_id' => $lineId]) }}"
                   class="text-sm text-blue-600 hover:underline">{{ __('Today') }}</a>
            @endif
        </div>
    </div>

    @if($workOrders->isEmpty())
        <div class="card flex flex-col items-center py-16 text-center">
            <svg class="w-14 h-14 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-gray-500">{{ __('No work orders scheduled for this week.') }}</p>
            <a href="{{ route('admin.work-orders.create') }}" class="mt-4 btn-touch btn-primary">
                {{ __('Create Work Order') }}
            </a>
        </div>
    @else

    {{-- By Line --}}
    @if(!$lineId)
        <div class="space-y-6">
            @foreach($lines as $line)
                @php $lineOrders = $workOrders->where('line_id', $line->id); @endphp
                @if($lineOrders->isEmpty()) @continue @endif
                <div>
                    <h2 class="flex items-center gap-2 text-base font-bold text-gray-700 mb-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        {{ $line->name }}
                        <span class="text-xs font-normal text-gray-400">({{ $lineOrders->count() }} {{ trans_choice('order|orders', $lineOrders->count()) }})</span>
                    </h2>
                    @include('admin.schedule._table', ['orders' => $lineOrders])
                </div>
            @endforeach

            {{-- Orders with no line --}}
            @php $noLine = $workOrders->whereNull('line_id'); @endphp
            @if($noLine->isNotEmpty())
                <div>
                    <h2 class="flex items-center gap-2 text-base font-bold text-gray-700 mb-2">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        {{ __('No Line Assigned') }}
                    </h2>
                    @include('admin.schedule._table', ['orders' => $noLine])
                </div>
            @endif
        </div>
    @else
        @include('admin.schedule._table', ['orders' => $workOrders])
    @endif

    @endif
</div>
@endsection
