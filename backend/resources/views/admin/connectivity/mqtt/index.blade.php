@extends('layouts.app')
@section('title', __('MQTT Connections'))

@section('content')
<div class="p-6 space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('MQTT Connections') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Define and manage MQTT broker connections and topic subscriptions.') }}
            </p>
        </div>
        <a href="{{ route('admin.connectivity.mqtt.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium
                  rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('New MQTT Connection') }}
        </a>
    </div>

    @if($connections->isEmpty())
        <div class="text-center py-16 text-gray-400 dark:text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
            <p class="text-sm">{{ __('No MQTT connections defined.') }}</p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Broker') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Topics') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Messages') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Last connected') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($connections as $conn)
                        @php
                            $colorMap = ['green' => 'bg-green-500', 'yellow' => 'bg-yellow-400', 'red' => 'bg-red-500', 'slate' => 'bg-slate-400'];
                            $dot = $colorMap[$conn->statusColor()] ?? 'bg-slate-400';
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $dot }} {{ $conn->status === 'connected' ? 'animate-pulse' : '' }}"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ $conn->status }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                <a href="{{ route('admin.connectivity.mqtt.show', $conn) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $conn->name }}
                                </a>
                                @if(!$conn->is_active)
                                    <span class="ml-1.5 text-xs text-gray-400 dark:text-gray-500">({{ __('inactive') }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">
                                @if($conn->mqttConnection)
                                    {{ $conn->mqttConnection->broker_host }}:{{ $conn->mqttConnection->broker_port }}
                                    @if($conn->mqttConnection->use_tls)
                                        <span class="ml-1 text-green-600 dark:text-green-400">TLS</span>
                                    @endif
                                @else
                                    <span class="text-red-400">{{ __('Not configured') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $conn->topics_count }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ number_format($conn->messages_received) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $conn->last_connected_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.connectivity.mqtt.show', $conn) }}"
                                       class="text-xs px-2 py-1 text-blue-600 dark:text-blue-400 hover:underline">{{ __('View') }}</a>
                                    <a href="{{ route('admin.connectivity.mqtt.edit', $conn) }}"
                                       class="text-xs px-2 py-1 text-gray-600 dark:text-gray-300 hover:underline">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('admin.connectivity.mqtt.destroy', $conn) }}"
                                          onsubmit="return confirm('{{ __('Delete this connection and all its topics?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs px-2 py-1 text-red-500 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
