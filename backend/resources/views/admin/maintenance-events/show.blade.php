@extends('layouts.app')

@section('title', 'Maintenance Event')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $event->title }}</h1>
            <p class="text-gray-500 mt-1 text-sm">Scheduled: {{ $event->scheduled_at?->format('d M Y H:i') ?? '—' }}</p>
        </div>
        <a href="{{ route('admin.maintenance-events.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main details -->
        <div class="lg:col-span-2 space-y-4">
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Details</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Event Type</dt>
                        <dd class="mt-1">
                            @php
                                $typeColors = [
                                    'preventive'  => 'bg-blue-100 text-blue-800',
                                    'corrective'  => 'bg-red-100 text-red-800',
                                    'inspection'  => 'bg-purple-100 text-purple-800',
                                    'calibration' => 'bg-yellow-100 text-yellow-800',
                                ];
                                $typeColor = $typeColors[$event->event_type] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <span class="px-2 py-1 {{ $typeColor }} rounded-full text-xs font-medium capitalize">
                                {{ str_replace('_', ' ', $event->event_type) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Status</dt>
                        <dd class="mt-1">
                            @php
                                $statusColors = [
                                    'scheduled'   => 'bg-yellow-100 text-yellow-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'completed'   => 'bg-green-100 text-green-800',
                                    'cancelled'   => 'bg-gray-100 text-gray-600',
                                ];
                                $statusColor = $statusColors[$event->status] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <span class="px-2 py-1 {{ $statusColor }} rounded-full text-xs font-medium capitalize">
                                {{ str_replace('_', ' ', $event->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Assigned To</dt>
                        <dd class="mt-1 text-gray-800">{{ $event->assignedTo->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Cost Source</dt>
                        <dd class="mt-1 text-gray-800">{{ $event->costSource->name ?? '—' }}</dd>
                    </div>
                    @if($event->tool)
                        <div>
                            <dt class="text-xs text-gray-500 uppercase tracking-wide">Tool</dt>
                            <dd class="mt-1 text-gray-800">{{ $event->tool->name }}</dd>
                        </div>
                    @endif
                    @if($event->line)
                        <div>
                            <dt class="text-xs text-gray-500 uppercase tracking-wide">Production Line</dt>
                            <dd class="mt-1 text-gray-800">{{ $event->line->name }}</dd>
                        </div>
                    @endif
                    @if($event->workstation)
                        <div>
                            <dt class="text-xs text-gray-500 uppercase tracking-wide">Workstation</dt>
                            <dd class="mt-1 text-gray-800">{{ $event->workstation->name }}</dd>
                        </div>
                    @endif
                </dl>

                @if($event->description)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <dt class="text-xs text-gray-500 uppercase tracking-wide mb-2">Description</dt>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $event->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar: status actions -->
        <div class="space-y-4">
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('admin.maintenance-events.edit', $event) }}" class="w-full btn-touch btn-secondary block text-center">
                        Edit Event
                    </a>

                    @if($event->status === 'scheduled')
                        <form method="POST" action="{{ route('admin.maintenance-events.start', $event) }}">
                            @csrf
                            <button type="submit" class="w-full btn-touch btn-primary">
                                Start
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.maintenance-events.cancel', $event) }}"
                              onsubmit="return confirm('Cancel this event?');">
                            @csrf
                            <button type="submit" class="w-full btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200">
                                Cancel Event
                            </button>
                        </form>
                    @elseif($event->status === 'in_progress')
                        <form method="POST" action="{{ route('admin.maintenance-events.complete', $event) }}">
                            @csrf
                            <button type="submit" class="w-full btn-touch bg-green-600 text-white hover:bg-green-700">
                                Mark as Complete
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.maintenance-events.cancel', $event) }}"
                              onsubmit="return confirm('Cancel this event?');">
                            @csrf
                            <button type="submit" class="w-full btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200">
                                Cancel Event
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Timeline</h2>
                <ul class="space-y-2 text-sm">
                    <li class="flex gap-2 text-gray-600">
                        <span class="text-gray-400 shrink-0">Created</span>
                        <span class="ml-auto">{{ $event->created_at->format('d M Y') }}</span>
                    </li>
                    @if($event->started_at)
                        <li class="flex gap-2 text-gray-600">
                            <span class="text-gray-400 shrink-0">Started</span>
                            <span class="ml-auto">{{ $event->started_at->format('d M Y H:i') }}</span>
                        </li>
                    @endif
                    @if($event->completed_at)
                        <li class="flex gap-2 text-green-700">
                            <span class="shrink-0">Completed</span>
                            <span class="ml-auto">{{ $event->completed_at->format('d M Y H:i') }}</span>
                        </li>
                    @endif
                    @if($event->cancelled_at)
                        <li class="flex gap-2 text-gray-400">
                            <span class="shrink-0">Cancelled</span>
                            <span class="ml-auto">{{ $event->cancelled_at->format('d M Y H:i') }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
