@extends('layouts.app')
@section('title', $connection->name)
@section('content')
@php $o = $connection->opcuaConnection; @endphp
<div class="max-w-5xl mx-auto">
    <a href="{{ route('admin.connectivity.opcua.index') }}" class="text-blue-600 hover:text-blue-800 text-sm mb-4 inline-block">← {{ __('Back') }}</a>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $connection->name }}</h1>
            <p class="text-sm text-gray-500 font-mono break-all">{{ $o?->endpoint_url }} · {{ $o?->security_policy }} · {{ $o?->publishing_interval_ms }}ms</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-{{ $connection->statusColor() }}-100 text-{{ $connection->statusColor() }}-700">{{ $connection->status }}</span>
            <a href="{{ route('admin.connectivity.opcua.edit', $connection) }}" class="btn-touch btn-secondary text-sm">{{ __('Edit') }}</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>@endif

    {{-- Runtime status (gateway sidecar) --}}
    <div class="mb-4">
        @include('admin.connectivity._runtime-status', ['runtime' => $runtime])
    </div>

    {{-- Gateway wiring help --}}
    <div class="card mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-2">{{ __('Gateway endpoints') }}</h2>
        <p class="text-xs text-gray-500 mb-2">{{ __('The opcua-gateway sidecar uses these (with an API token):') }}</p>
        <div class="space-y-1 text-xs font-mono">
            <div><span class="text-gray-400">GET&nbsp;</span> /api/v1/machine-connections/{{ $connection->id }}/gateway-config</div>
            <div><span class="text-gray-400">POST</span> /api/v1/machine-connections/{{ $connection->id }}/signals</div>
            <div><span class="text-gray-400">POST</span> /api/v1/machine-connections/{{ $connection->id }}/heartbeat</div>
        </div>
    </div>

    {{-- Tags (OPC UA nodes) --}}
    <div class="card">
        <h2 class="text-lg font-bold text-gray-800 mb-3">{{ __('Nodes') }} ({{ $connection->tags->count() }})</h2>
        @if($connection->tags->isNotEmpty())
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead><tr class="text-left text-xs uppercase text-gray-500">
                    <th class="px-2 py-2">{{ __('Name') }}</th><th class="px-2 py-2">{{ __('Node ID') }}</th>
                    <th class="px-2 py-2">{{ __('Signal') }}</th><th class="px-2 py-2">{{ __('Type') }}</th>
                    <th class="px-2 py-2">{{ __('Workstation') }}</th><th class="px-2 py-2">{{ __('Transform') }}</th><th></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($connection->tags as $tag)
                    <tr>
                        <td class="px-2 py-2 font-medium">{{ $tag->name }}</td>
                        <td class="px-2 py-2 font-mono">{{ $tag->address }}</td>
                        <td class="px-2 py-2"><span class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-700">{{ $tag->signal_type }}</span></td>
                        <td class="px-2 py-2 text-gray-500">{{ $tag->data_type }}</td>
                        <td class="px-2 py-2">{{ $tag->workstation?->name ?? '—' }}</td>
                        <td class="px-2 py-2 text-xs text-gray-400 font-mono">{{ $tag->transform ? json_encode($tag->transform) : '—' }}</td>
                        <td class="px-2 py-2 text-right">
                            <form method="POST" action="{{ route('admin.connectivity.opcua.tags.destroy', [$connection, $tag]) }}" onsubmit="return confirm('{{ __('Remove this tag?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 hover:text-red-700 text-xs">✕</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.connectivity.opcua.tags.store', $connection) }}" class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-end border-t border-gray-100 pt-4">
            @csrf
            <div><label class="form-label text-xs">{{ __('Name') }}</label><input name="name" class="form-input w-full" required></div>
            <div><label class="form-label text-xs">{{ __('Node ID') }}</label><input name="address" class="form-input w-full" placeholder="ns=2;s=State" required></div>
            <div>
                <label class="form-label text-xs">{{ __('Data type') }}</label>
                <select name="data_type" class="form-input w-full">
                    <option value="int16">int16</option><option value="int32">int32</option>
                    <option value="float32">float32</option><option value="bool">bool</option><option value="string">string</option>
                </select>
            </div>
            <div>
                <label class="form-label text-xs">{{ __('Signal') }}</label>
                <select name="signal_type" class="form-input w-full">
                    <option value="state">state</option><option value="good_count">good_count</option>
                    <option value="reject_count">reject_count</option><option value="cycle_complete">cycle_complete</option>
                    <option value="telemetry">telemetry</option><option value="alarm">alarm</option>
                </select>
            </div>
            <div>
                <label class="form-label text-xs">{{ __('Workstation') }}</label>
                <select name="workstation_id" class="form-input w-full">
                    <option value="">—</option>
                    @foreach($workstations as $ws)<option value="{{ $ws->id }}">{{ $ws->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label text-xs">{{ __('Value map') }}</label><input name="value_map" class="form-input w-full" placeholder="1=RUNNING,2=IDLE,3=FAULT"></div>
            <div><label class="form-label text-xs">{{ __('Scale') }}</label><input name="scale" type="number" step="any" class="form-input w-full"></div>
            <div class="col-span-2 sm:col-span-4 flex justify-end">
                <button class="btn-touch btn-primary text-sm">+ {{ __('Add node') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
