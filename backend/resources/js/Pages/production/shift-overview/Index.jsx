import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AppLayout from '../../../layouts/AppLayout';
import PageTitle from '../../../components/PageTitle';
import { SEGMENT_COLOR, pct, ratioColor, scoreColor, segmentColor } from '../../../components/shift-monitor/tokens';
import { echo } from '../../../lib/echo';
import { apiGet } from '../../../lib/http';
import { __, formatNumber } from '../../../lib/i18n';

/**
 * One line's machines side by side for the running shift.
 *
 * The shift monitor explains one machine in depth; this shows which machine
 * needs explaining. Each row is the whole shift end to end, so two stations are
 * comparable at a glance, and clicking one opens it in the monitor.
 *
 * The rows are deliberately not clickable *segments*: at a shift per row a
 * two-minute stop is a few pixels, which is enough to see and not enough to
 * aim at. Classifying stops stays on the detail screen where a stop is 50px
 * wide.
 *
 * Live the same way the monitor is: every station on the line pushes on its own
 * `shift-monitor.{id}` channel and a burst of them coalesces into one re-fetch.
 */

/** Longest a burst of nudges waits before one re-fetch covers all of them. */
const COALESCE_MS = 600;

/** Safety net for a socket that died without saying so. */
const FALLBACK_POLL_MS = 45000;

