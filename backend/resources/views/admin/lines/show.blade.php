@extends('layouts.app')

@section('title', __('Production Line Details'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Lines'), 'url' => route('admin.lines.index')],
    ['label' => $line->name, 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.lines.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back to Production Lines') }}
        </a>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800">{{ $line->name }}</h1>
                @if($line->is_active)
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">{{ __('Active') }}</span>
                @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">{{ __('Inactive') }}</span>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.lines.edit', $line) }}" class="btn-touch btn-secondary">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('Edit Line') }}
                </a>
                <form method="POST" action="{{ route('admin.lines.toggle-active', $line) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn-touch {{ $line->is_active ? 'btn-secondary' : 'btn-primary' }}">
                        @if($line->is_active)
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            {{ __('Deactivate') }}
                        @else
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('Activate') }}
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
                    <p class="text-sm text-gray-600">{{ __('Work Orders') }}</p>
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
                    <p class="text-sm text-gray-600">{{ __('Workstations') }}</p>
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
                    <p class="text-sm text-gray-600">{{ __('Assigned Operators') }}</p>
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
                <h2 class="text-xl font-bold text-gray-800">{{ __('Line Statuses') }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Kanban statuses available for work orders on this line. Global statuses are shown in gray.') }}</p>
            </div>
            <a href="{{ route('admin.line-statuses.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Manage global statuses') }} →</a>
        </div>

            <div class="flex flex-wrap gap-2 mb-4">
            @forelse($lineStatuses as $status)
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white"
                     style="background-color: {{ $status->color }}">
                    {{ $status->name }}
                    @if($status->is_default)
                        <span class="text-xs opacity-75">{{ __('(default)') }}</span>
                    @endif
                    @if($status->line_id === null)
                        <span class="text-xs opacity-60">{{ __('global') }}</span>
                    @else
                        <form method="POST" action="{{ route('admin.line-statuses.destroy', $status) }}"
                              onsubmit="return confirm('{{ __('Delete status') }} \'{{ $status->name }}\'?')"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ml-1 opacity-75 hover:opacity-100" title="{{ __('Delete') }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No statuses yet. Add one below or') }} <a href="{{ route('admin.line-statuses.index') }}" class="text-blue-600 hover:underline">{{ __('manage global statuses') }}</a>.</p>
            @endforelse
        </div>

        <!-- Add line-specific status -->
        <form method="POST" action="{{ route('admin.lines.statuses.store', $line) }}"
              class="border-t border-gray-100 pt-4 flex items-end gap-3 flex-wrap">
            @csrf
            <div class="flex flex-col gap-1">
                <label class="form-label text-xs">{{ __('Color') }}</label>
                <input type="color" name="color" value="#F59E0B"
                       class="w-10 h-10 rounded cursor-pointer border border-gray-300 p-0.5" required>
            </div>
            <div class="flex flex-col gap-1 flex-1 min-w-[160px]">
                <label class="form-label text-xs">{{ __('Status name (line-specific)') }}</label>
                <input type="text" name="name" placeholder="e.g. Waiting for parts"
                       class="form-input py-1.5 text-sm" maxlength="100" required>
            </div>
            <div class="flex flex-col gap-1 w-20">
                <label class="form-label text-xs">{{ __('Order') }}</label>
                <input type="number" name="sort_order" value="10" min="0"
                       class="form-input py-1.5 text-sm">
            </div>
            <button type="submit" class="btn-touch btn-secondary py-1.5 text-sm">
                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add to this line') }}
            </button>
        </form>
    </div>

    {{-- ── Product Types ── --}}
    <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">{{ __('Assigned Product Types') }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Product types that can be produced on this line.') }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            @forelse($line->productTypes as $pt)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 border border-blue-200 text-blue-800 text-sm font-medium rounded-lg">
                    <span class="font-mono text-xs text-blue-500">{{ $pt->code }}</span>
                    {{ $pt->name }}
                </span>
            @empty
                <p class="text-sm text-gray-400">{{ __('No product types assigned — all types are allowed.') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.lines.product-types.sync', $line) }}"
              class="border-t border-gray-100 pt-4"
              x-data="{ open: false }">
            @csrf
            <button type="button" @click="open = !open"
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span x-text="open ? '{{ __('Hide selector') }}' : '{{ __('Change assignment') }}'"></span>
            </button>

            <div x-show="open" x-cloak class="mt-3">
                @if($allProductTypes->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No active product types defined yet.') }}</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-3">
                        @foreach($allProductTypes as $pt)
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors
                                {{ in_array($pt->id, $assignedTypeIds) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="checkbox" name="product_type_ids[]" value="{{ $pt->id }}"
                                       class="rounded border-gray-300 text-blue-600"
                                       {{ in_array($pt->id, $assignedTypeIds) ? 'checked' : '' }}>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $pt->name }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $pt->code }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mb-3">{{ __('Leave all unchecked to allow all product types on this line.') }}</p>
                    <button type="submit" class="btn-touch btn-primary py-1.5 text-sm">{{ __('Save Assignment') }}</button>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Workstations ── --}}
    <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">{{ __('Workstations') }}</h2>
                @if($line->workstations->isEmpty())
                    <p class="text-sm text-amber-600 mt-0.5 font-medium">
                        {{ __('No workstations configured — line itself acts as a single workstation.') }}
                    </p>
                @else
                    <p class="text-sm text-gray-500 mt-0.5">{{ $line->workstations->count() }} {{ __('workstation(s) on this line.') }}</p>
                @endif
            </div>
            <a href="{{ route('admin.lines.workstations.index', $line) }}" class="btn-touch btn-secondary text-sm">
                {{ __('Manage') }}
            </a>
        </div>

        @php $effective = $line->effectiveWorkstations(); @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($effective as $ws)
                <div class="flex items-center gap-3 p-3 rounded-lg border
                    {{ $ws->is_line_itself ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' }}">
                    <div class="{{ $ws->is_line_itself ? 'bg-amber-100' : 'bg-green-100' }} rounded-full p-2 flex-shrink-0">
                        <svg class="w-5 h-5 {{ $ws->is_line_itself ? 'text-amber-600' : 'text-green-600' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $ws->name }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $ws->code }}</p>
                        @if($ws->is_line_itself)
                            <p class="text-xs text-amber-600">{{ __('virtual (line = workstation)') }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assigned Operators -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">{{ __('Assigned Operators') }}</h2>
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
                            <form method="POST" action="{{ route('admin.lines.unassign-operator', [$line, $user]) }}" class="inline" onsubmit="return confirm('{{ __('Remove') }} {{ $user->name }} {{ __('from this line?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 p-2" title="{{ __('Remove operator') }}">
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
                    <p class="text-gray-600">{{ __('No operators assigned yet') }}</p>
                </div>
            @endif

            <!-- Assign Operator Form -->
            @if($availableOperators->count() > 0)
                <form method="POST" action="{{ route('admin.lines.assign-operator', $line) }}" class="border-t border-gray-200 pt-4">
                    @csrf
                    <label for="user_id" class="form-label">{{ __('Assign New Operator') }}</label>
                    <div class="flex gap-2">
                        <select name="user_id" id="user_id" class="form-input flex-1" required>
                            <option value="">{{ __('Select an operator...') }}</option>
                            @foreach($availableOperators as $operator)
                                <option value="{{ $operator->id }}">{{ $operator->name }} ({{ $operator->username }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-touch btn-primary">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ __('Assign') }}
                        </button>
                    </div>
                </form>
            @else
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-500 text-center">{{ __('All available operators are already assigned to this line.') }}</p>
                </div>
            @endif
        </div>

        <!-- Recent Work Orders -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Recent Work Orders') }}</h2>

            @if($workOrders->count() > 0)
                <div class="space-y-2">
                    @foreach($workOrders as $workOrder)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800">{{ $workOrder->work_order_number }}</p>
                                    <p class="text-sm text-gray-600">{{ $workOrder->product_name }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('Quantity') }}: {{ $workOrder->planned_qty }} | {{ __('Created') }}: {{ $workOrder->created_at->format('Y-m-d H:i') }}
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
                        {{ __('Showing 10 most recent of') }} {{ $line->workOrders->count() }} {{ __('total work orders') }}
                    </p>
                @endif
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <p class="text-gray-600">{{ __('No work orders yet') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════ DEFAULT OPERATOR VIEW ══════════════════ --}}
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-1">{{ __('Default Operator View') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Choose which view operators see by default when they select this line.') }}
        </p>
        <form method="POST" action="{{ route('admin.lines.default-view.set', $line) }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="form-label">{{ __('Default View') }}</label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors
                                  {{ ($line->default_operator_view ?? 'queue') === 'queue' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300' }}">
                        <input type="radio" name="default_operator_view" value="queue"
                               {{ ($line->default_operator_view ?? 'queue') === 'queue' ? 'checked' : '' }}
                               onchange="this.form.submit()"
                               class="text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Queue') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Standard work order list with status, batches, priority and actions.') }}</p>
                        </div>
                    </label>
                    <label class="flex-1 flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors
                                  {{ ($line->default_operator_view ?? 'queue') === 'workstation' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300' }}">
                        <input type="radio" name="default_operator_view" value="workstation"
                               {{ ($line->default_operator_view ?? 'queue') === 'workstation' ? 'checked' : '' }}
                               onchange="this.form.submit()"
                               class="text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Workstation') }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Flat production table with quantities, Z1/Z2 shift inputs and inline entry.') }}</p>
                        </div>
                    </label>
                </div>
            </div>
        </form>
    </div>

    {{-- ══════════════════ WORKSTATION VIEW TEMPLATE ══════════════════ --}}
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-1">{{ __('Workstation View') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Select a view template that defines which columns operators see in the Workstation view for this line.') }}
        </p>
        <form method="POST" action="{{ route('admin.lines.view-template.assign', $line) }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="form-label">{{ __('View Template') }}</label>
                <select name="view_template_id" class="form-input w-full">
                    <option value="">— {{ __('Default (no custom columns)') }} —</option>
                    @foreach($allViewTemplates as $tpl)
                        <option value="{{ $tpl->id }}" {{ $line->view_template_id == $tpl->id ? 'selected' : '' }}>
                            {{ $tpl->name }}
                            ({{ count($tpl->columns) }} columns)
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Save') }}</button>
        </form>
        @if($allViewTemplates->isEmpty())
            <p class="text-xs text-gray-400 mt-3">
                {{ __('No templates created yet.') }}
                <a href="{{ route('admin.view-templates.create') }}" class="text-blue-600 hover:underline">{{ __('Create one') }}</a>.
            </p>
        @endif
    </div>

    {{-- ══════════════════ CUSTOM VIEW COLUMNS (fallback) ══════════════════ --}}
    <div class="card mt-4" x-data="{
        columns: @json($viewColumns->map(fn($c) => ['label' => $c->label, 'key' => $c->key, 'source' => $c->source])->values()),
        newLabel: '',
        newKey: '',
        newSource: 'extra_data',
        add() {
            if (!this.newLabel || !this.newKey) return;
            this.columns.push({ label: this.newLabel, key: this.newKey, source: this.newSource });
            this.newLabel = '';
            this.newKey = '';
            this.newSource = 'extra_data';
        },
        remove(i) { this.columns.splice(i, 1); },
        moveUp(i) { if (i > 0) { [this.columns[i-1], this.columns[i]] = [this.columns[i], this.columns[i-1]]; } },
        moveDown(i) { if (i < this.columns.length - 1) { [this.columns[i], this.columns[i+1]] = [this.columns[i+1], this.columns[i]]; } }
    }">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-1">{{ __('Workstation View Columns') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Configure which columns operators see in the Workstation view for this line.') }}
            {{ __('Columns with source') }} <strong>extra_data</strong> {{ __('pull values from the work order\'s imported data.') }}
            {{ __('Columns with source') }} <strong>field</strong> {{ __('pull from work order fields (order_no, description, due_date, priority).') }}
        </p>

        <form method="POST" action="{{ route('admin.lines.view-columns.save', $line) }}">
            @csrf

            {{-- Existing columns --}}
            <div class="space-y-2 mb-4">
                <template x-for="(col, i) in columns" :key="i">
                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">
                        <span class="text-gray-400 text-sm font-mono w-6 text-center" x-text="i + 1"></span>
                        <input type="hidden" :name="'columns[' + i + '][label]'" :value="col.label">
                        <input type="hidden" :name="'columns[' + i + '][key]'" :value="col.key">
                        <input type="hidden" :name="'columns[' + i + '][source]'" :value="col.source">
                        <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-200" x-text="col.label"></span>
                        <code class="text-xs text-gray-500 bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded" x-text="col.source + '.' + col.key"></code>
                        <button type="button" @click="moveUp(i)" class="p-1 text-gray-400 hover:text-gray-700" title="{{ __('Move up') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button type="button" @click="moveDown(i)" class="p-1 text-gray-400 hover:text-gray-700" title="{{ __('Move down') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <button type="button" @click="remove(i)" class="p-1 text-red-400 hover:text-red-600" title="{{ __('Remove') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                <div x-show="columns.length === 0" class="text-sm text-gray-400 text-center py-4">
                    {{ __('No custom columns configured. Default view will be shown.') }}
                </div>
            </div>

            {{-- Add new column --}}
            <div class="flex flex-wrap gap-2 items-end border-t border-gray-200 dark:border-gray-700 pt-4 mb-4">
                <div class="flex-1 min-w-[140px]">
                    <label class="text-xs text-gray-500 block mb-1">{{ __('Column Label') }}</label>
                    <input type="text" x-model="newLabel" placeholder="e.g. Material" class="form-input w-full text-sm">
                </div>
                <div class="flex-1 min-w-[140px]">
                    <label class="text-xs text-gray-500 block mb-1">{{ __('Data Key') }}</label>
                    <input type="text" x-model="newKey" placeholder="e.g. material" class="form-input w-full text-sm">
                </div>
                <div class="w-36">
                    <label class="text-xs text-gray-500 block mb-1">{{ __('Source') }}</label>
                    <select x-model="newSource" class="form-input w-full text-sm">
                        <option value="extra_data">extra_data</option>
                        <option value="field">field</option>
                    </select>
                </div>
                <button type="button" @click="add()"
                        class="btn-touch btn-secondary text-sm"
                        :disabled="!newLabel || !newKey">
                    {{ __('+ Add') }}
                </button>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Save View Columns') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
