@php
    /**
     * @var \App\Models\MaintenanceEvent $event
     * @var bool $isOverdue
     * @var bool $compact
     */
    $isOverdue = $isOverdue ?? false;
    $compact   = $compact ?? false;

    $statusColors = [
        'pending'     => 'bg-amber-100 text-amber-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'completed'   => 'bg-green-100 text-green-700',
        'cancelled'   => 'bg-gray-100 text-gray-500',
    ];
    $statusLabels = [
        'pending'     => __('Pending'),
        'in_progress' => __('In progress'),
        'completed'   => __('Completed'),
        'cancelled'   => __('Cancelled'),
    ];
    $typeConfig = [
        'planned'    => ['color' => 'bg-green-50 text-green-700 border border-green-200', 'label' => __('Planned')],
        'corrective' => ['color' => 'bg-red-50 text-red-700 border border-red-200',       'label' => __('Corrective')],
        'inspection' => ['color' => 'bg-blue-50 text-blue-700 border border-blue-200',    'label' => __('Inspection')],
    ];

    $statusColor = $statusColors[$event->status] ?? 'bg-gray-100 text-gray-600';
    $statusLabel = $statusLabels[$event->status] ?? ucfirst(str_replace('_', ' ', (string) $event->status));
    $type        = $typeConfig[$event->event_type] ?? ['color' => 'bg-gray-50 text-gray-600 border border-gray-200', 'label' => ucfirst((string) $event->event_type)];

    $daysOverdue = 0;
    if ($isOverdue && $event->scheduled_at) {
        $daysOverdue = (int) floor($event->scheduled_at->diffInHours(now()) / 24);
        if ($daysOverdue < 1) {
            $daysOverdue = 1;
        }
    }
@endphp

<div class="card {{ $isOverdue ? 'border-l-4 border-red-400' : '' }} {{ $compact ? 'py-3' : '' }}">
    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
        {{-- Type icon --}}
        <div class="flex-shrink-0 hidden sm:flex items-center justify-center w-10 h-10 rounded-lg {{ $type['color'] }}">
            @if($event->event_type === 'corrective')
                {{-- wrench-screwdriver --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                </svg>
            @elseif($event->event_type === 'inspection')
                {{-- magnifying-glass --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
            @else
                {{-- calendar --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            {{-- Title + badges --}}
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <a href="{{ route('admin.maintenance-events.show', $event) }}"
                   class="font-semibold text-gray-800 hover:text-blue-700 transition-colors truncate">
                    {{ $event->title }}
                </a>

                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }} {{ $event->status === 'in_progress' ? 'animate-pulse' : '' }}">
                    {{ $statusLabel }}
                </span>

                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $type['color'] }}">
                    {{ $type['label'] }}
                </span>

                @if($isOverdue)
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700"
                          title="{{ __(':n days overdue', ['n' => $daysOverdue]) }}">
                        {{ $daysOverdue }} {{ trans_choice('day|days', $daysOverdue) }} {{ __('overdue') }}
                    </span>
                @endif
            </div>

            {{-- Meta line --}}
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                @if($event->assignedTo)
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        {{ $event->assignedTo->name }}
                    </span>
                @endif

                @php
                    $targetLabel = null;
                    if ($event->line) {
                        $targetLabel = $event->line->name;
                    } elseif ($event->workstation) {
                        $targetLabel = $event->workstation->name;
                    } elseif ($event->tool) {
                        $targetLabel = $event->tool->name;
                    }
                @endphp
                @if($targetLabel)
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                        </svg>
                        {{ $targetLabel }}
                    </span>
                @endif

                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75"/>
                    </svg>
                    {{ $event->scheduled_at?->format('Y-m-d H:i') ?? '—' }}
                </span>

                @if($event->status === 'in_progress' && $event->started_at)
                    <span class="inline-flex items-center gap-1 text-blue-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        {{ __('In progress :time', ['time' => $event->started_at->diffForHumans(null, true)]) }}
                    </span>
                @elseif($event->status === 'completed' && $event->started_at && $event->completed_at)
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        {{ $event->started_at->diffForHumans($event->completed_at, true) }}
                    </span>
                @endif

                @if($event->status === 'completed' && $event->actual_cost !== null)
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4"/>
                        </svg>
                        {{ number_format((float) $event->actual_cost, 2, ',', ' ') }} {{ $event->currency ?? 'PLN' }}
                    </span>
                @endif

                @if($event->status === 'completed' && $event->assignedTo)
                    <span class="text-gray-400">{{ __('by') }} {{ $event->assignedTo->name }}</span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex-shrink-0 flex items-center gap-0.5">
            <a href="{{ route('admin.maintenance-events.show', $event) }}"
               data-tip="{{ __('View') }}"
               class="inline-flex items-center justify-center w-8 h-8 rounded-md text-blue-600 hover:bg-blue-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </a>

            @if(in_array($event->status, ['pending', 'in_progress']))
                <a href="{{ route('admin.maintenance-events.edit', $event) }}"
                   data-tip="{{ __('Edit') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
            @endif

            @if($event->status === 'pending')
                <form method="POST" action="{{ route('admin.maintenance-events.start', $event) }}">
                    @csrf
                    <button type="submit" data-tip="{{ __('Start') }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-green-600 hover:bg-green-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                </form>
            @endif

            @if($event->status === 'in_progress')
                <form method="POST" action="{{ route('admin.maintenance-events.complete', $event) }}">
                    @csrf
                    <button type="submit" data-tip="{{ __('Complete') }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-green-700 hover:bg-green-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </form>
            @endif

            @if(in_array($event->status, ['pending', 'in_progress']))
                <form method="POST" action="{{ route('admin.maintenance-events.cancel', $event) }}"
                      onsubmit="return confirm('{{ __('Cancel this event?') }}')">
                    @csrf
                    <button type="submit" data-tip="{{ __('Cancel') }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-red-500 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
