@extends('layouts.app')

@section('title', 'Pakowanie — Przegląd')

@section('content')
<div class="max-w-7xl mx-auto">
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Pakowanie', 'url' => null],
    ]" />

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Pakowanie — Przegląd</h1>
            <p class="text-sm text-gray-500 mt-1">Bieżąca zmiana: {{ (now()->hour >= 6 && now()->hour < 18) ? '06:00 – 18:00' : '18:00 – 06:00' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('packaging.station') }}" class="btn-touch btn-primary">
                Otwórz stanowisko
            </a>
            <a href="{{ route('packaging.eans.index') }}" class="btn-touch btn-secondary">
                Zarządzaj EAN
            </a>
        </div>
    </div>

    {{-- Stats ────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ number_format($stats['today_packed']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Spakowano (zmiana)</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-gray-700 dark:text-gray-200">{{ number_format($stats['plan']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Plan łącznie</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold {{ $stats['backlog'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ number_format($stats['backlog']) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Backlog</p>
        </div>
        @php
            $realizacja = $stats['plan'] > 0 ? min(100, round($stats['total_packed'] / $stats['plan'] * 100)) : 0;
        @endphp
        <div class="card text-center">
            <p class="text-3xl font-extrabold {{ $realizacja >= 100 ? 'text-green-600' : ($realizacja >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $realizacja }}%
            </p>
            <p class="text-xs text-gray-500 mt-1">Realizacja</p>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                <div class="h-1.5 rounded-full {{ $realizacja >= 100 ? 'bg-green-500' : ($realizacja >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                     style="width: {{ $realizacja }}%"></div>
            </div>
        </div>
    </div>

    {{-- Items table ───────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                Zlecenia do spakowania ({{ count($items) }})
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Zlecenie</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Linia</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">EAN</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Spakowano</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Plan</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-32">Postęp</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($items as $item)
                        <tr class="{{ $item['done'] ? 'bg-green-50 dark:bg-green-900/10' : '' }}">
                            <td class="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $item['order_no'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item['product'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item['line'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @foreach($item['eans'] as $ean)
                                    <span class="inline-block font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded mr-1">{{ $ean }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">{{ $item['packed_qty'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ $item['planned_qty'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $item['done'] ? 'bg-green-500' : ($item['progress'] >= 50 ? 'bg-yellow-500' : 'bg-indigo-500') }}"
                                             style="width: {{ $item['progress'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 w-8 text-right">{{ $item['progress'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($item['done'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        Spakowane
                                    </span>
                                @elseif($item['status'] === 'DONE')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        W trakcie
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $item['status'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                                Brak zleceń z przypisanymi kodami EAN
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
