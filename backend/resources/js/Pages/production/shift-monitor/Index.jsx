import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

import AppLayout from '../../../layouts/AppLayout';
import PageTitle from '../../../components/PageTitle';
import AnalysisView from '../../../components/shift-monitor/AnalysisView';
import HourRow from '../../../components/shift-monitor/HourRow';
import StopPanel from '../../../components/shift-monitor/StopPanel';
import TopStrip from '../../../components/shift-monitor/TopStrip';
import { SEGMENT_COLOR } from '../../../components/shift-monitor/tokens';
import { echo } from '../../../lib/echo';
import { apiCall, apiGet } from '../../../lib/http';
import { __, formatNumber } from '../../../lib/i18n';

/**
 * Live shift monitor — one station's shift as it happens.
 *
 * The screen is one server-computed snapshot. Deriving the timeline in the
 * browser would mean shipping the raw state slices and counter events and
 * re-implementing the segment maths in a second language, so the shaped payload
 * is fetched whole and every panel agrees by construction.
 *
 * Updates are pushed, not polled: the station's Reverb channel carries a nudge
 * whenever its machine state, counters or stops move (ShiftMonitorChanged), and
 * the page re-fetches. Three things fall out of that:
 *
 *  - nudges are coalesced, since a busy line can emit several a second and the
 *    snapshot only needs fetching once per burst;
 *  - a re-fetch on every (re)subscribe catches whatever landed while the socket
 *    was down, the same guarantee realtimeCollection gives its collections;
 *  - a slow interval stays as a safety net, so a silently dead socket costs
 *    freshness rather than the screen.
 *
 * Refreshing pauses while a stop drawer is open, so an update can't move the
 * segment out from under someone mid-decision.
 */

/** Longest a burst of nudges waits before one re-fetch covers all of them. */
const COALESCE_MS = 400;

/** Safety net for a socket that died without saying so. */
const FALLBACK_POLL_MS = 30000;

/**
 * A Date as the YYYY-MM-DD the viewer sees, not the one UTC sees.
 *
 * `toISOString().slice(0, 10)` is the obvious way to do this and is wrong for
 * anyone far enough from UTC that local noon falls on another UTC day.
 */
