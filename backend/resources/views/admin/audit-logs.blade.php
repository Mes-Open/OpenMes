@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Audit Logs', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Audit Logs</h1>
        <p class="text-gray-600 mt-2">Track all system changes and user activities</p>
    </div>

    <!-- Filters -->
    <div class="card mb-6" x-data="{ showFilters: false }">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-800">Filters</h2>
            <button @click="showFilters = !showFilters" class="btn-touch btn-secondary text-sm">
                <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'"></span>
            </button>
        </div>

        <form method="GET" action="{{ route('admin.audit-logs') }}" x-show="showFilters" x-transition>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Entity Type -->
                <div>
                    <label for="entity_type" class="form-label">Entity Type</label>
                    <select id="entity_type" name="entity_type" class="form-input w-full">
                        <option value="">All Types</option>
                        @foreach($entityTypes as $type)
                            <option value="{{ $type }}" {{ request('entity_type') === $type ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- User -->
                <div>
                    <label for="user_id" class="form-label">User</label>
                    <select id="user_id" name="user_id" class="form-input w-full">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->username }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Action -->
                <div>
                    <label for="action" class="form-label">Action</label>
                    <select id="action" name="action" class="form-input w-full">
                        <option value="">All Actions</option>
                        <option value="created" {{ request('action') === 'created' ? 'selected' : '' }}>Created</option>
                        <option value="updated" {{ request('action') === 'updated' ? 'selected' : '' }}>Updated</option>
                        <option value="deleted" {{ request('action') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label for="start_date" class="form-label">Start Date</label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="{{ request('start_date') }}"
                        class="form-input w-full"
                    >
                </div>

                <div>
                    <label for="end_date" class="form-label">End Date</label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="{{ request('end_date') }}"
                        class="form-input w-full"
                    >
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-touch btn-primary">
                    Apply Filters
                </button>
                <a href="{{ route('admin.audit-logs') }}" class="btn-touch btn-secondary">
                    Clear Filters
                </a>
                <a href="{{ route('admin.audit-logs.export') }}?{{ http_build_query(request()->all()) }}" class="btn-touch btn-secondary ml-auto">
                    Export to CSV
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="card">
        @if($auditLogs->isEmpty())
            <div class="text-center py-12 text-gray-500">No audit logs found</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($auditLogs as $log)
                            <tr class="hover:bg-gray-50" x-data="{ expanded: false }">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->user ? $log->user->name : 'System' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($log->action === 'created') bg-green-100 text-green-800
                                        @elseif($log->action === 'updated') bg-blue-100 text-blue-800
                                        @elseif($log->action === 'deleted') bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($log->action === 'updated' && $log->after_state)
                                        <button @click="expanded = !expanded" class="text-blue-600 hover:text-blue-800">
                                            <span x-text="expanded ? 'Hide' : 'View'"></span> Changes
                                        </button>
                                        <div x-show="expanded" x-transition class="mt-2 p-3 bg-gray-50 rounded text-xs">
                                            @foreach($log->after_state as $field => $newValue)
                                                <div class="mb-1">
                                                    <strong>{{ $field }}:</strong>
                                                    {{ $log->before_state[$field] ?? 'null' }} → {{ $newValue }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($log->action === 'created')
                                        <span class="text-gray-500">Created with {{ count($log->after_state ?? []) }} fields</span>
                                    @elseif($log->action === 'deleted')
                                        <span class="text-gray-500">Record deleted</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
