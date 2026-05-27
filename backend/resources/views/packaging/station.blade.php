@extends('layouts.app')

@section('title', __('Packaging Station'))

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="packagingStation('{{ $scannerMode ?? 'hid' }}')"
     x-init="init()">

    {{-- Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                </svg>
                {{ __('Packaging Station') }}

                {{-- Scanner help (Admin/Supervisor only) --}}
                @auth
                @if(auth()->user()->hasAnyRole(['Admin', 'Supervisor']))
                <span class="relative" x-data="{ helpOpen: false }">
                    <button type="button" @click="helpOpen = !helpOpen"
                            class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-colors align-middle"
                            title="{{ __('How does scanning work?') }}">
                        ?
                    </button>

                <div x-show="helpOpen" @click.outside="helpOpen = false" x-cloak x-transition
                     class="absolute left-0 top-full mt-2 w-80 max-w-[90vw] bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-4 text-sm">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100">{{ __('EAN Scanning') }}</h3>
                        <button type="button" @click="helpOpen = false"
                                class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <p class="text-xs text-gray-600 dark:text-gray-300 mb-3">
                        {{ __('Scan the EAN code assigned to a work order. The system recognizes the order and increments the packed counter.') }}
                    </p>

                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-3 mb-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('Current mode') }}</p>
                        <template x-if="scannerMode === 'hid'">
                            <div>
                                <p class="font-semibold text-gray-800 dark:text-gray-100 text-sm">{{ __('HID (keyboard wedge)') }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Just scan the code with the scanner - data is entered automatically, no need to click anywhere.') }}
                                </p>
                            </div>
                        </template>
                        <template x-if="scannerMode === 'manual'">
                            <div>
                                <p class="font-semibold text-gray-800 dark:text-gray-100 text-sm">{{ __('Manual (typing)') }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Type the code in the field above the table and press Enter. Use when the scanner does not work.') }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <div class="space-y-1.5 mb-3">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-green-200 text-green-800">{{ __('OK') }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ __('Code recognized, counter incremented') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-200 text-red-800">{{ __('ERROR') }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ __('Unknown EAN or inactive work order') }}</span>
                        </div>
                    </div>

                    <button type="button"
                            @click="helpOpen = false; $dispatch('open-scanner-config')"
                            class="inline-flex items-center justify-center gap-2 w-full px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h2m14 0h2M3 16h2m14 0h2M8 4v2m8-2v2M8 18v2m8-2v2M5 8h14v8H5z"/>
                        </svg>
                        {{ __('Configure scanner') }}
                    </button>
                    </div>
                </span>
                @endif
                @endauth
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Shift') }}: <span class="font-semibold" x-text="shiftLabel"></span>
                &nbsp;·&nbsp; {{ __('Logged in') }}: <span class="font-semibold">{{ auth()->user()->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full"
                  :class="scannerMode === 'manual'
                        ? 'text-blue-700 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-400'">
                <span class="w-2 h-2 rounded-full"
                      :class="scannerMode === 'manual' ? 'bg-blue-500' : 'bg-green-500 animate-pulse'"></span>
                <span x-text="scannerMode === 'manual' ? @json(__('Scanner: manual')) : @json(__('Scanning active (HID)'))"></span>
            </span>
        </div>
    </div>

    {{-- ═══════ SCANNER CONFIGURATION MODAL ═══════ --}}
    @auth
    @if(auth()->user()->hasAnyRole(['Admin', 'Supervisor']))
    <div x-data="{ open: false }"
         @open-scanner-config.window="open = true"
         x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60" @click="open = false"></div>
        <div class="relative bg-white dark:bg-gray-800 w-full max-w-md rounded-xl shadow-2xl p-6"
             x-transition @click.stop>
            {{-- Header --}}
            <div class="flex items-start justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ __('Scanner Configuration') }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Scan the code to configure the scanner') }}</p>
                </div>
                <button type="button" @click="open = false"
                        class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 -mt-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Instructions --}}
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 mb-4 text-xs text-blue-900 dark:text-blue-200 space-y-1">
                <p class="font-semibold">{{ __('How to use') }}</p>
                <ol class="list-decimal pl-4 space-y-0.5">
                    <li>{{ __('Point the scanner at the code below and scan it once.') }}</li>
                    <li>{!! __('The scanner remembers the configuration: after every subsequent scan it will send :combo.', ['combo' => '<kbd class="px-1 rounded bg-white border border-gray-300 font-mono text-[10px]">Ctrl</kbd>+<kbd class="px-1 rounded bg-white border border-gray-300 font-mono text-[10px]">V</kbd>+<kbd class="px-1 rounded bg-white border border-gray-300 font-mono text-[10px]">Enter</kbd>']) !!}</li>
                    <li>{{ __('Go back to the station - EAN scanning will start working automatically.') }}</li>
                </ol>
            </div>

            {{-- Barcode --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 flex flex-col items-center">
                <svg viewBox="0 0 200 60" preserveAspectRatio="xMidYMid meet"
                     class="w-full h-24" shape-rendering="crispEdges"
                     xmlns="http://www.w3.org/2000/svg">
                    <rect width="200" height="60" fill="#fff"/>
                    <g fill="#000">
                        <rect x="2"   y="0" width="2" height="60"/>
                        <rect x="6"   y="0" width="1" height="60"/>
                        <rect x="9"   y="0" width="3" height="60"/>
                        <rect x="14"  y="0" width="1" height="60"/>
                        <rect x="17"  y="0" width="2" height="60"/>
                        <rect x="21"  y="0" width="2" height="60"/>
                        <rect x="25"  y="0" width="1" height="60"/>
                        <rect x="28"  y="0" width="3" height="60"/>
                        <rect x="33"  y="0" width="2" height="60"/>
                        <rect x="37"  y="0" width="1" height="60"/>
                        <rect x="40"  y="0" width="1" height="60"/>
                        <rect x="43"  y="0" width="3" height="60"/>
                        <rect x="48"  y="0" width="1" height="60"/>
                        <rect x="51"  y="0" width="2" height="60"/>
                        <rect x="55"  y="0" width="1" height="60"/>
                        <rect x="58"  y="0" width="2" height="60"/>
                        <rect x="62"  y="0" width="3" height="60"/>
                        <rect x="67"  y="0" width="1" height="60"/>
                        <rect x="70"  y="0" width="2" height="60"/>
                        <rect x="74"  y="0" width="1" height="60"/>
                        <rect x="77"  y="0" width="3" height="60"/>
                        <rect x="82"  y="0" width="1" height="60"/>
                        <rect x="85"  y="0" width="2" height="60"/>
                        <rect x="89"  y="0" width="2" height="60"/>
                        <rect x="93"  y="0" width="1" height="60"/>
                        <rect x="96"  y="0" width="3" height="60"/>
                        <rect x="101" y="0" width="2" height="60"/>
                        <rect x="105" y="0" width="1" height="60"/>
                        <rect x="108" y="0" width="1" height="60"/>
                        <rect x="111" y="0" width="3" height="60"/>
                        <rect x="116" y="0" width="2" height="60"/>
                        <rect x="120" y="0" width="1" height="60"/>
                        <rect x="123" y="0" width="2" height="60"/>
                        <rect x="127" y="0" width="3" height="60"/>
                        <rect x="132" y="0" width="1" height="60"/>
                        <rect x="135" y="0" width="2" height="60"/>
                        <rect x="139" y="0" width="1" height="60"/>
                        <rect x="142" y="0" width="3" height="60"/>
                        <rect x="147" y="0" width="2" height="60"/>
                        <rect x="151" y="0" width="1" height="60"/>
                        <rect x="154" y="0" width="2" height="60"/>
                        <rect x="158" y="0" width="2" height="60"/>
                        <rect x="162" y="0" width="1" height="60"/>
                        <rect x="165" y="0" width="3" height="60"/>
                        <rect x="170" y="0" width="1" height="60"/>
                        <rect x="173" y="0" width="2" height="60"/>
                        <rect x="177" y="0" width="1" height="60"/>
                        <rect x="180" y="0" width="3" height="60"/>
                        <rect x="185" y="0" width="2" height="60"/>
                        <rect x="189" y="0" width="1" height="60"/>
                        <rect x="192" y="0" width="2" height="60"/>
                        <rect x="196" y="0" width="2" height="60"/>
                    </g>
                </svg>
                <div class="font-mono text-[11px] mt-2 tracking-widest text-black">CFG-CTRL+V+ENTER</div>
                <p class="text-[10px] text-gray-500 mt-2 text-center">{{ __('CODE 128 · scanner suffix configuration') }}</p>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 mt-4">
                <a href="{{ route('settings.system') }}?tab=production"
                   class="text-xs text-gray-500 hover:text-gray-700 underline">
                    {{ __('Open settings') }}
                </a>
                <button type="button" @click="open = false"
                        class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
    @endif
    @endauth

    {{-- Manual input (only when scanner_mode = manual) --}}
    <form x-show="scannerMode === 'manual'" x-cloak
          @submit.prevent="handleScan(manualInput); manualInput = ''"
          class="card mb-4 flex items-center gap-2">
        <input type="text" x-model="manualInput" x-ref="manualInput"
               :placeholder="@json(__('Scan or type the EAN code and press Enter…'))"
               class="form-input flex-1 font-mono text-lg"
               autocomplete="off" inputmode="text">
        <button type="submit"
                class="px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shrink-0">
            OK
        </button>
    </form>

    {{-- Stats row ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400" x-text="stats.today_packed ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Packed (shift)') }}</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold text-gray-700 dark:text-gray-200" x-text="stats.plan ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Total plan') }}</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold"
               :class="(stats.backlog ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
               x-text="stats.backlog ?? '—'"></p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Backlog') }}</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-extrabold"
               :class="realizacja >= 100 ? 'text-green-600' : realizacja >= 50 ? 'text-yellow-600' : 'text-red-600'"
               x-text="realizacja + '%'"></p>
            <p class="text-xs text-gray-500 mt-1">{{ __('Completion') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Last scan ───────────────────────────────────────────────────── --}}
        <div class="card">
            <h2 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">{{ __('Last scan') }}</h2>

            <div x-show="!lastScan" class="py-8 text-center text-gray-400 dark:text-gray-600 text-sm">
                {{ __('Hold an EAN code up to the scanner…') }}
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
                          x-text="lastScan?.success ? @json(__('OK')) : @json(__('Error'))"></span>
                </div>

                <div x-show="lastScan?.success" class="mt-3 flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all duration-500"
                             :class="lastScan?.progress >= 100 ? 'bg-green-500' : lastScan?.progress >= 50 ? 'bg-yellow-500' : 'bg-indigo-500'"
                             :style="'width:' + (lastScan?.progress ?? 0) + '%'"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        <span x-text="lastScan?.packed_qty"></span> / <span x-text="lastScan?.planned_qty"></span> {{ __('pcs.') }}
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
                {{ __('Waiting for a scan…') }}
            </div>
            <div x-show="flash === 'success'" x-cloak class="text-center">
                <svg class="mx-auto w-14 h-14 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-green-700 dark:text-green-300 font-bold mt-2">{{ __('Scanned!') }}</p>
            </div>
            <div x-show="flash === 'error'" x-cloak class="text-center">
                <svg class="mx-auto w-14 h-14 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <p class="text-red-700 dark:text-red-300 font-bold mt-2">{{ __('Scanning error') }}</p>
            </div>
        </div>
    </div>

    {{-- Items to pack ────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0 mb-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                {{ __('Work orders to pack') }}
            </h2>
            <span class="text-xs text-gray-400" x-text="items.length + ' ' + @json(__('items'))"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Order') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Product') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('EAN') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Packed') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Plan') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">{{ __('Progress') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <template x-if="items.length === 0">
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">{{ __('No work orders with assigned EAN codes') }}</td></tr>
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
                {{ __('Scan history (shift)') }}
            </h2>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Time') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Product') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('EAN') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <template x-if="history.length === 0">
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-sm">{{ __('No scans in this shift') }}</td></tr>
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
function packagingStation(scannerMode) {
    return {
        scannerMode: scannerMode || 'hid',
        manualInput: '',
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
            if (this.scannerMode === 'hid') {
                document.addEventListener('keydown', (e) => this.onKey(e));
            } else {
                this.$nextTick(() => this.$refs.manualInput?.focus());
            }
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
            ean = (ean || '').trim();
            if (!ean) return;
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
                this.lastScan = { success: false, ean, error: @json(__('Connection error')), scanned_at: new Date().toLocaleTimeString() };
                this.flash = 'error';
            }

            setTimeout(() => { this.flash = null; }, 2000);
            if (this.scannerMode === 'manual') {
                this.$nextTick(() => this.$refs.manualInput?.focus());
            }
        },
    };
}
</script>
@endpush
@endsection