export default function ShiftOverviewIndex() {
    const page = usePage().props;
    const { lines = [], basePath, monitorPath } = page;

    const [snapshot, setSnapshot] = useState(page.snapshot ?? null);
    const [lineId, setLineId] = useState(page.selected?.lineId ?? null);

    const lineRef = useRef(lineId);
    lineRef.current = lineId;

    const inFlight = useRef(false);
    const refresh = useCallback(async (targetLine) => {
        if (inFlight.current) return;
        inFlight.current = true;

        const params = new URLSearchParams();
        const line = targetLine ?? lineRef.current;
        if (line) params.set('line', line);

        try {
            const res = await apiGet(`${basePath}/check?${params}`);
            if (!res.ok) return;
            const json = await res.json();
            if (json.data) setSnapshot(json.data);
        } catch (_) {
            // Keep the last good snapshot — a dropped fetch shouldn't blank a
            // screen somebody is watching the line on.
        } finally {
            inFlight.current = false;
        }
    }, [basePath]);

    // One channel per station on the line. A line-wide push would need a
    // channel of its own and an authorisation rule to go with it; the per
    // station ones already exist and already carry exactly this news.
    const stationIds = useMemo(() => snapshot?.stationIds ?? [], [snapshot?.stationIds]);
    const stationKey = stationIds.join(',');

    const coalesceTimer = useRef(null);
    const scheduleRefresh = useCallback(() => {
        if (coalesceTimer.current) return;
        coalesceTimer.current = setTimeout(() => {
            coalesceTimer.current = null;
            refresh();
        }, COALESCE_MS);
    }, [refresh]);

    useEffect(() => () => clearTimeout(coalesceTimer.current), []);

    useEffect(() => {
        if (!stationIds.length) return undefined;

        const names = stationIds.map((id) => `shift-monitor.${id}`);
        names.forEach((name) => echo.private(name).listen('.changed', scheduleRefresh));

        return () => names.forEach((name) => echo.leave(name));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stationKey, scheduleRefresh]);

    useEffect(() => {
        const id = setInterval(() => refresh(), FALLBACK_POLL_MS);
        return () => clearInterval(id);
    }, [refresh]);

    const pickLine = useCallback((id) => {
        setLineId(id);
        refresh(id);
    }, [refresh]);

    const openStation = useCallback((station) => {
        router.visit(`${monitorPath}?workstation=${station.id}`);
    }, [monitorPath]);

    if (!snapshot) {
        return (
            <AppLayout>
                <Head title={__('Line overview')} />
                <PageTitle>{__('Line overview')}</PageTitle>
                <p className="rounded-om border border-om-line bg-om-card px-5 py-8 text-center text-sm text-om-muted">
                    {__('No production line is configured yet.')}
                </p>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title={__('Line overview')} />
            <PageTitle>{__('Line overview')}</PageTitle>

            <div className="flex h-full min-h-0 flex-col">
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden border border-om-line bg-om-card">
                    <header className="flex flex-shrink-0 flex-wrap items-center gap-3 border-b border-om-line2 px-5 py-3">
                        <div className="flex flex-wrap gap-1 rounded-om-sm border border-om-line2 bg-om-bg p-1">
                            {lines.map((line) => (
                                <button
                                    key={line.id}
                                    type="button"
                                    onClick={() => pickLine(line.id)}
                                    className={`rounded-[7px] px-3 py-1.5 text-[12.5px] font-medium ${
                                        line.id === lineId ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
                                    }`}
                                >
                                    {line.name}
                                </button>
                            ))}
                        </div>

                        <div className="ml-auto text-right">
                            <div className="font-mono text-[8.5px] uppercase tracking-[0.12em] text-om-faint">
                                {__('Shift')}
                            </div>
                            <div className="text-[13.5px] font-semibold text-om-ink">
                                {snapshot.shift.label}
                                <span className="ml-2 font-mono text-[10px] font-normal text-om-faint">
                                    {snapshot.shift.window}
                                </span>
                            </div>
                        </div>
                    </header>

                    <div className="flex flex-shrink-0 items-center border-b border-om-line2 bg-om-panel">
                        <span className="w-[188px] flex-shrink-0 px-4 py-1.5 font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                            {__('Station')}
                        </span>
                        <span className="min-w-0 flex-1" />
                        <span className="w-[112px] flex-shrink-0 text-center font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                            {__('Actual / target')}
                        </span>
                        <span className="w-[74px] flex-shrink-0 text-center font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                            {__('OEE')}
                        </span>
                        <span className="w-[86px] flex-shrink-0 text-center font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                            {__('No cause')}
                        </span>
                    </div>

                    {/* Fixed row height, scrolling past about eight machines.
                        Stretching four stations over a whole screen makes each
                        bar taller without saying anything more, and a row's
                        height carries no meaning — its length does. */}
                    <div className="flex min-h-0 flex-1 flex-col overflow-y-auto">
                        {snapshot.stations.map((station) => (
                            <StationRow key={station.id} station={station} onOpen={() => openStation(station)} />
                        ))}
                    </div>
                    <div className="flex flex-shrink-0 flex-wrap items-center gap-x-[18px] gap-y-1.5 border-t border-om-line2 bg-om-panel px-[18px] py-2.5">
                        <Legend />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function StationRow({ station, onOpen }) {
    const ratio = station.target ? station.produced / station.target : null;

    return (
        <button
            type="button"
            onClick={onOpen}
            title={__('Open :station in the shift monitor', { station: station.code })}
            className="flex h-[68px] flex-shrink-0 items-stretch border-b border-om-line2 text-left hover:bg-om-ink/[0.03]"
        >
            <span className="flex w-[188px] flex-shrink-0 flex-col justify-center gap-0.5 border-r border-om-line2 px-4">
                <span className="flex items-center gap-2">
                    <StateDot state={station.state} />
                    <span className="truncate text-[13.5px] font-semibold text-om-ink">{station.code}</span>
                </span>
                <span className="truncate font-mono text-[9.5px] text-om-faint">{station.name}</span>
            </span>

            <span className="relative flex min-w-0 flex-1 flex-col justify-center px-2">
                <span className="relative block h-[34px] overflow-hidden rounded-[3px] bg-om-track">
                    {station.segments.map((segment) => (
                        <span
                            key={segment.key}
                            className="absolute inset-y-0 block"
                            style={{
                                left: pct(segment.from, station.span),
                                width: pct(segment.minutes, station.span),
                                background: segmentColor(segment),
                            }}
                        />
                    ))}
                    {/* Where the shift has got to, so a short bar reads as
                        "early in the shift" rather than as a dead machine. */}
                    <span
                        className="absolute inset-y-0 z-[2] w-0.5 bg-om-accent"
                        style={{ left: pct(station.elapsed, station.span) }}
                    />
                </span>
            </span>

            <span className="flex w-[112px] flex-shrink-0 flex-col items-center justify-center border-l border-om-line2">
                <span className="font-mono text-[13px] font-semibold" style={{ color: ratioColor(ratio) }}>
                    {formatNumber(station.produced)}
                </span>
                <span className="font-mono text-[9.5px] text-om-faint">
                    {station.target === null ? '—' : `/ ${formatNumber(station.target)}`}
                </span>
            </span>

            <span className="flex w-[74px] flex-shrink-0 items-center justify-center border-l border-om-line2">
                <span className="font-mono text-[15px] font-semibold" style={{ color: scoreColor(station.oee) }}>
                    {station.oee === null ? '—' : `${station.oee}%`}
                </span>
            </span>

            <span className="flex w-[86px] flex-shrink-0 items-center justify-center border-l border-om-line2">
                {station.unclassified > 0 ? (
                    <span className="min-w-6 rounded-full bg-om-blocked px-2 py-0.5 text-center font-mono text-[11px] font-semibold text-white">
                        {station.unclassified}
                    </span>
                ) : (
                    <span className="font-mono text-[11px] text-om-faint">—</span>
                )}
            </span>
        </button>
    );
}

/** Reported state as a colour, or hollow when the station has reported nothing. */
function StateDot({ state }) {
    if (!state) {
        return <span className="h-2 w-2 flex-shrink-0 rounded-full border border-om-faint" title={__('No data recorded')} />;
    }

    const color = ['RUNNING'].includes(state)
        ? SEGMENT_COLOR.run
        : ['STOPPED', 'FAULT', 'WAITING'].includes(state)
            ? SEGMENT_COLOR.downUnclassified
            : ['CLEANING', 'MAINTENANCE', 'SETUP'].includes(state)
                ? SEGMENT_COLOR.plan
                : 'var(--om-faint)';

    return <span className="h-2 w-2 flex-shrink-0 rounded-full" style={{ background: color }} title={__(state)} />;
}

function Legend() {
    const items = [
        [__('Running'), SEGMENT_COLOR.run],
        [__('Reduced speed'), SEGMENT_COLOR.slow],
        [__('Stop — needs a reason'), SEGMENT_COLOR.downUnclassified],
        [__('Stop — reason set'), SEGMENT_COLOR.down],
        [__('Planned / changeover'), SEGMENT_COLOR.plan],
        [__('No data recorded'), SEGMENT_COLOR.none],
    ];

    return (
        <>
            <span className="font-mono text-[9px] uppercase tracking-[0.12em] text-om-faint">{__('States')}</span>
            {items.map(([label, background]) => (
                <span key={label} className="flex items-center gap-[6px] text-[11px] text-om-muted">
                    <span className="h-[11px] w-[18px] rounded-[3px] border border-om-line" style={{ background }} />
                    {label}
                </span>
            ))}
            <span className="ml-auto font-mono text-[10px] text-om-faint">
                {__('click a station to open its shift')}
            </span>
        </>
    );
}
