@extends('layouts.app')

@section('title', 'CSV Import')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Import</h1>
            <p class="text-gray-600 mt-1">Import work orders from a CSV, XLS or XLSX file with custom column mapping</p>
        </div>
    </div>

    {{-- Import result banner --}}
    @if(session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="card mb-6 border-l-4 {{ $result['failed'] === 0 ? 'border-green-500' : 'border-yellow-500' }}">
            <div class="flex items-start gap-4">
                <div class="{{ $result['failed'] === 0 ? 'bg-green-100' : 'bg-yellow-100' }} rounded-full p-3 flex-shrink-0">
                    @if($result['failed'] === 0)
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                        </svg>
                    @endif
                </div>
                <div class="flex-1">
                    <p class="font-bold text-gray-800 mb-1">Import {{ $result['failed'] === 0 ? 'Completed' : 'Completed with errors' }}</p>
                    <div class="flex gap-6 text-sm">
                        <span class="text-green-700 font-medium">✓ {{ $result['success'] }} imported</span>
                        @if($result['failed'] > 0)
                            <span class="text-red-700 font-medium">✗ {{ $result['failed'] }} failed</span>
                        @endif
                        <span class="text-gray-600">{{ $result['total'] }} total rows</span>
                    </div>
                    @if(!empty($result['errors']))
                        <details class="mt-3">
                            <summary class="text-sm text-red-600 cursor-pointer">Show errors ({{ count($result['errors']) }})</summary>
                            <ul class="mt-2 text-xs text-red-700 space-y-1 bg-red-50 rounded p-3">
                                @foreach($result['errors'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Upload Form --}}
        <div class="lg:col-span-2 card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Upload File</h2>
            <form method="POST" action="{{ route('admin.csv-import.upload') }}" enctype="multipart/form-data"
                  x-data="{ dragging: false, filename: '' }">
                @csrf

                {{-- Drop zone --}}
                <div
                    class="border-2 border-dashed rounded-xl p-8 text-center transition-colors mb-6 cursor-pointer"
                    :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; filename = $refs.fileInput.files[0]?.name || ''"
                    @click="$refs.fileInput.click()"
                >
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="text-gray-600 font-medium">Drop file here or <span class="text-blue-600">browse</span></p>
                    <p class="text-sm text-gray-400 mt-1">Max 10 MB · .csv, .txt, .xlsx, .xls</p>
                    <input
                        type="file"
                        name="csv_file"
                        x-ref="fileInput"
                        accept=".csv,.txt,.xlsx,.xls"
                        class="hidden"
                        @change="filename = $refs.fileInput.files[0]?.name || ''"
                        required
                    >
                    <p x-show="filename" class="mt-2 text-sm text-blue-700 font-medium" x-text="filename"></p>
                </div>
                @error('csv_file')
                    <p class="text-red-600 text-sm -mt-4 mb-4">{{ $message }}</p>
                @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="form-label">Duplicate Strategy</label>
                        <select name="import_strategy" class="form-input w-full" required>
                            <option value="update_or_create">Update if exists, create if new</option>
                            <option value="skip_existing">Skip existing records</option>
                            <option value="error_on_duplicate">Error on duplicates</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Load Mapping Profile (optional)</label>
                        <select name="mapping_id" class="form-input w-full">
                            <option value="">— Map columns manually —</option>
                            @foreach($savedMappings as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}{{ $m->is_default ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-touch btn-primary w-full">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload & Configure Mapping
                </button>
            </form>

            {{-- Field Reference --}}
            <details class="mt-6">
                <summary class="text-sm font-medium text-gray-600 cursor-pointer hover:text-gray-800">📋 Available system fields reference</summary>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($systemFields as $key => $label)
                        <div class="flex items-center gap-2 text-xs bg-gray-50 rounded p-2">
                            <code class="text-blue-700 font-mono shrink-0">{{ $key }}</code>
                            <span class="text-gray-600">{{ $label }}</span>
                            @if(in_array($key, ['order_no', 'quantity']))
                                <span class="ml-auto text-red-500 font-bold shrink-0">required</span>
                            @endif
                        </div>
                    @endforeach
                    <div class="flex items-center gap-2 text-xs bg-purple-50 rounded p-2 sm:col-span-2">
                        <code class="text-purple-700 font-mono shrink-0">custom:field_name</code>
                        <span class="text-gray-600">Any extra field — stored as JSON on the work order</span>
                    </div>
                </div>
            </details>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Saved Mapping Profiles --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-3">Saved Mapping Profiles</h2>
                @forelse($savedMappings as $m)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $m->name }}</p>
                            @php $cols = count($m->mapping_config['column_mappings'] ?? []); @endphp
                            <p class="text-xs text-gray-500">{{ $cols }} column{{ $cols !== 1 ? 's' : '' }} mapped</p>
                        </div>
                        @if(!$m->is_default)
                            <form method="POST" action="{{ route('admin.csv-import.mappings.destroy', $m) }}"
                                  onsubmit="return confirm('Delete mapping profile?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No saved profiles yet. Profiles are saved during import.</p>
                @endforelse
            </div>

            {{-- Recent Imports --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-3">Recent Imports</h2>
                @forelse($recentImports as $imp)
                    <div class="py-2 border-b border-gray-100 last:border-0">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-xs text-gray-600 truncate max-w-[140px]" title="{{ $imp->filename }}">
                                {{ $imp->filename }}
                            </p>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                {{ $imp->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : ($imp->status === 'FAILED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ $imp->status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">
                            <span class="text-green-600">✓ {{ $imp->successful_rows }}</span> /
                            {{ $imp->total_rows }} rows
                            @if($imp->failed_rows > 0)
                                · <span class="text-red-600">✗ {{ $imp->failed_rows }}</span>
                            @endif
                            · {{ $imp->created_at->diffForHumans() }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No imports yet.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
