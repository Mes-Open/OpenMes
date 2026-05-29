@extends('layouts.app')
@section('title', __('OPC UA Connections'))
@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Connectivity'), 'url' => route('admin.connectivity.index')],
    ['label' => __('OPC UA'), 'url' => null],
]" />
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('OPC UA Connections') }}</h1>
        <a href="{{ route('admin.connectivity.opcua.create') }}" class="btn-touch btn-primary">+ {{ __('New Connection') }}</a>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 mb-6 text-sm text-blue-800">
        <p class="font-semibold mb-1">{{ __('OPC UA requires the gateway sidecar') }}</p>
        <p>{{ __('OpenMES does not speak OPC UA directly. A separate gateway service (opcua-gateway) connects to your OPC UA server and forwards readings. Each connection page shows whether its gateway is running.') }}</p>
    </div>

    @if($connections->isEmpty())
        <div class="card text-center py-16"><p class="text-gray-500">{{ __('No OPC UA connections defined yet.') }}</p></div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($connections as $c)
                <a href="{{ route('admin.connectivity.opcua.show', $c) }}" class="card hover:shadow-lg transition">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-gray-800">{{ $c->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-{{ $c->statusColor() }}-100 text-{{ $c->statusColor() }}-700">{{ $c->status }}</span>
                    </div>
                    <p class="text-sm text-gray-500 font-mono break-all">{{ $c->opcuaConnection?->endpoint_url }}</p>
                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                        <span>{{ $c->tags->count() }} {{ __('tags') }}</span>
                        <span>{{ $c->opcuaConnection?->security_policy }}</span>
                        <span>{{ $c->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
