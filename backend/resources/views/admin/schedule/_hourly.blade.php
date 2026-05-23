{{--
    Hourly (minute-level) view of the production planner.

    Renders a Gantt-style grid:
      - Left sticky column = line name + load %.
      - Right scrollable area = 24h timeline with 1 minute = 2 px.
      - Work orders are positioned absolutely from `start_minute` / `duration_minutes`.
      - Drag-to-move (whole card) and drag-to-resize (right handle) snap to
        `slotMinutes` granularity and persist via PUT to /admin/schedule/{id}
        and /admin/schedule/{id}/resize respectively.

    Cross-midnight WOs are signalled with ← / → arrows because the controller
    clamps `start_minute` to 0 and `end_minute` to 1440.
--}}

@php
    $pxPerMinute = 2;
    $hourPx = 60 * $pxPerMinute; // 120
    $totalWidth = $data['minutes_per_day'] * $pxPerMinute; // 2880
    $isToday = $data['date']->isToday();
    $shiftBoundaryMinutes = [];
    if (($shiftsPerDay ?? 3) >= 1) {
        $shiftLengthMinutes = (int) (1440 / max(1, $shiftsPerDay));
        for ($b = $shiftLengthMinutes; $b < 1440; $b += $shiftLengthMinutes) {
            $shiftBoundaryMinutes[] = $b;
        }
    }
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden"
     x-data="hourlyPlanner({
         slotMinutes: {{ (int) $slotMinutes }},
         pxPerMinute: {{ $pxPerMinute }},
         dayStartIso: '{{ $data['date']->copy()->startOfDay()->toIso8601String() }}',
         isToday: {{ $isToday ? 'true' : 'false' }},
         updateUrl: '{{ url('/admin/schedule') }}',
         csrf: '{{ csrf_token() }}',
     })"
     x-init="init()">

    {{-- Header bar with date label + legend --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60">
        <div class="flex items-center gap-3">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $data['label'] }}</span>
            @if($isToday)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">{{ __('Today') }}</span>
            @endif
            <span class="text-[10px] text-gray-400">{{ $slotMinutes }} {{ __('min snap') }}</span>
        </div>
        <div class="flex items-center gap-3 text-[10px] text-gray-500">
            <span class="inline-flex items-center gap-1">
                <span class="w-3 h-2.5 rounded-sm bg-blue-300 border border-blue-500"></span>
                {{ __('Scheduled') }}
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="w-3 h-2.5 rounded-sm bg-red-200 border border-red-500"></span>
                {{ __('Conflict') }}
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="w-3 h-2.5 rounded-sm bg-gray-200 border border-gray-400 border-dashed"></span>
                {{ __('Legacy') }}
            </span>
        </div>
    </div>

    <div class="flex">

        {{-- ===== LEFT STICKY COLUMN: line names + load % ===== --}}
        <div class="shrink-0 w-[200px] border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            {{-- Spacer matching the hour axis row --}}
            <div class="h-9 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60 flex items-center px-3">
                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">{{ __('Production line') }}</span>
            </div>

            @foreach($data['lines'] as $lineRow)
                <div class="h-[76px] border-b border-gray-100 dark:border-gray-700/60 px-3 py-2 flex flex-col justify-center"
                     data-line-id="{{ $lineRow['line']->id }}">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" title="{{ $lineRow['line']->name }}">
                        {{ $lineRow['line']->code ?? $lineRow['line']->name }}
                    </div>
                    <div class="flex items-center gap-1.5 mt-1">
                        <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full
                                        @if($lineRow['load_percent'] >= 90) bg-red-500
                                        @elseif($lineRow['load_percent'] >= 70) bg-amber-500
                                        @else bg-emerald-500 @endif"
                                 style="width: {{ $lineRow['load_percent'] }}%;"></div>
                        </div>
                        <span class="text-[10px] font-bold @if($lineRow['load_percent'] >= 90) text-red-600 @elseif($lineRow['load_percent'] >= 70) text-amber-600 @else text-emerald-600 @endif">
                            {{ $lineRow['load_percent'] }}%
                        </span>
                    </div>
                    <div class="text-[10px] text-gray-400 mt-0.5">
                        {{ floor($lineRow['used_minutes'] / 60) }}h {{ $lineRow['used_minutes'] % 60 }}m / 24h
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ===== RIGHT SCROLLABLE GRID: hour axis + lane rows ===== --}}
        <div class="flex-1 overflow-x-auto overflow-y-hidden hourly-scroll" x-ref="scroller">
            <div class="relative" style="width: {{ $totalWidth }}px;">

                {{-- Hour axis --}}
                <div class="flex h-9 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60 sticky top-0 z-20">
                    @for($h = 0; $h < 24; $h++)
                        <div class="hour-cell relative shrink-0 border-r border-gray-200 dark:border-gray-700 flex items-center justify-start pl-1.5"
                             style="width: {{ $hourPx }}px;">
                            <span class="text-[10px] font-semibold text-gray-500">{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}:00</span>
                            {{-- 15-min ticks (4 per hour at slot=15) --}}
                            <div class="absolute inset-y-0 left-[{{ $hourPx / 4 }}px] w-px bg-gray-200 dark:bg-gray-700/60"></div>
                            <div class="absolute inset-y-0 left-[{{ $hourPx / 2 }}px] w-px bg-gray-200 dark:bg-gray-700/60"></div>
                            <div class="absolute inset-y-0 left-[{{ ($hourPx * 3) / 4 }}px] w-px bg-gray-200 dark:bg-gray-700/60"></div>
                        </div>
                    @endfor
                </div>

                {{-- Lane rows --}}
                @foreach($data['lines'] as $lineRow)
                    <div class="lane relative border-b border-gray-100 dark:border-gray-700/60"
                         style="height: 76px;"
                         data-line-id="{{ $lineRow['line']->id }}"
                         @drop.prevent="onLaneDrop($event, {{ $lineRow['line']->id }})"
                         @dragover.prevent
                    >
                        {{-- Vertical hour gridlines --}}
                        @for($h = 1; $h < 24; $h++)
                            <div class="absolute top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700/60"
                                 style="left: {{ $h * $hourPx }}px;"></div>
                        @endfor

                        {{-- Shift boundary lines --}}
                        @foreach($shiftBoundaryMinutes as $boundary)
                            <div class="absolute top-0 bottom-0 w-px border-l border-dashed border-indigo-400/70 dark:border-indigo-400/50"
                                 style="left: {{ $boundary * $pxPerMinute }}px;"></div>
                        @endforeach

                        {{-- Now line (only when viewing today) --}}
                        @if($isToday)
                            <div class="now-line absolute top-0 bottom-0 w-0.5 bg-red-500 z-30 pointer-events-none"
                                 :style="`left: ${nowLineLeft}px; display: ${nowLineLeft === null ? 'none' : 'block'};`">
                                <div class="absolute -top-1 -left-1 w-2 h-2 rounded-full bg-red-500"></div>
                            </div>
                        @endif

                        {{-- Work order cards --}}
                        @foreach($lineRow['orders'] as $order)
                            @php
                                $wo = $order['wo'];
                                $continuesLeft = $order['start_minute'] === 0
                                    && $wo->planned_start_at
                                    && $wo->planned_start_at->lt($data['date']->copy()->startOfDay());
                                $continuesRight = $order['end_minute'] >= $data['minutes_per_day']
                                    && $wo->planned_end_at
                                    && $wo->planned_end_at->gt($data['date']->copy()->endOfDay());
                                $cardBase = 'bg-blue-200 border-blue-500 text-blue-900';
                                if ($order['has_conflict']) {
                                    $cardBase = 'bg-red-100 border-red-500 text-red-800';
                                } elseif ($order['is_legacy']) {
                                    $cardBase = 'bg-gray-200 border-gray-400 text-gray-700 border-dashed';
                                } elseif ($wo->status === 'IN_PROGRESS') {
                                    $cardBase = 'bg-amber-200 border-amber-500 text-amber-900 in-progress-pulse';
                                } elseif ($wo->status === 'DONE') {
                                    $cardBase = 'bg-green-200 border-green-500 text-green-900';
                                } elseif ($wo->status === 'BLOCKED') {
                                    $cardBase = 'bg-red-200 border-red-500 text-red-900';
                                }
                            @endphp
                            <div class="wo-card group absolute rounded border-2 shadow-sm px-1.5 py-1 overflow-visible cursor-grab active:cursor-grabbing select-none {{ $cardBase }}"
                                 style="left: {{ $order['start_minute'] * $pxPerMinute }}px;
                                        width: {{ max(20, $order['duration_minutes'] * $pxPerMinute) }}px;
                                        top: 6px;
                                        height: 60px;"
                                 data-wo-id="{{ $wo->id }}"
                                 data-line-id="{{ $lineRow['line']->id }}"
                                 data-start-minute="{{ $order['start_minute'] }}"
                                 data-duration-minutes="{{ $order['duration_minutes'] }}"
                                 data-is-legacy="{{ $order['is_legacy'] ? '1' : '0' }}"
                                 @mousedown="onCardMouseDown($event)"
                                 title="{{ $wo->order_no }} — {{ $wo->productType?->name ?? '' }}">

                                @if($continuesLeft)
                                    <span class="absolute left-0 top-1/2 -translate-y-1/2 -ml-0.5 text-[12px] leading-none text-gray-600">&laquo;</span>
                                @endif
                                @if($continuesRight)
                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] leading-none text-gray-600">&raquo;</span>
                                @endif

                                <div class="flex flex-col h-full pointer-events-none">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('admin.work-orders.show', $wo) }}"
                                           class="text-[11px] font-bold truncate pointer-events-auto hover:underline"
                                           @click.stop>
                                            {{ $wo->order_no }}
                                        </a>
                                        @if($order['has_conflict'])
                                            <span class="text-[9px] font-bold uppercase text-red-700">{{ __('Conflict') }}</span>
                                        @endif
                                        @if($order['is_legacy'])
                                            <span class="text-[9px] font-bold uppercase text-gray-600">{{ __('Legacy') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] truncate opacity-80">
                                        {{ $wo->productType->name ?? '-' }}
                                    </div>
                                    <div class="text-[9px] mt-auto opacity-70 truncate">
                                        @php
                                            $sh = (int) floor($order['start_minute'] / 60);
                                            $sm = $order['start_minute'] % 60;
                                            $eh = (int) floor($order['end_minute'] / 60);
                                            $em = $order['end_minute'] % 60;
                                        @endphp
                                        {{ sprintf('%02d:%02d', $sh, $sm) }} – {{ sprintf('%02d:%02d', $eh, $em) }}
                                        ({{ floor($order['duration_minutes'] / 60) }}h{{ $order['duration_minutes'] % 60 ? ' ' . ($order['duration_minutes'] % 60) . 'm' : '' }})
                                    </div>
                                </div>

                                {{-- Unassign button --}}
                                <button class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center
                                               opacity-0 group-hover:opacity-100 hover:!opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-20 pointer-events-auto"
                                        @click.stop.prevent="if(confirm({{ json_encode(__('Remove this order from schedule?')) }})) { saveOrder({{ $wo->id }}, { line_id: '', due_date: '', week_number: '', shift_number: '', planned_start_at: '', planned_end_at: '' }).then(() => refreshContent()) }"
                                        title="{{ __('Remove from schedule') }}">✕</button>

                                {{-- Right edge resize handle --}}
                                @if(! $order['is_legacy'])
                                    <div class="wo-resize-handle absolute right-0 top-0 bottom-0 w-1.5 cursor-ew-resize bg-black/0 hover:bg-black/20 rounded-r"
                                         @mousedown.stop="onResizeMouseDown($event)"></div>
                                @endif
                            </div>
                        @endforeach

                        @if($lineRow['orders']->isEmpty())
                            <div class="absolute inset-0 flex items-center justify-center text-[11px] text-gray-300 italic pointer-events-none">
                                {{ __('No scheduled orders') }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Drag/resize ghost element rendered into <body> via Alpine teleport --}}
    <template x-teleport="body">
        <div class="fixed inset-0 z-[60] pointer-events-none" x-show="active" x-cloak>
            <div class="absolute top-2 right-2 px-2 py-1 rounded bg-gray-900 text-white text-[11px] font-mono shadow-lg"
                 x-text="statusText"></div>
        </div>
    </template>
