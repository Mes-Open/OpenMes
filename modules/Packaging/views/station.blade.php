@extends('layouts.app')

@section('title', 'Stanowisko Pakowania')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="packagingStation()"
     x-init="init()">

    {{-- Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                </svg>
                Stanowisko Pakowania
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Zmiana: <span class="font-semibold" x-text="shiftLabel"></span>
                &nbsp;·&nbsp; Zalogowany: <span class="font-semibold">{{ auth()->user()->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-400 px-3 py-1.5 rounded-full">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                Skanowanie aktywne
            </span>
        </div>
    </div>

    {{-- Stats row ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400" x-text="stats.today_packed ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">Spakowano (zmiana)</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-gray-700 dark:text-gray-200" x-text="stats.plan ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">Plan łącznie</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold"
               :class="(stats.backlog ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
               x-text="stats.backlog ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">Backlog</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold"
               :class="realizacja >= 100 ? 'text-green-600' : realizacja >= 50 ? 'text-yellow-600' : 'text-red-600'"
               x-text="realizacja + '%'"></p>
            <p class="text-xs text-gray-500 mt-1">Realizacja</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Last scan ───────────────────────────────────────────────────── --}}
        <div class="card">
            <h2 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">Ostatnie skanowanie</h2>

            <div x-show="!lastScan" class="py-8 text-center text-gray-400 dark:text-gray-600 text-sm">
                Przyłóż kod EAN do skanera…
            </div>

            <div x-show="lastScan" x-cloak>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xl font-bold text-gray-800 dark:text-white" x-text="lastScan?.product"></p>
                        <p class="text-sm text-gray-500 mt-0.5">
                            EAN: <span class="font-mono" x-text="lastScan?.ean"></span>
                            &nbsp;·&nbsp; <span x-text="lastScan?.scanned_at"></span>
                        </p>
                    </div>
                    <span class="shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-bold"
                          :class="lastScan?.success ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                          x-text="lastScan?.success ? 'OK' : 'Błąd'"></span>
                </div>

                <div x-show="lastScan?.success" class="mt-3 flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all duration-500"
                             :class="lastScan?.progress >= 100 ? 'bg-green-500' : lastScan?.progress >= 50 ? 'bg-yellow-500' : 'bg-indigo-500'"
                             :style="'width:' + (lastScan?.progress ?? 0) + '%'"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        <span x-text="lastScan?.packed_qty"></span> / <span x-text="lastScan?.planned_qty"></span> szt.
                    </span>
                </div>

                {{-- Error message --}}
                <div x-show="!lastScan?.success && lastScan?.error" class="mt-3 text-sm text-red-600 dark:text-red-400 font-medium" x-text="lastScan?.error"></div>
            </div>
        </div>

        {{-- Flash overlay (full-width highlight) ──────────────────────── --}}
        <div class="card flex items-center justify-center min-h-[120px]"
             :class="{
                 'bg-green-50 dark:bg-green-900/20 border-green-300': flash === 'success',
                 'bg-red-50 dark:bg-red-900/20 border-red-300': flash === 'error',
                 'card': flash === null
             }">
            <div x-show="flash === null" class="text-center text-gray-400 dark:text-gray-600 text-sm select-none">
                <svg class="mx-auto w-10 h-10 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M12 4v1m6.364 1.636l-.707.707M20 12h-1M17.657 17.657l-.707-.707M12 20v-1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l.707.707"/>
                </svg>
                Czekam na skan…
            </div>
            <div x-show="flash === 'success'" x-cloak class="text-center">
                <svg class="mx-auto w-14 h-14 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-green-700 dark:text-green-300 font-bold mt-2">Zeskanowano!</p>
            </div>
            <div x-show="flash === 'error'" x-cloak class="text-center">
                <svg class="mx-auto w-14 h-14 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <p class="text-red-700 dark:text-red-300 font-bold mt-2">Błąd skanowania</p>
            </div>
        </div>
    </div>

    {{-- Items to pack ────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0 mb-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                Zlecenia do spakowania
            </h2>
            <span class="text-xs text-gray-400" x-text="items.length + ' pozycji'"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Zlecenie</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produkt</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">EAN</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Spakowano</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Postęp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <template x-if="items.length === 0">
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Brak zleceń z przypisanymi kodami EAN</td></tr>
                    </template>
                    <template x-for="item in items" :key="item.id">
                        <tr :class="item.done ? 'bg-green-50 dark:bg-green-900/10' : ''">
                            <td class="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400" x-text="item.order_no"></td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-text="item.product"></td>
                            <td class="px-4 py-3">
                                <template x-for="ean in item.eans" :key="ean">
                                    <span class="inline-block font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded mr-1 mb-0.5" x-text="ean"></span>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-right font-bold" x-text="item.packed_qty"></td>
                            <td class="px-4 py-3 text-right text-gray-500" x-text="item.planned_qty"></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full"
                                             :class="item.done ? 'bg-green-500' : item.progress >= 50 ? 'bg-yellow-500' : 'bg-indigo-500'"
                                             :style="'width:' + item.progress + '%'"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 w-8 text-right" x-text="item.progress + '%'"></span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Scan log ─────────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                Historia skanowań (zmiana)
            </h2>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Czas</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">EAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <template x-if="history.length === 0">
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-sm">Brak skanowań w tej zmianie</td></tr>
                    </template>
                    <template x-for="entry in history" :key="entry.id">
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-gray-500 text-xs whitespace-nowrap" x-text="entry.scanned_at"></td>
                            <td class="px-4 py-2.5 font-medium text-gray-700 dark:text-gray-300" x-text="entry.product_name"></td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-500" x-text="entry.ean"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function packagingStation() {
    return {
        items:     [],
        history:   [],
        stats:     { today_packed: 0, plan: 0, backlog: 0 },
        lastScan:  null,
        flash:     null,
        lastHistoryId: 0,
        buffer:    '',
        bufferTimer: null,

        get realizacja() {
            return this.stats.plan > 0
                ? Math.min(100, Math.round(this.stats.today_packed / this.stats.plan * 100))
                : 0;
        },

        get shiftLabel() {
            const h = new Date().getHours();
            return (h >= 6 && h < 18) ? '06:00 – 18:00' : '18:00 – 06:00';
        },

        async init() {
            await Promise.all([this.fetchItems(), this.fetchHistory(), this.fetchStats()]);
            setInterval(() => this.poll(), 3000);
            document.addEventListener('keydown', (e) => this.onKey(e));
        },

        async poll() {
            try {
                const res = await fetch('{{ route('packaging.history.poll') }}?after_id=' + this.lastHistoryId, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.history.length > 0) {
                    this.history.unshift(...data.history);
                    if (this.history.length > 100) this.history = this.history.slice(0, 100);
                    this.lastHistoryId = Math.max(this.lastHistoryId, ...data.history.map(h => h.id));
                    await Promise.all([this.fetchItems(), this.fetchStats()]);
                }
            } catch {}
        },

        async fetchItems() {
            try {
                const res = await fetch('{{ route('packaging.items') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) this.items = (await res.json()).items;
            } catch {}
        },

        async fetchHistory() {
            try {
                const res = await fetch('{{ route('packaging.history') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                this.history = data.history;
                if (data.history.length > 0) {
                    this.lastHistoryId = Math.max(...data.history.map(h => h.id));
                }
            } catch {}
        },

        async fetchStats() {
            try {
                const res = await fetch('{{ route('packaging.stats') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) this.stats = await res.json();
            } catch {}
        },

        onKey(e) {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.key === 'Enter') {
                const ean = this.buffer.trim();
                this.buffer = '';
                if (this.bufferTimer) clearTimeout(this.bufferTimer);
                if (ean) this.handleScan(ean);
            } else if (e.key.length === 1) {
                this.buffer += e.key;
                if (this.bufferTimer) clearTimeout(this.bufferTimer);
                this.bufferTimer = setTimeout(() => { this.buffer = ''; }, 500);
            }
        },

        async handleScan(ean) {
            try {
                const res = await fetch('{{ route('packaging.scan') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ean }),
                });
                const data = await res.json();

                if (res.ok) {
                    const wo = data.work_order;
                    this.lastScan = {
                        success:     true,
                        product:     wo.product,
                        ean:         ean,
                        packed_qty:  wo.packed_qty,
                        planned_qty: wo.planned_qty,
                        progress:    wo.planned_qty > 0 ? Math.min(100, Math.round(wo.packed_qty / wo.planned_qty * 100)) : 0,
                        scanned_at:  new Date().toLocaleTimeString('pl-PL'),
                    };
                    this.flash = 'success';
                    await Promise.all([this.fetchItems(), this.fetchStats()]);
                    this.history.unshift({
                        id: Date.now(),
                        ean: ean,
                        product_name: wo.product,
                        scanned_at: new Date().toLocaleTimeString('pl-PL'),
                    });
                } else {
                    this.lastScan = { success: false, ean, error: data.message, scanned_at: new Date().toLocaleTimeString('pl-PL') };
                    this.flash = 'error';
                }
            } catch {
                this.lastScan = { success: false, ean, error: 'Błąd połączenia', scanned_at: new Date().toLocaleTimeString('pl-PL') };
                this.flash = 'error';
            }

            setTimeout(() => { this.flash = null; }, 2000);
        },
    };
}
</script>
@endpush
@endsection
