@extends('layouts.app')

@section('title', 'Maintenance Events')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Maintenance Events', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Maintenance Events</h1>
        <a href="{{ route('admin.maintenance-events.create') }}" class="btn-touch btn-primary">
            Add Maintenance Event
        </a>
    </div>

    @if(session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left p-3">Title</th>
                        <th class="text-left p-3">Type</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">Scheduled</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">{{ $event->title }}</td>
                        <td class="p-3">{{ $event->event_type }}</td>
                        <td class="p-3">{{ $event->status }}</td>
                        <td class="p-3">{{ $event->scheduled_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="p-3">
                            <a href="{{ route('admin.maintenance-events.edit', $event) }}" class="text-blue-600 hover:underline mr-2">Edit</a>

                            @if($event->status === 'pending')
                            <form method="POST" action="{{ route('admin.maintenance-events.start', $event) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-green-600 hover:underline mr-2">Start</button>
                            </form>
                            @endif

                            @if($event->status === 'in_progress')
                            <form method="POST" action="{{ route('admin.maintenance-events.complete', $event) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline mr-2">Complete</button>
                            </form>
                            @endif

                            @if(in_array($event->status, ['pending', 'in_progress']))
                            <form method="POST" action="{{ route('admin.maintenance-events.cancel', $event) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-red-600 hover:underline">Cancel</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-3 text-center text-gray-500">No maintenance events found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $events->links() }}</div>
    </div>
</div>
@endsection
