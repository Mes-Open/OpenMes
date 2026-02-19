@extends('layouts.app')

@section('title', 'Production Anomalies')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Production Anomalies</h1>
        <a href="{{ route('admin.production-anomalies.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Record Anomaly
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <form method="GET" action="{{ route('admin.production-anomalies.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="form-label">Work Order</label>
                <select name="work_order_id" class="form-input">
                    <option value="">All work orders</option>
                    @foreach($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->order_no }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input">
                    <option value="">All statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="processed" @selected(request('status') === 'processed')>Processed</option>
                    <option value="dismissed" @selected(request('status') === 'dismissed')>Dismissed</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-touch btn-primary">Filter</button>
                <a href="{{ route('admin.production-anomalies.index') }}" class="btn-touch btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Work Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Product</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Planned Qty</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actual Qty</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Deviation</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Reason</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($anomalies as $anomaly)
                        @php
                            $deviation = $anomaly->planned_qty > 0
                                ? (($anomaly->actual_qty - $anomaly->planned_qty) / $anomaly->planned_qty) * 100
                                : 0;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono text-gray-700 font-medium">
                                {{ $anomaly->workOrder->order_no ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-gray-800">{{ $anomaly->product_name }}</td>
                            <td class="py-3 px-4 text-right text-gray-600">{{ number_format($anomaly->planned_qty, 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-600">{{ number_format($anomaly->actual_qty, 2) }}</td>
                            <td class="py-3 px-4 text-right font-medium {{ $deviation < 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ ($deviation >= 0 ? '+' : '') }}{{ number_format($deviation, 1) }}%
                            </td>
                            <td class="py-3 px-4 text-gray-600">{{ $anomaly->anomalyReason->name ?? '—' }}</td>
                            <td class="py-3 px-4">
                                @if($anomaly->status === 'pending')
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending</span>
                                @elseif($anomaly->status === 'processed')
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Processed</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Dismissed</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if($anomaly->status === 'pending')
                                        <form method="POST" action="{{ route('admin.production-anomalies.process', $anomaly) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-800 p-1" title="Process">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.production-anomalies.destroy', $anomaly) }}" class="inline"
                                          onsubmit="return confirm('Delete this anomaly record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-500">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="font-medium">No anomalies recorded</p>
                                <a href="{{ route('admin.production-anomalies.create') }}" class="inline-block mt-3 btn-touch btn-primary">Record Anomaly</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($anomalies->hasPages())
            <div class="mt-4 px-4">{{ $anomalies->links() }}</div>
        @endif
    </div>
</div>
@endsection
