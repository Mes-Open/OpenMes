@extends('layouts.app')

@section('title', 'Issue Types')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Issue Types', 'url' => null],
]" />

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Issue Types</h1>
            <p class="text-gray-600 mt-1">Configure the types of issues operators can report</p>
        </div>
        <a href="{{ route('admin.issue-types.create') }}" class="btn-touch btn-primary">+ New Type</a>
    </div>

    <div class="card overflow-hidden p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blocking</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issues</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @forelse($issueTypes as $type)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $type->name }}</td>
                        <td class="px-4 py-3 font-mono text-sm text-gray-600">{{ $type->code }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                @if($type->severity === 'CRITICAL') bg-red-100 text-red-800
                                @elseif($type->severity === 'HIGH')  bg-orange-100 text-orange-800
                                @elseif($type->severity === 'MEDIUM') bg-yellow-100 text-yellow-800
                                @else                                bg-gray-100 text-gray-600
                                @endif">
                                {{ $type->severity }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($type->is_blocking)
                                <span class="text-xs font-medium text-red-700 bg-red-50 px-2 py-0.5 rounded">Blocking</span>
                            @else
                                <span class="text-xs text-gray-400">Non-blocking</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.issue-types.toggle-active', $type) }}">
                                @csrf
                                <button type="submit" class="text-xs px-2 py-0.5 rounded font-medium
                                    {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $type->issues_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-3">
                                <a href="{{ route('admin.issue-types.edit', $type) }}" class="text-sm text-blue-600 hover:underline">Edit</a>
                                @if($type->issues_count === 0)
                                    <form method="POST" action="{{ route('admin.issue-types.destroy', $type) }}"
                                          onsubmit="return confirm('Delete issue type {{ $type->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm text-red-500 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                            No issue types yet.
                            <a href="{{ route('admin.issue-types.create') }}" class="text-blue-600 hover:underline ml-1">Create one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
