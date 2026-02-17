@extends('layouts.app')

@section('title', 'Select Line')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Select Production Line</h1>
        <p class="text-gray-600 mt-2">Choose a production line to begin work</p>
    </div>

    @if($lines->isEmpty())
        <div class="card text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No lines assigned</h3>
            <p class="mt-1 text-sm text-gray-500">You are not assigned to any production lines. Please contact your administrator.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($lines as $line)
                <form method="POST" action="{{ route('operator.select-line.post') }}" class="transform transition hover:scale-105">
                    @csrf
                    <input type="hidden" name="line_id" value="{{ $line->id }}">

                    <button type="submit" class="w-full card hover:shadow-xl cursor-pointer text-left transition-all">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-800">{{ $line->name }}</h3>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                Active
                            </span>
                        </div>

                        @if($line->description)
                            <p class="text-gray-600 mb-4">{{ $line->description }}</p>
                        @endif

                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span>{{ $line->workstations->count() }} Workstation{{ $line->workstations->count() !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-end text-blue-600">
                            <span class="text-sm font-medium">Select Line</span>
                            <svg class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
