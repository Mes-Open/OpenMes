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
                                <button type="button"
                                        @click="$dispatch('open-detail', { log: {{ Js::from($log->toArray() + ['source' => $log->source, 'user' => $log->user ? ['name' => $log->user->name, 'id' => $log->user->id] : null]) }} })"
                                        class="text-blue-600 hover:underline text-xs">{{ __('Details') }}</button>
                                @if($log->source === 'audit' && in_array($log->action, ['updated', 'created']))
                                    <span class="text-gray-300 mx-1">|</span>
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

{{-- Log entry detail modal --}}
<div x-data="logDetailModal()"
     @open-detail.window="open($event.detail.log)"
     x-show="visible"
     @click.self="close()"
     @keydown.escape.window="close()"
     x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="log-detail-title"
     tabindex="-1">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto"
         @click.stop>
        <div class="flex items-center justify-between p-4 border-b">
            <h3 id="log-detail-title" class="text-lg font-semibold">{{ __('Log entry details') }}</h3>
            <button type="button"
                    @click="close()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none"
                    aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="p-4 space-y-3 text-sm">
            <div>
                <strong class="text-gray-700">{{ __('Timestamp') }}:</strong>
                <span class="font-mono text-xs" x-text="formatTimestamp(data?.created_at)"></span>
            </div>
            <div>
                <strong class="text-gray-700">{{ __('Source') }}:</strong>
                <span class="px-2 py-0.5 rounded text-xs uppercase"
                      :class="data?.source === 'audit' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'"
                      x-text="data?.source || '—'"></span>
            </div>
            <div>
                <strong class="text-gray-700">{{ __('User') }}:</strong>
                <span x-text="(data?.user && data.user.name) ? data.user.name : '{{ __('Guest') }}'"></span>
            </div>
            <div>
                <strong class="text-gray-700">{{ __('IP address') }}:</strong>
                <span class="font-mono text-xs" x-text="data?.ip_address || '—'"></span>
            </div>

            {{-- Audit row: action + entity + before/after state --}}
            <template x-if="data?.source === 'audit'">
                <div class="space-y-2 border-t pt-3">
                    <div>
                        <strong class="text-gray-700">{{ __('Action') }}:</strong>
                        <span x-text="data?.action || '—'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Entity') }}:</strong>
                        <span x-text="entityLabel(data)"></span>
                    </div>
                    <template x-if="data?.before_state">
                        <details class="mt-2">
                            <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">{{ __('Before state') }}</summary>
                            <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words"
                                 x-text="prettyJson(data?.before_state)"></pre>
                        </details>
                    </template>
                    <template x-if="data?.after_state">
                        <details class="mt-2" open>
                            <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">{{ __('After state') }}</summary>
                            <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words"
                                 x-text="prettyJson(data?.after_state)"></pre>
                        </details>
                    </template>
                </div>
            </template>

            {{-- Request row: method, path, status, duration --}}
            <template x-if="data?.source === 'request'">
                <div class="space-y-2 border-t pt-3">
                    <div>
                        <strong class="text-gray-700">{{ __('Method') }}:</strong>
                        <span class="font-mono px-2 py-0.5 rounded bg-gray-100 text-xs" x-text="data?.method || '—'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Path') }}:</strong>
                        <span class="font-mono text-xs break-all" x-text="data?.path || '—'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Route name') }}:</strong>
                        <span class="font-mono text-xs" x-text="data?.route_name || '—'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Status') }}:</strong>
                        <span x-text="data?.status ?? '—'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Duration') }}:</strong>
                        <span x-text="(data?.duration_ms ?? '—') + ' ms'"></span>
                    </div>
                    <div>
                        <strong class="text-gray-700">{{ __('Sampled') }}:</strong>
                        <span x-text="data?.sampled ? '{{ __('yes') }}' : '{{ __('no') }}'"></span>
                    </div>
                </div>
            </template>

            <div class="text-xs text-gray-400 pt-3 border-t break-words">
                <strong>{{ __('User agent') }}:</strong>
                <span x-text="data?.user_agent || '—'"></span>
            </div>
        </div>
        <div class="flex justify-end p-3 border-t bg-gray-50">
            <button type="button"
                    @click="close()"
                    class="btn-touch btn-secondary text-sm">{{ __('Close') }}</button>
        </div>
    </div>
</div>

<script>
    function logDetailModal() {
        return {
            visible: false,
            data: null,
            open(log) {
                this.data = log || null;
                this.visible = true;
                // Lock body scroll while modal is open
                document.body.style.overflow = 'hidden';
            },
            close() {
                this.visible = false;
                this.data = null;
                document.body.style.overflow = '';
            },
            formatTimestamp(v) {
                if (!v) return '—';
                // Carbon serializes as ISO 8601; show original for clarity
                return String(v).replace('T', ' ').replace(/\.\d+Z?$/, '');
            },
            entityLabel(d) {
                if (!d) return '—';
                const type = d.entity_type ? String(d.entity_type).split('\\').pop() : '—';
                return d.entity_id ? `${type} #${d.entity_id}` : type;
            },
            prettyJson(v) {
                if (v === null || v === undefined) return '';
                try {
                    return JSON.stringify(v, null, 2);
                } catch (e) {
                    return String(v);
                }
            },
        };
    }
</script>
@endsection
