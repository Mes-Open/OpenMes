@extends('layouts.app')

@section('title', 'Work Orders — Supervisor')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Work Orders</h1>
            <p class="text-gray-600 mt-1">{{ $workOrders->total() }} orders total</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('supervisor.work-orders.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label">Search order #</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-input w-full" placeholder="Order number…">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input w-full">
                    <option value="">All statuses</option>
                    @foreach(['PENDING','ACCEPTED','IN_PROGRESS','PAUSED','BLOCKED','DONE','REJECTED','CANCELLED'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ str_replace('_', ' ', $s) }}</option>
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
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">Filter</button>
            <a href="{{ route('supervisor.work-orders.index') }}" class="btn-touch btn-secondary text-sm">Clear</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Line</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($workOrders as $wo)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('supervisor.work-orders.show', $wo) }}" class="font-mono font-semibold text-blue-700 hover:underline">
                                    {{ $wo->order_no }}
                                </a>
                                @if($wo->priority > 0)
                                    <span class="ml-1 text-xs text-orange-500 font-medium">P{{ $wo->priority }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $wo->line->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $wo->productType->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @include('components.wo-status-badge', ['status' => $wo->status])
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @php $pct = $wo->planned_qty > 0 ? min(($wo->produced_qty / $wo->planned_qty) * 100, 100) : 0 @endphp
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs">{{ number_format($wo->produced_qty, 0) }}/{{ number_format($wo->planned_qty, 0) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($wo->due_date)
                                    <span class="{{ $wo->due_date->isPast() && $wo->status !== 'DONE' ? 'text-red-600 font-medium' : '' }}">
                                        {{ $wo->due_date->format('d M Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    <a href="{{ route('supervisor.work-orders.show', $wo) }}" class="text-sm text-blue-600 hover:underline">View</a>
                                    @if($wo->status === 'PENDING')
                                        <form method="POST" action="{{ route('supervisor.work-orders.accept', $wo) }}">@csrf
                                            <button class="text-sm text-green-600 hover:underline font-medium">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('supervisor.work-orders.reject', $wo) }}"
                                              onsubmit="return confirm('Reject work order {{ $wo->order_no }}?')">@csrf
                                            <button class="text-sm text-red-500 hover:underline">Reject</button>
                                        </form>
                                    @elseif($wo->status === 'ACCEPTED')
                                        <form method="POST" action="{{ route('supervisor.work-orders.reject', $wo) }}"
                                              onsubmit="return confirm('Reject work order {{ $wo->order_no }}?')">@csrf
                                            <button class="text-sm text-red-500 hover:underline">Reject</button>
                                        </form>
                                    @elseif($wo->status === 'IN_PROGRESS')
                                        <form method="POST" action="{{ route('supervisor.work-orders.pause', $wo) }}">@csrf
                                            <button class="text-sm text-yellow-600 hover:underline">Pause</button>
                                        </form>
                                    @elseif($wo->status === 'PAUSED')
                                        <form method="POST" action="{{ route('supervisor.work-orders.resume', $wo) }}">@csrf
                                            <button class="text-sm text-blue-600 hover:underline font-medium">Resume</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                No work orders found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $workOrders->links() }}
    </div>
</div>
@endsection
