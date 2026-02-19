@extends('layouts.app')

@section('title', 'Divisions')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Divisions</h1>
        <a href="{{ route('admin.divisions.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Division
        </a>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Factory</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Code</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($divisions as $division)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 text-gray-600">{{ $division->factory->name ?? '—' }}</td>
                            <td class="py-3 px-4 font-mono text-gray-600">{{ $division->code }}</td>
                            <td class="py-3 px-4 font-medium text-gray-900">{{ $division->name }}</td>
                            <td class="py-3 px-4">
                                @if($division->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.divisions.edit', $division) }}" class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.divisions.toggle-active', $division) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-gray-600 hover:text-gray-800 p-1" title="{{ $division->is_active ? 'Deactivate' : 'Activate' }}">
                                            @if($division->is_active)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            @endif
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.divisions.destroy', $division) }}" class="inline"
                                          onsubmit="return confirm('Delete this division?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-500">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                <p class="font-medium">No divisions yet</p>
                                <a href="{{ route('admin.divisions.create') }}" class="inline-block mt-3 btn-touch btn-primary">Add Division</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($divisions->hasPages())
            <div class="mt-4 px-4">{{ $divisions->links() }}</div>
        @endif
    </div>
</div>
@endsection
