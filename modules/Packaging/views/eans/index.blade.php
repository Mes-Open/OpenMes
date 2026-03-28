@extends('layouts.app')

@section('title', 'Zarządzanie kodami EAN')

@section('content')
<div class="max-w-7xl mx-auto">
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Pakowanie', 'url' => route('packaging.overview')],
        ['label' => 'Kody EAN', 'url' => null],
    ]" />

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Kody EAN — Zarządzanie</h1>
            <p class="text-sm text-gray-500 mt-1">Przypisuj kody kreskowe do zleceń produkcyjnych</p>
        </div>
        <a href="{{ route('packaging.overview') }}" class="btn-touch btn-secondary">← Przegląd pakowania</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 text-sm font-medium border border-green-200 dark:border-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Add EAN form ─────────────────────────────────────────────────────── --}}
    <div class="card mb-6" x-data="{ workOrderId: '' }">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Dodaj kod EAN</h2>
        <form method="POST" action="{{ route('packaging.eans.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="form-label">Zlecenie produkcyjne</label>
                <select name="work_order_id" class="form-input w-full" required>
                    <option value="">— wybierz zlecenie —</option>
                    @foreach($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(old('work_order_id') == $wo->id)>
                            {{ $wo->order_no }}{{ $wo->productType ? ' — ' . $wo->productType->name : '' }}
                        </option>
                    @endforeach
                </select>
                @error('work_order_id')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="form-label">Kod EAN</label>
                <input type="text" name="ean" value="{{ old('ean') }}" class="form-input w-full font-mono"
                       placeholder="np. 5901234123457" required maxlength="100">
                @error('ean')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-touch btn-primary w-full sm:w-auto">Dodaj EAN</button>
            </div>
        </form>
    </div>

    {{-- Search ───────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('packaging.eans.index') }}" class="card mb-4 py-3">
        <div class="flex gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   class="form-input flex-1" placeholder="Szukaj po numerze zlecenia…">
            <button type="submit" class="btn-touch btn-secondary text-sm">Szukaj</button>
            @if(request('search'))
                <a href="{{ route('packaging.eans.index') }}" class="btn-touch btn-secondary text-sm">Wyczyść</a>
            @endif
        </div>
    </form>

    {{-- Table ────────────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Zlecenie</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Kody EAN</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Spakowano / Plan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($workOrders as $wo)
                        <tr>
                            <td class="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $wo->order_no }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo->productType?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ match($wo->status) {
                                        'DONE'        => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                        'IN_PROGRESS' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                        'PENDING'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        default       => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    } }}">
                                    {{ str_replace('_', ' ', $wo->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @forelse($wo->eans as $ean)
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ $ean->ean }}</span>
                                        <form method="POST" action="{{ route('packaging.eans.destroy', $ean) }}" onsubmit="return confirm('Usunąć kod EAN {{ $ean->ean }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors">Usuń</button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="text-xs text-gray-400">Brak EAN</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">
                                <span class="font-bold">{{ $wo->packed_qty ?? 0 }}</span>
                                <span class="text-gray-400"> / {{ (int) $wo->planned_qty }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Brak wyników</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($workOrders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $workOrders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