</div>

<style>
    .hourly-scroll::-webkit-scrollbar { height: 10px; }
    .hourly-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,.2); border-radius: 6px; }
    .hourly-scroll::-webkit-scrollbar-track { background: transparent; }

    .wo-card { transition: box-shadow .15s ease, transform .05s ease; }
    .wo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); z-index: 10; }
    .wo-card.dragging { z-index: 40; box-shadow: 0 8px 18px rgba(0,0,0,.18); opacity: .9; }
    .wo-card.resizing { z-index: 40; box-shadow: 0 8px 18px rgba(0,0,0,.18); }

    @keyframes in-progress-pulse-kf {
        0%, 100% { box-shadow: inset 0 0 0 0 rgba(245,158,11,0); }
        50%      { box-shadow: inset 0 0 12px 0 rgba(245,158,11,.4); }
    }
    .in-progress-pulse { animation: in-progress-pulse-kf 2s ease-in-out infinite; }
</style>

<script>
    function hourlyPlanner(opts) {
        return {
            slotMinutes: opts.slotMinutes,
            pxPerMinute: opts.pxPerMinute,
            dayStartIso: opts.dayStartIso,
            isToday: opts.isToday,
            updateUrl: opts.updateUrl,
            csrf: opts.csrf,

            // Interaction state
            active: false,            // any drag/resize in progress
            mode: null,               // 'move' | 'resize'
            card: null,               // current DOM card
            origLeft: 0,
            origWidth: 0,
            origStartMinute: 0,
            origDurationMinutes: 0,
            origLineId: null,
            startClientX: 0,
            startClientY: 0,
            statusText: '',

            // Now line
            nowLineLeft: null,
            nowTimer: null,

            init() {
                this.recomputeNow();
                if (this.isToday) {
                    this.nowTimer = setInterval(() => this.recomputeNow(), 60_000);
                    // Auto-scroll so the now-line is visible
                    this.$nextTick(() => {
                        const s = this.$refs.scroller;
                        if (! s || this.nowLineLeft === null) return;
                        s.scrollLeft = Math.max(0, this.nowLineLeft - 200);
                    });
                } else {
                    // Scroll to working hours (06:00)
                    this.$nextTick(() => {
                        const s = this.$refs.scroller;
                        if (s) s.scrollLeft = 6 * 60 * this.pxPerMinute;
                    });
                }

                window.addEventListener('mousemove', (e) => this.onMouseMove(e));
                window.addEventListener('mouseup', (e) => this.onMouseUp(e));
            },

            recomputeNow() {
                if (! this.isToday) {
                    this.nowLineLeft = null;
                    return;
                }
                const now = new Date();
                const minutes = now.getHours() * 60 + now.getMinutes();
                this.nowLineLeft = minutes * this.pxPerMinute;
            },

            snapToSlot(minute) {
                return Math.round(minute / this.slotMinutes) * this.slotMinutes;
            },

            // === MOVE ============================================================
            onCardMouseDown(e) {
                // Resize handle handles its own mousedown.stop
                if (e.button !== 0) return;
                const card = e.currentTarget;
                if (! card) return;
                this.mode = 'move';
                this.card = card;
                card.classList.add('dragging');
                this.origLeft = parseFloat(card.style.left) || 0;
                this.origWidth = parseFloat(card.style.width) || 0;
                this.origStartMinute = parseInt(card.dataset.startMinute, 10) || 0;
                this.origDurationMinutes = parseInt(card.dataset.durationMinutes, 10) || 0;
                this.origLineId = parseInt(card.dataset.lineId, 10) || null;
                this.startClientX = e.clientX;
                this.startClientY = e.clientY;
                this.active = true;
                this.statusText = this.fmtMinute(this.origStartMinute) + ' (move)';
                e.preventDefault();
            },

            // === RESIZE ==========================================================
            onResizeMouseDown(e) {
                if (e.button !== 0) return;
                const handle = e.currentTarget;
                const card = handle.closest('.wo-card');
                if (! card) return;
                this.mode = 'resize';
                this.card = card;
                card.classList.add('resizing');
                this.origLeft = parseFloat(card.style.left) || 0;
                this.origWidth = parseFloat(card.style.width) || 0;
                this.origStartMinute = parseInt(card.dataset.startMinute, 10) || 0;
                this.origDurationMinutes = parseInt(card.dataset.durationMinutes, 10) || 0;
                this.origLineId = parseInt(card.dataset.lineId, 10) || null;
                this.startClientX = e.clientX;
                this.startClientY = e.clientY;
                this.active = true;
                this.statusText = this.origDurationMinutes + 'm (resize)';
                e.preventDefault();
            },

            onMouseMove(e) {
                if (! this.active || ! this.card) return;
                const dx = e.clientX - this.startClientX;
                if (this.mode === 'move') {
                    const newLeft = this.origLeft + dx;
                    // Clamp to grid [0, totalWidth - width]
                    const maxLeft = (24 * 60 * this.pxPerMinute) - this.origWidth;
                    const clamped = Math.max(0, Math.min(maxLeft, newLeft));
                    this.card.style.left = clamped + 'px';
                    const minute = this.snapToSlot(clamped / this.pxPerMinute);
                    this.statusText = this.fmtMinute(minute) + ' (' + this.origDurationMinutes + 'm)';
                } else if (this.mode === 'resize') {
                    const newWidth = this.origWidth + dx;
                    // Minimum width = one slot
                    const minWidth = this.slotMinutes * this.pxPerMinute;
                    // Maximum: stay within day
                    const maxWidth = (24 * 60 * this.pxPerMinute) - this.origLeft;
                    const clamped = Math.max(minWidth, Math.min(maxWidth, newWidth));
                    this.card.style.width = clamped + 'px';
                    const duration = this.snapToSlot(clamped / this.pxPerMinute);
                    this.statusText = this.fmtMinute(this.origStartMinute) + ' (' + duration + 'm)';
                }
            },

            async onMouseUp(e) {
                if (! this.active || ! this.card) return;
                const card = this.card;
                const mode = this.mode;
                this.active = false;

                if (mode === 'move') {
                    card.classList.remove('dragging');
                    // Snap left to grid
                    const rawMinute = (parseFloat(card.style.left) || 0) / this.pxPerMinute;
                    const newStartMinute = Math.max(0, Math.min(24 * 60 - this.origDurationMinutes, this.snapToSlot(rawMinute)));
                    card.style.left = (newStartMinute * this.pxPerMinute) + 'px';

                    // Detect target line via elementFromPoint
                    let targetLineId = this.origLineId;
                    const under = document.elementFromPoint(e.clientX, e.clientY);
                    if (under) {
                        const laneEl = under.closest('.lane');
                        if (laneEl && laneEl.dataset.lineId) {
                            targetLineId = parseInt(laneEl.dataset.lineId, 10);
                        }
                    }

                    const startUnchanged = newStartMinute === this.origStartMinute;
                    const lineUnchanged = targetLineId === this.origLineId;
                    if (startUnchanged && lineUnchanged) {
                        this.cleanup();
                        return;
                    }

                    await this.saveMove(card, newStartMinute, this.origDurationMinutes, targetLineId, false);
                } else if (mode === 'resize') {
                    card.classList.remove('resizing');
                    const rawDuration = (parseFloat(card.style.width) || 0) / this.pxPerMinute;
                    const snapped = Math.max(this.slotMinutes, this.snapToSlot(rawDuration));
                    const startMinute = this.origStartMinute;
                    const newDuration = Math.min(snapped, 24 * 60 - startMinute);
                    card.style.width = (newDuration * this.pxPerMinute) + 'px';

                    if (newDuration === this.origDurationMinutes) {
                        this.cleanup();
                        return;
                    }
                    await this.saveResize(card, startMinute, newDuration, false);
                }

                this.cleanup();
            },

            cleanup() {
                this.card = null;
                this.mode = null;
                this.active = false;
                this.statusText = '';
            },

            // === API =============================================================
            toIso(minute) {
                const d = new Date(this.dayStartIso);
                d.setMinutes(d.getMinutes() + minute);
                return d.toISOString();
            },

            async saveMove(card, newStartMinute, durationMinutes, targetLineId, force) {
                const woId = card.dataset.woId;
                const startAt = this.toIso(newStartMinute);
                const endAt = this.toIso(newStartMinute + durationMinutes);
                const body = {
                    planned_start_at: startAt,
                    planned_end_at: endAt,
                    line_id: targetLineId,
                };
                if (force) body.force_conflict = 1;

                try {
                    const r = await fetch(`${this.updateUrl}/${woId}`, {
                        method: 'PUT',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                    if (r.status === 409) {
                        const d = await r.json().catch(() => ({}));
                        if (confirm((d.message || 'Conflict detected.') + '\n\nSave anyway?')) {
                            return this.saveMove(card, newStartMinute, durationMinutes, targetLineId, true);
                        }
                        location.reload();
                        return;
                    }
                    if (! r.ok) {
                        alert('Save failed (' + r.status + ').');
                        location.reload();
                        return;
                    }
                    // Persist new dataset values so subsequent drags use them
                    card.dataset.startMinute = newStartMinute;
                    card.dataset.lineId = targetLineId;
                    // If line changed, reload so the card moves into the right lane
                    if (targetLineId !== this.origLineId) {
                        location.reload();
                    }
                } catch (err) {
                    alert('Network error: ' + err.message);
                    location.reload();
                }
            },

            async saveResize(card, startMinute, newDuration, force) {
                const woId = card.dataset.woId;
                const startAt = this.toIso(startMinute);
                const endAt = this.toIso(startMinute + newDuration);
                const body = {
                    planned_start_at: startAt,
                    planned_end_at: endAt,
                };
                if (force) body.force_conflict = 1;

                try {
                    const r = await fetch(`${this.updateUrl}/${woId}/resize`, {
                        method: 'PUT',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                    if (r.status === 409) {
                        const d = await r.json().catch(() => ({}));
                        if (confirm((d.message || 'Conflict detected.') + '\n\nSave anyway?')) {
                            return this.saveResize(card, startMinute, newDuration, true);
                        }
                        location.reload();
                        return;
                    }
                    if (! r.ok) {
                        alert('Save failed (' + r.status + ').');
                        location.reload();
                        return;
                    }
                    card.dataset.durationMinutes = newDuration;
                } catch (err) {
                    alert('Network error: ' + err.message);
                    location.reload();
                }
            },

            // === Backlog drop (placeholder — accepts dragstart from backlog cards)
            onLaneDrop(e, lineId) {
                // The existing backlog cards dispatch their own assign flow.
                // We swallow native drop so the browser doesn't navigate.
            },

            fmtMinute(min) {
                const h = Math.floor(min / 60);
                const m = min % 60;
                return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            },
        };
    }
</script>
