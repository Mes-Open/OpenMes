@extends('layouts.app')

@section('title', 'Production Schedule')

@section('content')
<div>

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Production Schedule</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Week {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
                @if($currentShift)
                    &nbsp;·&nbsp;
                    <span class="text-green-600 font-medium">
                        Current shift: {{ $currentShift->name }} ({{ substr($currentShift->start_time, 0, 5) }}–{{ substr($currentShift->end_time, 0, 5) }})
                    </span>
                @endif
            </p>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <input type="week" name="week" value="{{ $weekStart->format('Y-\WW') }}"
                   class="form-input text-sm py-2 min-h-0"
                   onchange="this.form.submit()">
            <select name="line_id" onchange="this.form.submit()"
                    class="form-input text-sm py-2 min-h-0">
                <option value="">All Lines</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}" {{ $lineId == $line->id ? 'selected' : '' }}>
                        {{ $line->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if($workOrders->isEmpty())
        <div class="card flex flex-col items-center py-16 text-center">
            <svg class="w-14 h-14 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-gray-500">No work orders scheduled for this week.</p>
            <a href="{{ route('admin.work-orders.create') }}" class="mt-4 btn-touch btn-primary">
                Create Work Order
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
                        <span class="text-xs font-normal text-gray-400">({{ $lineOrders->count() }} order{{ $lineOrders->count() !== 1 ? 's' : '' }})</span>
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
                        No Line Assigned
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
