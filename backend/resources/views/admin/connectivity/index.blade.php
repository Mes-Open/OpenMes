@extends('layouts.app')
@section('title', 'Machine Connectivity')

@section('content')
<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Machine Connectivity</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Overview of all machine communication channels.
            </p>
        </div>
        <a href="{{ route('admin.connectivity.mqtt.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium
                  rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Connection
        </a>
    </div>

    @if(session('success'))
        <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Protocol tabs --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('admin.connectivity.index') }}"
           class="px-4 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400">
            All ({{ $connections->count() }})
        </a>
        <a href="{{ route('admin.connectivity.mqtt.index') }}"
           class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 dark:text-gray-400
                  hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
            MQTT
        </a>
    </div>

    {{-- Connection cards --}}
    @if($connections->isEmpty())
        <div class="text-center py-16 text-gray-400 dark:text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
            <p class="text-sm">No connections defined yet.</p>
            <a href="{{ route('admin.connectivity.mqtt.create') }}" class="mt-2 inline-block text-blue-500 hover:underline text-sm">
                Create your first MQTT connection →
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($connections as $conn)
                @php
                    $color = $conn->statusColor();
                    $colorMap = [
                        'green' => 'bg-green-500',
                        'yellow' => 'bg-yellow-400',
                        'red' => 'bg-red-500',
                        'slate' => 'bg-slate-400',
                    ];
                    $dot = $colorMap[$color] ?? 'bg-slate-400';
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700
                            shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $dot }}
                                         {{ $conn->status === 'connected' ? 'animate-pulse' : '' }}"></span>
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ $conn->name }}</h3>
                        </div>
                        <span class="shrink-0 text-xs px-2 py-0.5 rounded-full font-medium uppercase tracking-wide
                                     bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            {{ $conn->protocol }}
                        </span>
                    </div>

                    @if($conn->description)
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $conn->description }}</p>
                    @endif

                    @if($conn->protocol === 'mqtt' && $conn->mqttConnection)
                        <p class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                            {{ $conn->mqttConnection->broker_host }}:{{ $conn->mqttConnection->broker_port }}
                        </p>
                    @endif

                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $conn->topics_count }} topic{{ $conn->topics_count !== 1 ? 's' : '' }}</span>
                        <span>{{ number_format($conn->messages_received) }} msg</span>
                    </div>

                    @if($conn->last_connected_at)
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Last connected: {{ $conn->last_connected_at->diffForHumans() }}
                        </p>
                    @endif

                    <div class="flex gap-2 pt-1 border-t border-gray-100 dark:border-gray-700 mt-auto">
                        @if($conn->protocol === 'mqtt')
                            <a href="{{ route('admin.connectivity.mqtt.show', $conn) }}"
                               class="flex-1 text-center text-xs px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30
                                      text-blue-700 dark:text-blue-300 rounded-md hover:bg-blue-100 transition-colors font-medium">
                                View
                            </a>
                            <a href="{{ route('admin.connectivity.mqtt.edit', $conn) }}"
                               class="flex-1 text-center text-xs px-3 py-1.5 bg-gray-50 dark:bg-gray-700
                                      text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 transition-colors font-medium">
                                Edit
                            </a>
                        @endif
                        <form method="POST" action="{{ route('admin.connectivity.mqtt.toggle-active', $conn) }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs px-3 py-1.5 rounded-md font-medium transition-colors
                                           {{ $conn->is_active
                                               ? 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100'
                                               : 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 hover:bg-green-100' }}">
                                {{ $conn->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
