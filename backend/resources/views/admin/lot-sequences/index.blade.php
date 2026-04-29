@extends('layouts.app')

@section('title', 'LOT Sequences')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'LOT Sequences', 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">LOT Sequences</h1>
        <a href="{{ route('admin.lot-sequences.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Sequence
        </a>
    </div>

    @if($sequences->count() > 0)
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prefix</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Next #</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($sequences as $seq)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $seq->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $seq->productType?->name ?? 'Default (all)' }}
                            </td>
                            <td class="px-4 py-3 text-sm font-mono">{{ $seq->prefix }}{{ $seq->suffix ? ' / '.$seq->suffix : '' }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-blue-600">{{ $seq->previewNext() }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $seq->next_number }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.lot-sequences.edit', $seq) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.lot-sequences.destroy', $seq) }}" onsubmit="return confirm('Delete this LOT sequence?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card text-center py-12">
            <p class="text-gray-500 text-lg mb-4">No LOT sequences configured yet.</p>
            <a href="{{ route('admin.lot-sequences.create') }}" class="btn-touch btn-primary">Add First Sequence</a>
        </div>
    @endif
</div>
@endsection
