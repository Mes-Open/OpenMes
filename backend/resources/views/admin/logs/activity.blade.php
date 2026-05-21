@extends('layouts.app')

@section('title', __('Activity Logs'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Activity Logs'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Activity Logs') }}</h1>
        <p class="text-gray-600 mt-1">{{ __('What users did across the system — entity changes, navigation, auth events.') }}</p>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.logs.activity') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
                <label for="from" class="form-label text-xs">{{ __('From') }}</label>
                <input id="from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-input w-full">
            </div>
            <div>
                <label for="to" class="form-label text-xs">{{ __('To') }}</label>
                <input id="to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-input w-full">
            </div>
            <div>
                <label for="user_id" class="form-label text-xs">{{ __('User') }}</label>
                <select id="user_id" name="user_id" class="form-input w-full">
                    <option value="">{{ __('All users') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source" class="form-label text-xs">{{ __('Source') }}</label>
                <select id="source" name="source" class="form-input w-full">
                    <option value="">{{ __('All sources') }}</option>
                    <option value="audit" @selected(request('source') === 'audit')>{{ __('Entity changes') }}</option>
                    <option value="request" @selected(request('source') === 'request')>{{ __('Navigation') }}</option>
                </select>
            </div>
            <div>
                <label for="entity_type" class="form-label text-xs">{{ __('Entity') }}</label>
                <select id="entity_type" name="entity_type" class="form-input w-full">
                    <option value="">{{ __('All entities') }}</option>
                    @foreach($entityTypes as $et)
                        <option value="{{ $et }}" @selected(request('entity_type') === $et)>{{ class_basename($et) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action" class="form-label text-xs">{{ __('Action') }}</label>
                <select id="action" name="action" class="form-input w-full">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Apply') }}</button>
            <a href="{{ route('admin.logs.activity') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
            <a href="{{ route('admin.logs.activity.export', request()->query()) }}"
               class="btn-touch btn-secondary text-sm sm:ml-auto">{{ __('Export CSV') }}</a>
        </div>
    </form>

    {{-- Timeline --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2">{{ __('When') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Who') }}</th>
                        <th class="text-left px-4 py-2">{{ __('What') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                {{ $log->created_at?->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 whitespace-nowrap">
                                {{ $log->user?->name ?? __('Guest') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($log->source === 'audit')
                                    @php
                                        $actionColors = [
                                            'created'      => 'bg-green-100 text-green-700',
                                            'updated'      => 'bg-blue-100 text-blue-700',
                                            'deleted'      => 'bg-red-100 text-red-700',
                                            'login'        => 'bg-purple-100 text-purple-700',
                                            'logout'       => 'bg-gray-100 text-gray-600',
                                            'login_failed' => 'bg-red-100 text-red-700',
                                        ];
                                        $badge = $actionColors[$log->action] ?? 'bg-gray-100 text-gray-600';
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                    <span class="text-gray-700 ml-1">
                                        {{ class_basename($log->entity_type ?? '') }}@if($log->entity_id) #{{ $log->entity_id }}@endif
                                    </span>
                                @else
                                    @php
                                        $methodBadge = match($log->method) {
                                            'GET'    => 'bg-gray-100 text-gray-600',
                                            'POST'   => 'bg-green-100 text-green-700',
                                            'PUT', 'PATCH' => 'bg-blue-100 text-blue-700',
                                            'DELETE' => 'bg-red-100 text-red-700',
                                            default  => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="font-mono text-xs px-2 py-0.5 rounded {{ $methodBadge }}">{{ $log->method }}</span>
                                    <span class="text-gray-700 text-xs font-mono break-all">{{ $log->path }}</span>
                                    <span class="text-xs text-gray-400 whitespace-nowrap">→ {{ $log->status }} • {{ $log->duration_ms }}ms</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                @if($log->source === 'audit' && in_array($log->action, ['updated', 'created']))
                                    <a href="{{ route('admin.audit-logs', ['user_id' => $log->user_id, 'entity_type' => $log->entity_type]) }}"
                                       class="text-blue-600 hover:underline">{{ __('View changes') }}</a>
                                @endif
                                <div class="text-gray-400 mt-1">{{ $log->ip_address }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center text-gray-400">
                                {{ __('No activity in this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($logs, 'links') && $logs->hasPages())
            <div class="p-3 border-t">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
