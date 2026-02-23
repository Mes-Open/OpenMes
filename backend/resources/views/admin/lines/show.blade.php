@extends('layouts.app')

@section('title', 'Production Line Details')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Lines', 'url' => route('admin.lines.index')],
    ['label' => $line->name, 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.lines.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Production Lines
        </a>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800">{{ $line->name }}</h1>
                @if($line->is_active)
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.lines.edit', $line) }}" class="btn-touch btn-secondary">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Line
                </a>
                <form method="POST" action="{{ route('admin.lines.toggle-active', $line) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn-touch {{ $line->is_active ? 'btn-secondary' : 'btn-primary' }}">
                        @if($line->is_active)
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Deactivate
                        @else
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Activate
                        @endif
                    </button>
                </form>
            </div>
        </div>
        <p class="text-sm text-gray-500 font-mono mt-1">{{ $line->code }}</p>
        @if($line->description)
            <p class="text-gray-600 mt-2">{{ $line->description }}</p>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Work Orders</p>
                    <p class="text-3xl font-bold text-blue-600">{{ $line->workOrders->count() }}</p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.lines.workstations.index', $line) }}" class="card hover:shadow-lg transition-shadow block">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Workstations</p>
                    <p class="text-3xl font-bold text-green-600">{{ $line->workstations->count() }}</p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
        </a>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Assigned Operators</p>
                    <p class="text-3xl font-bold text-purple-600">{{ $line->users->count() }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Line Statuses -->
    <div class="card mb-6" x-data="{}">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Line Statuses</h2>
                <p class="text-sm text-gray-500 mt-0.5">Kanban statuses available for work orders on this line. Global statuses are shown in gray.</p>
            </div>
            <a href="{{ route('admin.line-statuses.index') }}" class="text-sm text-blue-600 hover:underline">Manage global statuses →</a>
        </div>

        @if(session('success'))
            <div class="mb-3 p-2 bg-green-50 border border-green-200 rounded text-green-700 text-sm">{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap gap-2 mb-4">
            @forelse($lineStatuses as $status)
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white"
                     style="background-color: {{ $status->color }}">
                    {{ $status->name }}
                    @if($status->is_default)
                        <span class="text-xs opacity-75">(default)</span>
                    @endif
                    @if($status->line_id === null)
                        <span class="text-xs opacity-60">global</span>
                    @else
                        <form method="POST" action="{{ route('admin.line-statuses.destroy', $status) }}"
                              onsubmit="return confirm('Delete status \'{{ $status->name }}\'?')"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ml-1 opacity-75 hover:opacity-100" title="Delete">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">No statuses yet. Add one below or <a href="{{ route('admin.line-statuses.index') }}" class="text-blue-600 hover:underline">manage global statuses</a>.</p>
            @endforelse
        </div>

        <!-- Add line-specific status -->
        <form method="POST" action="{{ route('admin.lines.statuses.store', $line) }}"
              class="border-t border-gray-100 pt-4 flex items-end gap-3 flex-wrap">
            @csrf
            <div class="flex flex-col gap-1">
                <label class="form-label text-xs">Color</label>
                <input type="color" name="color" value="#F59E0B"
                       class="w-10 h-10 rounded cursor-pointer border border-gray-300 p-0.5" required>
            </div>
            <div class="flex flex-col gap-1 flex-1 min-w-[160px]">
                <label class="form-label text-xs">Status name (line-specific)</label>
                <input type="text" name="name" placeholder="e.g. Waiting for parts"
                       class="form-input py-1.5 text-sm" maxlength="100" required>
            </div>
            <div class="flex flex-col gap-1 w-20">
                <label class="form-label text-xs">Order</label>
                <input type="number" name="sort_order" value="10" min="0"
                       class="form-input py-1.5 text-sm">
            </div>
            <button type="submit" class="btn-touch btn-secondary py-1.5 text-sm">
                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add to this line
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assigned Operators -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Assigned Operators</h2>
            </div>

            @if($line->users->count() > 0)
                <div class="space-y-2 mb-4">
                    @foreach($line->users as $user)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $user->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $user->username }}</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.lines.unassign-operator', [$line, $user]) }}" class="inline" onsubmit="return confirm('Remove {{ $user->name }} from this line?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 p-2" title="Remove operator">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg mb-4">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="text-gray-600">No operators assigned yet</p>
                </div>
            @endif

            <!-- Assign Operator Form -->
            @if($availableOperators->count() > 0)
                <form method="POST" action="{{ route('admin.lines.assign-operator', $line) }}" class="border-t border-gray-200 pt-4">
                    @csrf
                    <label for="user_id" class="form-label">Assign New Operator</label>
                    <div class="flex gap-2">
                        <select name="user_id" id="user_id" class="form-input flex-1" required>
                            <option value="">Select an operator...</option>
                            @foreach($availableOperators as $operator)
                                <option value="{{ $operator->id }}">{{ $operator->name }} ({{ $operator->username }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-touch btn-primary">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Assign
                        </button>
                    </div>
                </form>
            @else
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-500 text-center">All available operators are already assigned to this line.</p>
                </div>
            @endif
        </div>

        <!-- Recent Work Orders -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Work Orders</h2>

            @if($workOrders->count() > 0)
                <div class="space-y-2">
                    @foreach($workOrders as $workOrder)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800">{{ $workOrder->work_order_number }}</p>
                                    <p class="text-sm text-gray-600">{{ $workOrder->product_name }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Quantity: {{ $workOrder->quantity }} | Created: {{ $workOrder->created_at->format('Y-m-d H:i') }}
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($workOrder->status === 'PENDING') bg-yellow-100 text-yellow-800
                                    @elseif($workOrder->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                                    @elseif($workOrder->status === 'COMPLETED') bg-green-100 text-green-800
                                    @elseif($workOrder->status === 'BLOCKED') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $workOrder->status }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($line->workOrders->count() > 10)
                    <p class="text-sm text-gray-500 text-center mt-4">
                        Showing 10 most recent of {{ $line->workOrders->count() }} total work orders
                    </p>
                @endif
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <p class="text-gray-600">No work orders yet</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
