@extends('layouts.app')

@section('title', __('{{ __('Select Production Line') }}'))

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('Select Production Line') }}</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Choose a production line and optionally a workstation</p>
    </div>

    @if($lines->isEmpty())
        <div class="card text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No lines assigned') }}</h3>
            <p class="mt-1 text-sm text-gray-500">You are not assigned to any production lines. Please contact your administrator.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($lines as $line)
                <div x-data="{ showWorkstations: false }" class="transform transition hover:scale-105">
                    <form method="POST" action="{{ route('operator.select-line.post') }}">
                        @csrf
                        <input type="hidden" name="line_id" value="{{ $line->id }}">

                        <div class="card hover:shadow-xl transition-all">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $line->name }}</h3>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                    Active
                                </span>
                            </div>

                            @if($line->description)
                                <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $line->description }}</p>
                            @endif

                            {{-- Workstation selector --}}
                            @if($line->workstations->where('is_active', true)->count() > 0)
                                <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Workstation
                                        <span class="text-gray-400 font-normal">(optional)</span>
                                    </label>
                                    <select name="workstation_id"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                            @change="showWorkstations = true">
                                        <option value="">All workstations</option>
                                        @foreach($line->workstations->where('is_active', true)->sortBy('name') as $ws)
                                            <option value="{{ $ws->id }}">{{ $ws->name }}{{ $ws->code ? ' ('.$ws->code.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mb-4">
                                    <div class="flex items-center text-sm text-gray-500">
                                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        <span>No workstations</span>
                                    </div>
                                </div>
                            @endif

                            <button type="submit" class="w-full btn-touch btn-primary flex items-center justify-center gap-2">
                                <span>Select</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
