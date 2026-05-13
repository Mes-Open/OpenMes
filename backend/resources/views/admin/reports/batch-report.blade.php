@extends('layouts.app')

@section('title', __('Series Report') . ' — ' . ($batch->lot_number ?? __('Batch #') . $batch->batch_number))

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6 print:hidden">
        <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Back') }}
        </a>
        <div class="flex gap-3">
            <button onclick="window.print()" class="btn-touch btn-secondary">{{ __('Print') }}</button>
            <a href="{{ route('admin.batch-report.pdf', $batch) }}" class="btn-touch btn-primary">{{ __('Download PDF') }}</a>
        </div>
    </div>

    @include('admin.reports._batch-report-content')
</div>
@endsection
