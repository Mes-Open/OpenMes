@extends('layouts.app')

@section('title', __('Shifts'))

@section('content')
<div class="max-w-4xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Shifts'), 'url' => null],
    ]" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ __('Shifts') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Define production shifts. These appear as columns in the Workstation view.') }}</p>
        </div>
        <a href="{{ route('admin.shifts.create') }}" class="btn-touch btn-primary">{{ __('+ New Shift') }}</a>
    </div>

    @if($shifts->isEmpty())
        <div class="card text-center py-16">
            <p class="text-gray-500 dark:text-gray-400 text-lg mb-4">{{ __('No shifts defined yet.') }}</p>
            <a href="{{ route('admin.shifts.create') }}" class="btn-touch btn-primary">{{ __('Create First Shift') }}</a>
        </div>
    @else
        <div class="card overflow-hidden p-0">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Order') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Code') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Time') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($shifts as $shift)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 text-gray-500 font-mono">{{ $shift->sort_order }}</td>
                        <td class="px-4 py-3 font-bold text-gray-800 dark:text-gray-200">{{ $shift->code }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $shift->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono">
                            {{ substr($shift->start_time, 0, 5) }} — {{ substr($shift->end_time, 0, 5) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($shift->is_active)
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ __('Active') }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.shifts.edit', $shift) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</a>
                                @if(!$shift->shiftEntries()->exists())
                                <form method="POST" action="{{ route('admin.shifts.destroy', $shift) }}" class="inline"
                                      onsubmit="return confirm('{{ __('Delete shift :code?', ['code' => $shift->code]) }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-medium">{{ __('Delete') }}</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