function localDate(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

export default function ShiftMonitorIndex() {
    const page = usePage().props;
    const { stations = [], basePath, reasonGroups = [] } = page;

    const [snapshot, setSnapshot] = useState(page.snapshot ?? null);
    const [selected, setSelected] = useState(page.selected ?? null);
    const [view, setView] = useState('live');
    const [metric, setMetric] = useState('rate');
    const [openSegment, setOpenSegment] = useState(null);
    const [toast, setToast] = useState(null);
    // Refs, not deps: the poll loop must see current values without being torn
    // down and rebuilt on every tick.
    const selectedRef = useRef(selected);
    selectedRef.current = selected;
    const pausedRef = useRef(false);
    pausedRef.current = openSegment !== null;

    const toastTimer = useRef(null);
    const fire = useCallback((message) => {
        clearTimeout(toastTimer.current);
        setToast(message);
        toastTimer.current = setTimeout(() => setToast(null), 2800);
    }, []);

    // One snapshot in flight at a time. Four things ask for a refresh (a nudge,
    // the fallback interval, a re-subscribe, and each mutation), and the request
    // is expensive enough that overlapping them wastes a full round trip and can
    // apply an older snapshot last.
    const inFlight = useRef(false);
    const pending = useRef(false);

    const refresh = useCallback(async function run(target) {
        if (inFlight.current) {
            pending.current = true;
            return;
        }
        inFlight.current = true;

        const params = new URLSearchParams();
        const next = target ?? selectedRef.current;
        if (next?.workstationId) params.set('workstation', next.workstationId);
        if (next?.shiftId) params.set('shift', next.shiftId);
        if (next?.date) params.set('date', next.date);

        try {
            const res = await apiGet(`${basePath}/check?${params}`);
            if (!res.ok) return;
            const json = await res.json();
            if (json.data) {
                setSnapshot(json.data);
                if (json.selected) setSelected(json.selected);
            }
        } catch (_) {
            // Keep the last good snapshot — a dropped fetch shouldn't blank the
            // screen someone is watching the line on.
        } finally {
            inFlight.current = false;
            if (pending.current) {
                pending.current = false;
                run();
            }
        }
    }, [basePath]);

    // Nudges arrive per change; a burst of them needs one re-fetch, not one
    // each. Trailing edge, so the fetch sees the last change in the burst.
    const coalesceTimer = useRef(null);
    const scheduleRefresh = useCallback(() => {
        if (coalesceTimer.current) return;
        coalesceTimer.current = setTimeout(() => {
            coalesceTimer.current = null;
            if (!pausedRef.current) refresh();
        }, COALESCE_MS);
    }, [refresh]);

    useEffect(() => () => clearTimeout(coalesceTimer.current), []);

    // Subscribe to the station being watched. Re-runs when the station changes,
    // leaving the previous channel — stepping through stations must not leave a
    // trail of live subscriptions behind it.
    const workstationId = selected?.workstationId;
    useEffect(() => {
        if (!workstationId) return undefined;

        const name = `shift-monitor.${workstationId}`;
        const channel = echo.private(name);
        channel.listen('.changed', scheduleRefresh);
        // Also on reconnect: the socket may have missed changes while it was down.
        channel.subscribed(() => { if (!pausedRef.current) refresh(); });

        return () => echo.leave(name);
    }, [workstationId, scheduleRefresh, refresh]);

    useEffect(() => {
        const id = setInterval(() => {
            if (!pausedRef.current) refresh();
        }, FALLBACK_POLL_MS);
        return () => clearInterval(id);
    }, [refresh]);

    useEffect(() => () => clearTimeout(toastTimer.current), []);

    // Pins land on the hour row they belong to, so each row only walks its own.
    const pinsByHour = useMemo(() => {
        const map = new Map();
        (snapshot?.events ?? []).forEach((pin) => {
            const list = map.get(pin.hour) ?? [];
            list.push(pin);
            map.set(pin.hour, list);
        });
        return map;
    }, [snapshot?.events]);

    // The open drawer tracks the live snapshot: classify a stop and the drawer
    // shows the new cause rather than the state it was opened with.
    const liveSegment = useMemo(() => {
        if (!openSegment || !snapshot) return openSegment;
        for (const hour of snapshot.hours) {
            const match = hour.segments.find((s) => s.key === openSegment.key);
            if (match) return match;
        }
        return openSegment;
    }, [openSegment, snapshot]);

    const step = useCallback((delta) => {
        if (stations.length === 0) return;
        const index = stations.findIndex((s) => s.id === selectedRef.current?.workstationId);
        const next = stations[(index + delta + stations.length) % stations.length];
        // Shift ids belong to a line, so moving station drops the pinned shift
        // and lets the server resolve the one that station is actually running.
        const target = { workstationId: next.id, shiftId: null, date: selectedRef.current?.date ?? null };
        setSelected(target);
        setOpenSegment(null);
        refresh(target);
        fire(`${next.code} · ${next.name}`);
    }, [stations, refresh, fire]);

    const stepShift = useCallback((delta) => {
        const current = selectedRef.current;
        if (!current?.date) return;
        // Built and read back in local time throughout. Round-tripping through
        // toISOString() reads the date in UTC, which lands on the previous day
        // for anyone east of UTC+12 — the arrows then appear to do nothing.
        const date = new Date(`${current.date}T12:00:00`);
        date.setDate(date.getDate() + delta);
        const target = { ...current, date: localDate(date) };
        setSelected(target);
        setOpenSegment(null);
        refresh(target);
    }, [refresh]);

    const jumpLive = useCallback(() => {
        const target = { workstationId: selectedRef.current?.workstationId, shiftId: null, date: null };
        setSelected(target);
        setOpenSegment(null);
        refresh(target);
        fire(__('Jumped to now'));
    }, [refresh, fire]);

    const post = useCallback(async (url, body) => {
        const res = await apiCall(url, 'POST', body);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(json.message ?? __('Something went wrong.'));
        }
        return json;
    }, []);

    const classify = useCallback(async (segment, reason, notes) => {
        try {
            const json = await post(`${basePath}/downtimes/${segment.downtimeId}/classify`, {
                downtime_reason_id: reason.id,
                notes: notes || null,
                // What this drawer believed when it opened. Refreshes pause
                // while it is open, so the server checks nobody has explained
                // the stop in the meantime rather than letting the later click
                // quietly overwrite the earlier decision.
                seen_classified_at: segment.classifiedAt ?? null,
            });
            setOpenSegment(null);
            await refresh();
            fire(json.message);
        } catch (e) {
            fire(e.message);
        }
    }, [post, basePath, refresh, fire]);

    const escalate = useCallback(async (segment, notes) => {
        try {
            const json = await post(`${basePath}/downtimes/${segment.downtimeId}/escalate`, {
                note: notes || null,
            });
            setOpenSegment(null);
            await refresh();
            fire(json.message);
        } catch (e) {
            fire(e.message);
        }
    }, [post, basePath, refresh, fire]);

    const openAttention = useCallback(() => {
        const key = snapshot?.attention?.first?.key;
        if (!key) return;
        for (const hour of snapshot.hours) {
            const match = hour.segments.find((s) => s.key === key);
            if (match) {
                setOpenSegment(match);
                return;
            }
        }
    }, [snapshot]);

    if (!snapshot) {
        return (
            <AppLayout>
                <Head title={__('Shift monitor')} />
                <PageTitle>{__('Shift monitor')}</PageTitle>
                <p className="rounded-om border border-om-line bg-om-card px-5 py-8 text-center text-sm text-om-muted">
                    {__('No workstation has reported any machine state yet.')}
                </p>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title={__('Shift monitor')} />
            <PageTitle>{__('Shift monitor')}</PageTitle>

            {/* The whole screen is meant to be read at a glance from across a
                shop floor, so it is laid out to the viewport rather than to its
                content: everything above the timeline keeps its natural height
                and the hour rows absorb the rest. Nothing scrolls. */}
            <div className="flex h-full min-h-0 flex-col">
            <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden border border-om-line2 bg-om-card">
                <TopStrip
                    snapshot={snapshot}
                    metric={metric}
                    onMetric={setMetric}
                    onStationStep={step}
                    onShiftStep={stepShift}
                    onJumpLive={jumpLive}
                    onOpenAttention={openAttention}
                />

                {view === 'live' ? (
                    <div className="flex min-h-0 flex-1 flex-col">
                        <div className="flex items-center border-b border-om-line2 bg-om-panel">
                            <span className="w-11 flex-shrink-0" />
                            <div className="relative h-6 min-w-0 flex-1">
                                {[15, 30, 45].map((minute) => (
                                    <span
                                        key={minute}
                                        className="absolute top-1.5 -translate-x-1/2 font-mono text-[9px] text-om-faint"
                                        style={{ left: `${(minute / 60) * 100}%` }}
                                    >
                                        :{minute}
                                    </span>
                                ))}
                            </div>
                            <span className="w-[104px] flex-shrink-0 text-center font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                                {__('Actual / target')}
                            </span>
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col">
                            {snapshot.hours.map((hour) => (
                                <HourRow
                                    key={hour.key}
                                    hour={hour}
                                    pins={pinsByHour.get(hour.from) ?? []}
                                    selectedKey={openSegment?.key}
                                    onSelectSegment={setOpenSegment}
                                />
                            ))}
                        </div>

                        <SummaryBar snapshot={snapshot} onOpenAttention={openAttention} onNote={fire} />
                    </div>
                ) : (
                    <div className="min-h-0 flex-1 overflow-auto">
                        <AnalysisView analysis={snapshot.analysis} />
                    </div>
                )}

                {/* Rendered outside the live/analysis branch, not inside the
                    summary bar it sits next to: that bar only exists in the
                    live view, and putting the switch there would strand anyone
                    who opened Analysis with no way back. */}
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-om-line2 bg-om-panel px-[18px] py-2.5">
                    <Legend />
                    <div className="ml-auto flex gap-1 rounded-om-sm border border-om-line bg-om-card p-1">
                        {[['live', __('Live shift')], ['analysis', __('Analysis')]].map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => { setView(key); setOpenSegment(null); }}
                                className={`rounded-[7px] px-[15px] py-[7px] text-[12.5px] font-medium ${
                                    view === key ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {liveSegment && (
                    <StopPanel
                        segment={liveSegment}
                        reasonGroups={reasonGroups}
                        idealRatePerMinute={snapshot.chart.idealRatePerMinute}
                        // Catch up on whatever was nudged while the drawer held
                        // refreshes off, instead of waiting for the safety net.
                        onClose={() => { setOpenSegment(null); refresh(); }}
                        onClassify={classify}
                        onEscalate={escalate}
                    />
                )}

                {toast && (
                    <div
                        role="status"
                        className="absolute bottom-[22px] left-1/2 z-[60] flex -translate-x-1/2 items-center gap-3 rounded-om bg-om-ink px-[22px] py-[13px] shadow-[0_18px_44px_-18px_rgba(0,0,0,0.6)]"
                    >
                        <span className="h-2 w-2 rounded-full bg-om-running" />
                        <span className="text-[13.5px] text-om-on-ink">{toast}</span>
                    </div>
                )}
            </div>

            </div>
        </AppLayout>
    );
}

function SummaryBar({ snapshot, onOpenAttention, onNote }) {
    const { summary } = snapshot;

    const items = [
        {
            key: 'changeover',
            label: __('Product changeover'),
            count: summary.changeovers,
            color: 'var(--om-maint)',
            onClick: () => onNote(__('Changeovers: :count · :minutes min total', {
                count: summary.changeovers,
                minutes: summary.changeoverMinutes,
            })),
        },
        {
            key: 'downtime',
            label: __('Unclassified stops'),
            count: summary.unclassified,
            color: 'var(--om-blocked)',
            urgent: summary.unclassified > 0,
            onClick: () => (summary.unclassified > 0 ? onOpenAttention() : onNote(__('All stops classified'))),
        },
        {
            key: 'speed',
            label: __('Speed loss'),
            count: summary.speedLossMinutes,
            color: 'var(--om-downtime)',
            onClick: () => onNote(__(':minutes min at reduced speed', { minutes: summary.speedLossMinutes })),
        },
        {
            key: 'scrap',
            label: __('Scrap'),
            count: summary.scrap,
            color: 'var(--om-blocked)',
            onClick: () => onNote(__(':count pcs scrap this shift', { count: summary.scrap })),
        },
    ];

    return (
        <div className="flex flex-wrap items-center bg-om-panel">
            {summary.operator && (
                <div className="flex items-center gap-2.5 border-r border-om-line2 px-[18px] py-[13px]">
                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-om-chip text-[9.5px] font-semibold text-om-ink">
                        {summary.operator.split(' ').map((w) => w[0]).slice(0, 2).join('')}
                    </span>
                    <span className="text-[12.5px] font-medium text-om-ink">{summary.operator}</span>
                </div>
            )}
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    onClick={item.onClick}
                    className="flex items-center gap-2.5 border-r border-om-line2 px-5 py-[13px] hover:bg-om-ink/[0.03]"
                >
                    <span className="h-2.5 w-2.5 rounded-[3px]" style={{ background: item.color }} />
                    <span className="text-[12.5px] text-om-muted">{item.label}</span>
                    <span
                        className="min-w-5 rounded-full px-[7px] py-0.5 text-center font-mono text-[10px] font-semibold"
                        style={item.urgent
                            ? { background: 'var(--om-blocked)', color: '#fff' }
                            : { background: 'var(--om-chip)', color: 'var(--om-muted)' }}
                    >
                        {formatNumber(item.count)}
                    </span>
                </button>
            ))}
            <div className="ml-auto px-[18px] py-[13px] font-mono text-[10px] text-om-faint">
                {__('live · pushed')}
            </div>
        </div>
    );
}

function Legend() {
    const items = [
        [__('Running'), SEGMENT_COLOR.run, false],
        [__('Reduced speed'), SEGMENT_COLOR.slow, false],
        [__('Stop — needs a reason'), SEGMENT_COLOR.downUnclassified, false],
        [__('Stop — reason set'), SEGMENT_COLOR.down, false],
        [__('Planned / changeover'), SEGMENT_COLOR.plan, false],
        [__('Not scheduled'), 'var(--om-track)', true],
        // The hatched one has to be named, or it reads as a rendering artefact
        // rather than as the machine having reported nothing for those minutes.
        [__('No data recorded'), SEGMENT_COLOR.none, true],
    ];

    return (
        <div className="flex flex-wrap items-center gap-x-[18px] gap-y-1.5">
            <span className="font-mono text-[9px] uppercase tracking-[0.12em] text-om-faint">{__('States')}</span>
            {items.map(([label, color, outlined]) => (
                <span key={label} className="flex items-center gap-[6px] text-[11px] text-om-muted">
                    <span
                        className="h-[11px] w-[18px] rounded-[3px]"
                        style={{ background: color, border: outlined ? '1px solid var(--om-line)' : undefined }}
                    />
                    {label}
                </span>
            ))}
        </div>
    );
}
