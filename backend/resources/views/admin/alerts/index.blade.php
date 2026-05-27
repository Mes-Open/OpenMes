@extends('layouts.app')

@section('title', __('Alerts'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Alerts'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto" x-data="alertPoller()" x-init="startPolling()">

    <div class="flex items-center gap-3 mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Alerts') }}</h1>
        @php $total = $blockingIssues->count() + $nonBlockingIssues->count() + $overdueOrders->count() + $blockedOrders->count(); @endphp
        @if($total > 0)
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-600 text-white text-sm font-bold" x-ref="badge">
                {{ $total }}
            </span>
        @endif
        <span class="ml-auto flex items-center gap-1.5 text-xs text-gray-400">
            <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>
            {{ __('Live') }}
        </span>
    </div>

    {{-- New alert banner (shown by polling) --}}
    <div x-show="newAlert" x-cloak x-transition
         class="mb-4 p-4 bg-red-600 text-white rounded-xl shadow-lg flex items-center gap-3 animate-pulse cursor-pointer"
         @click="window.location.reload()">
        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span class="font-bold text-lg" x-text="newAlertMsg"></span>
        <span class="ml-auto text-sm opacity-80">{{ __('Click to refresh') }}</span>
    </div>

    @if($total === 0)
        <div class="card flex flex-col items-center py-16 text-center">
            <svg class="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xl font-semibold text-gray-700">{{ __('All clear') }}</p>
            <p class="text-gray-500 mt-1">{{ __('No active alerts at this time.') }}</p>
        </div>
    @else
        {{-- Two-column layout: Blocking Issues left, work-order alerts right. Stacks on mobile. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- LEFT: Blocking Issues --}}
            <div>
                @if($blockingIssues->count() > 0)
                    <h2 class="flex items-center gap-2 text-lg font-bold text-red-700 mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        {{ __('Blocking Issues') }}
                        <span class="ml-1 bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $blockingIssues->count() }}</span>
                    </h2>
                    <div class="space-y-3">
                        @foreach($blockingIssues as $issue)
                        <div class="card border-l-4 border-red-500 bg-red-50">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-red-800">{{ $issue->issueType->name }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                            {{ $issue->status === 'OPEN' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' }}">
                                            {{ $issue->status }}
                                        </span>
                                    </div>
                                    @if($issue->description)
                                        <p class="text-sm text-gray-600 mt-1">{{ $issue->description }}</p>
                                    @endif
                                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-500 flex-wrap">
                                        @if($issue->workOrder)
                                            <span>{{ __('Work Order') }}:
                                                <a href="{{ route('admin.work-orders.show', $issue->workOrder) }}"
                                                   class="font-mono font-semibold text-blue-700 hover:underline">
                                                    {{ $issue->workOrder->order_no }}
                                                </a>
                                            </span>
                                        @endif
                                        <span>{{ __('Reported by') }}: {{ $issue->reportedBy?->name ?? '—' }}</span>
                                        <span>{{ $issue->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <a href="{{ route('admin.issues.index') }}"
                                   class="shrink-0 text-xs text-red-700 hover:underline font-medium">{{ __('View issues') }} →</a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="card flex flex-col items-center py-10 text-center text-gray-500">
                        <svg class="w-10 h-10 text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4"/>
                        </svg>
                        <p class="text-sm">{{ __('No blocking issues') }}</p>
                    </div>
                @endif
            </div>

            {{-- RIGHT: Work-order alerts (overdue + blocked stacked) --}}
            <div class="space-y-6">
                {{-- Overdue Work Orders --}}
                @if($overdueOrders->count() > 0)
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-bold text-orange-700 mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Overdue Work Orders') }}
                        <span class="ml-1 bg-orange-100 text-orange-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $overdueOrders->count() }}</span>
                    </h2>
                    <div class="overflow-hidden rounded-lg border border-orange-200 bg-white">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr class="bg-orange-50">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Order') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Line') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Overdue') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($overdueOrders as $wo)
                                <tr class="hover:bg-orange-50 cursor-pointer" onclick="window.location='{{ route('admin.work-orders.show', $wo) }}'">
                                    <td class="px-3 py-2">
                                        <span class="font-mono text-sm font-semibold text-blue-700">{{ $wo->order_no }}</span>
                                        <div class="text-[10px] text-orange-700 font-medium">{{ $wo->due_date->translatedFormat('d M Y') }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $wo->line?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-red-600 font-semibold">{{ $wo->due_date->diffForHumans() }}</td>
                                    <td class="px-3 py-2">
                                        @php
                                            $stColors = ['PENDING'=>'bg-gray-100 text-gray-700','ACCEPTED'=>'bg-blue-100 text-blue-700','IN_PROGRESS'=>'bg-yellow-100 text-yellow-700','BLOCKED'=>'bg-red-100 text-red-700','PAUSED'=>'bg-orange-100 text-orange-700','DONE'=>'bg-green-100 text-green-700','REJECTED'=>'bg-red-200 text-red-800','CANCELLED'=>'bg-gray-200 text-gray-600'];
                                            $stLabels = ['PENDING'=>__('Pending'),'ACCEPTED'=>__('Accepted'),'IN_PROGRESS'=>__('In Progress'),'BLOCKED'=>__('Blocked'),'PAUSED'=>__('Paused'),'DONE'=>__('Done'),'REJECTED'=>__('Rejected'),'CANCELLED'=>__('Cancelled')];
                                        @endphp
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $stColors[$wo->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $stLabels[$wo->status] ?? $wo->status }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Blocked Work Orders --}}
                @if($blockedOrders->count() > 0)
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-bold text-yellow-700 mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        {{ __('Blocked Work Orders') }}
                        <span class="ml-1 bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $blockedOrders->count() }}</span>
                    </h2>
                    <div class="overflow-hidden rounded-lg border border-yellow-200 bg-white">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr class="bg-yellow-50">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Order') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Line') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ __('Blocked since') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($blockedOrders as $wo)
                                <tr class="hover:bg-yellow-50 cursor-pointer" onclick="window.location='{{ route('admin.work-orders.show', $wo) }}'">
                                    <td class="px-3 py-2">
                                        <span class="font-mono text-sm font-semibold text-blue-700">{{ $wo->order_no }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $wo->line?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-500">{{ $wo->updated_at->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @if($overdueOrders->count() === 0 && $blockedOrders->count() === 0)
                    <div class="card flex flex-col items-center py-10 text-center text-gray-500">
                        <svg class="w-10 h-10 text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4"/>
                        </svg>
                        <p class="text-sm">{{ __('No work order alerts') }}</p>
                    </div>
                @endif
            </div>

        </div>

        {{-- NON-BLOCKING ISSUES (below the grid) --}}
        @if($nonBlockingIssues->count() > 0)
        <div class="mt-6">
            <h2 class="flex items-center gap-2 text-lg font-bold text-amber-700 mb-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ __('Open Issues') }} ({{ $nonBlockingIssues->count() }})
            </h2>
            <div class="card overflow-hidden p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-amber-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Issue') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Work Order') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Reported') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($nonBlockingIssues as $issue)
                        <tr class="hover:bg-amber-50/50">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">{{ $issue->title ?? $issue->description }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($issue->workOrder)
                                    <a href="{{ route('admin.work-orders.show', $issue->workOrder) }}" class="text-blue-600 hover:underline font-mono text-xs">{{ $issue->workOrder->order_no }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ $issue->issueType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $issue->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $issue->status === 'OPEN' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $issue->status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endif

</div>

<script>
function alertPoller() {
    return {
        knownTotal: {{ $total }},
        knownLatest: '{{ $blockingIssues->merge($nonBlockingIssues ?? collect())->max("created_at") ?? "" }}',
        newAlert: false,
        newAlertMsg: '',
        pollInterval: null,

        startPolling() {
            this.pollInterval = setInterval(() => this.check(), 5000);
        },

        async check() {
            try {
                const res = await fetch('{{ route("admin.alerts.check") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();

                if (data.total > this.knownTotal || (data.latest_issue_at && data.latest_issue_at > this.knownLatest)) {
                    this.newAlert = true;
                    const diff = data.total - this.knownTotal;
                    this.newAlertMsg = diff > 0
                        ? '{{ __("New alert!") }} (+' + diff + ')'
                        : '{{ __("New alert!") }}';
                    this.knownTotal = data.total;
                    this.knownLatest = data.latest_issue_at || this.knownLatest;
                    this.playAlertSound();

                    // Update badge
                    if (this.$refs.badge) {
                        this.$refs.badge.textContent = data.total;
                    }
                }
            } catch(e) {}
        },

        playAlertSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                // Urgent beep pattern: beep-beep-beep
                [0, 0.2, 0.4].forEach(delay => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    osc.type = 'square';
                    gain.gain.value = 0.3;
                    osc.start(ctx.currentTime + delay);
                    osc.stop(ctx.currentTime + delay + 0.1);
                });
            } catch(e) {}
        }
    };
}
</script>
@endsection
