@extends('layouts.app')

@section('title', __('Modbus Connections'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Connectivity'), 'url' => route('admin.connectivity.index')],
    ['label' => __('Modbus'), 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Modbus Connections') }}</h1>
        <a href="{{ route('admin.connectivity.modbus.create') }}" class="btn-touch btn-primary">+ {{ __('New Connection') }}</a>
    </div>

    @if($connections->isEmpty())
        <div class="card text-center py-16">
            <p class="text-gray-500">{{ __('No Modbus connections defined yet.') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($connections as $c)
                <a href="{{ route('admin.connectivity.modbus.show', $c) }}" class="card hover:shadow-lg transition">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-gray-800">{{ $c->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-{{ $c->statusColor() }}-100 text-{{ $c->statusColor() }}-700">{{ $c->status }}</span>
                    </div>
                    <p class="text-sm text-gray-500 font-mono">{{ $c->modbusConnection?->host }}:{{ $c->modbusConnection?->port }} (unit {{ $c->modbusConnection?->unit_id }})</p>
                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                        <span>{{ $c->tags->count() }} {{ __('tags') }}</span>
                        <span>{{ number_format($c->messages_received) }} {{ __('reads') }}</span>
                        <span>{{ $c->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
