@extends('layouts.app')

@section('title', __('Machine Monitor'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Machine Monitor'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto" x-data="machineMonitor()" x-init="init()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Machine Monitor') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Live workstation states from connected machines.') }}</p>
        </div>
        <span class="flex items-center gap-2 text-sm text-gray-500">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> {{ __('Live') }}
        </span>
    </div>

    <template x-if="tiles.length === 0">
        <div class="card text-center py-16">
            <p class="text-gray-500">{{ __('No workstations are wired to a machine connection yet.') }}</p>
            <a href="{{ route('admin.connectivity.modbus.index') }}" class="btn-touch btn-primary inline-block mt-4">{{ __('Configure Modbus') }}</a>
        </div>
    </template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="t in tiles" :key="t.id">
            <div class="card border-l-4" :class="borderClass(t.color)">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="font-bold text-gray-800" x-text="t.name"></h3>
                        <p class="text-xs text-gray-500" x-text="t.line"></p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase" :class="badgeClass(t.color)" x-text="t.state"></span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center mt-3">
                    <div>
                        <p class="text-[10px] uppercase text-gray-400">{{ __('Availability') }}</p>
                        <p class="text-lg font-bold text-gray-800"><span x-text="t.availability ?? '—'"></span><span x-show="t.availability !== null">%</span></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase text-gray-400">{{ __('Good') }}</p>
                        <p class="text-lg font-bold text-green-600" x-text="t.good"></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase text-gray-400">{{ __('Reject') }}</p>
                        <p class="text-lg font-bold text-red-500" x-text="t.reject"></p>
                    </div>
                </div>
                <template x-if="t.metadata && Object.keys(t.metadata).length">
                    <div class="mt-3 pt-2 border-t border-gray-100 flex flex-wrap gap-1.5">
                        <template x-for="(v,k) in t.metadata" :key="k">
                            <span class="text-[11px] bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5 text-gray-600"><span class="text-gray-400" x-text="k+':'"></span> <span x-text="v"></span></span>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

<script>
function machineMonitor() {
    return {
        tiles: @json($tiles),
        init() {
            setInterval(() => this.refresh(), 3000);
        },
        async refresh() {
            try {
                const r = await fetch('{{ route('admin.machine-monitor.check') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                if (!r.ok) return;
                const j = await r.json();
                this.tiles = j.data;
            } catch(e) {}
        },
        borderClass(c) {
            return {green:'border-green-500',amber:'border-amber-500',red:'border-red-500',gray:'border-gray-400',blue:'border-blue-500',slate:'border-slate-400'}[c] || 'border-slate-400';
        },
        badgeClass(c) {
            return {green:'bg-green-100 text-green-700',amber:'bg-amber-100 text-amber-700',red:'bg-red-100 text-red-700',gray:'bg-gray-100 text-gray-600',blue:'bg-blue-100 text-blue-700',slate:'bg-slate-100 text-slate-600'}[c] || 'bg-slate-100 text-slate-600';
        }
    };
}
</script>
@endsection
