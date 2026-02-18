@extends('layouts.app')

@section('title', 'Issues')

@php
    $isAdmin = auth()->user()->hasRole('Admin');
    $routePrefix = $isAdmin ? 'admin' : 'supervisor';
@endphp

@section('content')
<div class="max-w-7xl mx-auto" x-data="issueManager()">

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Issues</h1>
            <p class="text-gray-600 mt-1">{{ $issues->total() }} issues total</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input w-full">
                    <option value="">All statuses</option>
                    @foreach(['OPEN','ACKNOWLEDGED','RESOLVED','CLOSED'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Line</label>
                <select name="line_id" class="form-input w-full">
                    <option value="">All lines</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" @selected(request('line_id') == $line->id)>{{ $line->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="blocking" value="1" @checked(request('blocking')) class="rounded border-gray-300">
                    <span class="text-sm text-gray-700 font-medium">Blocking only</span>
                </label>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">Filter</button>
            <a href="{{ route($routePrefix . '.issues.index') }}" class="btn-touch btn-secondary text-sm">Clear</a>
        </div>
    </form>

    {{-- Issues List --}}
    <div class="space-y-3">
        @forelse($issues as $issue)
            <div class="card {{ $issue->isBlocking() ? 'border-l-4 border-red-400' : '' }}">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                @if($issue->status === 'OPEN')             bg-red-100 text-red-700
                                @elseif($issue->status === 'ACKNOWLEDGED') bg-yellow-100 text-yellow-700
                                @elseif($issue->status === 'RESOLVED')     bg-green-100 text-green-700
                                @else                                      bg-gray-100 text-gray-500
                                @endif">
                                {{ $issue->status }}
                            </span>
                            @if($issue->isBlocking())
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">BLOCKING</span>
                            @endif
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                @if($issue->issueType->severity === 'CRITICAL')   bg-red-50 text-red-800
                                @elseif($issue->issueType->severity === 'HIGH')    bg-orange-50 text-orange-800
                                @elseif($issue->issueType->severity === 'MEDIUM')  bg-yellow-50 text-yellow-700
                                @else                                              bg-gray-50 text-gray-600
                                @endif">
                                {{ $issue->issueType->severity }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $issue->issueType->name }}</span>
                        </div>

                        <p class="font-semibold text-gray-800">{{ $issue->title }}</p>
                        @if($issue->description)
                            <p class="text-sm text-gray-600 mt-1">{{ Str::limit($issue->description, 200) }}</p>
                        @endif

                        <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
                            <span>
                                Work Order:
                                @if($isAdmin)
                                    <a href="{{ route('admin.work-orders.show', $issue->workOrder) }}" class="text-blue-600 hover:underline font-mono">{{ $issue->workOrder->order_no }}</a>
                                @else
                                    <span class="font-mono">{{ $issue->workOrder->order_no }}</span>
                                @endif
                            </span>
                            @if($issue->workOrder->line)
                                <span>Line: {{ $issue->workOrder->line->name }}</span>
                            @endif
                            <span>Reported {{ $issue->reported_at->diffForHumans() }} by {{ $issue->reportedBy->name ?? 'unknown' }}</span>
                            @if($issue->resolution_notes)
                                <span class="text-green-700">Resolution: {{ Str::limit($issue->resolution_notes, 80) }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col gap-2 sm:flex-shrink-0 sm:min-w-[120px]">
                        @if($issue->status === 'OPEN')
                            <form method="POST" action="{{ route($routePrefix . '.issues.acknowledge', $issue) }}">
                                @csrf
                                <button class="btn-touch btn-secondary text-sm w-full">Acknowledge</button>
                            </form>
                        @endif

                        @if(in_array($issue->status, ['OPEN','ACKNOWLEDGED']))
                            <button
                                @click="openResolve({{ $issue->id }}, '{{ route($routePrefix . '.issues.resolve', $issue) }}')"
                                class="btn-touch btn-primary text-sm w-full">
                                Resolve
                            </button>
                        @endif

                        @if($issue->status === 'RESOLVED')
                            <form method="POST" action="{{ route($routePrefix . '.issues.close', $issue) }}">
                                @csrf
                                <button class="btn-touch btn-secondary text-sm w-full">Close</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card text-center py-12">
                <p class="text-gray-400 text-lg">No issues found.</p>
                @if(request()->hasAny(['status','line_id','blocking']))
                    <a href="{{ route($routePrefix . '.issues.index') }}" class="text-blue-600 hover:underline mt-2 block text-sm">Clear filters</a>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $issues->withQueryString()->links() }}</div>

    {{-- Resolve Modal --}}
    <div x-show="resolveOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="resolveOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.stop
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Resolve Issue</h3>
                <form :action="resolveAction" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Resolution notes (optional)</label>
                        <textarea name="resolution_notes" x-model="resolveNotes" rows="3"
                                  class="form-input w-full"
                                  placeholder="Describe how the issue was resolved…"></textarea>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="resolveOpen = false" class="btn-touch btn-secondary">Cancel</button>
                        <button type="submit" class="btn-touch btn-primary">Mark Resolved</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function issueManager() {
    return {
        resolveOpen: false,
        resolveAction: '',
        resolveNotes: '',
        openResolve(id, url) {
            this.resolveAction = url;
            this.resolveNotes  = '';
            this.resolveOpen   = true;
        },
    };
}
</script>
@endsection
