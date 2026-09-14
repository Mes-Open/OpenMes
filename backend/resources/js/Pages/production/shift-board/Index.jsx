import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import AppLayout from '../../../layouts/AppLayout';
import PageTitle from '../../../components/PageTitle';
import { echo } from '../../../lib/echo';
import { apiGet } from '../../../lib/http';
import { __ } from '../../../lib/i18n';
import { STATE_TONE, elapsedLabel, isDowntime, stateLabel } from '../../../components/shift-board/tokens';

/**
 * The whole plant, one tile per machine.
 *
 * Read from across the room, not clicked through: the question is "what is
 * stopped and why", so a tile carries a colour, a cause and a clock, and
 * nothing that needs a cursor to interpret. The line overview stays the screen
 * for comparing machines on one line; this one is the board above the floor.
 *
 * Flat on purpose — no grouping by line. Grouping turns a scoreboard into a
 * structure tree that has to be read before the red tile is found. The line
 * name sits on the tile instead, where it matters once you have spotted one.
 *
 * Live the same way the overview is: each station pushes on its own
 * `shift-monitor.{id}` channel and a burst coalesces into one re-fetch.
 */

/** Longest a burst of nudges waits before one re-fetch covers all of them. */
const COALESCE_MS = 600;

/** Safety net for a socket that died without saying so. */
const FALLBACK_POLL_MS = 45000;

/** How often the tile clocks advance. They count seconds, so once a second. */
const TICK_MS = 1000;

/**
 * How long the board may go without a successful refresh before it says so.
 *
 * The failure this exists for is silent: an expired session, a backend restart
 * or a proxy dropping the poll leaves every tile frozen on its last good state,
 * and a frozen board is indistinguishable from a calm plant. Generous enough
 * that one missed poll is not an alarm — two consecutive ones are.
 */
const STALE_MS = 3 * FALLBACK_POLL_MS;

export default function ShiftBoardIndex() {
    const page = usePage().props;
    const { basePath, monitorPath, kiosk = false } = page;

    const [snapshot, setSnapshot] = useState(page.snapshot ?? null);

    // The query string is the kiosk's configuration — it is what gets pasted
    // into a wall display, so the page follows it rather than holding its own
    // idea of which lines are shown.
    const linesParam = page.selected?.lineIds;
    const linesQuery = useMemo(
        () => (Array.isArray(linesParam) ? linesParam.join(',') : ''),
        [linesParam],
    );

    const [lastOk, setLastOk] = useState(() => Date.now());

    const inFlight = useRef(false);
    const refresh = useCallback(async () => {
        if (inFlight.current) return;
        inFlight.current = true;

        const params = new URLSearchParams();
        if (linesQuery) params.set('lines', linesQuery);

        try {
            const res = await apiGet(`${basePath}/check?${params}`);
            if (!res.ok) return;
            const json = await res.json();
            if (json.data) {
                setSnapshot(json.data);
                setLastOk(Date.now());
            }
        } catch (_) {
            // Keep the last good snapshot. A board hangs unattended for weeks;
            // blanking it on one dropped fetch would read as "plant stopped".
        } finally {
            inFlight.current = false;
        }
    }, [basePath, linesQuery]);

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

    // The clocks advance locally between pushes: a tile that only moved when
    // the server spoke would sit frozen through the longest stops, which are
    // exactly the ones somebody is standing there watching.
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), TICK_MS);
        return () => clearInterval(id);
    }, []);

    const openStation = useCallback((station) => {
        // Nothing to open in kiosk mode: there is no keyboard in front of the
        // board, and a stray touch that navigated away would leave the wall
        // showing a screen nobody is there to navigate back from.
        if (kiosk) return;
        router.visit(`${monitorPath}?workstation=${station.id}`);
    }, [kiosk, monitorPath]);

    const stations = snapshot?.stations ?? [];
    const stale = now - lastOk > STALE_MS;

    const board = (
        <div className={kiosk ? 'flex h-screen min-h-0 flex-col bg-om-bg' : 'flex h-full min-h-0 flex-col'}>
            <div className="flex min-h-0 flex-1 flex-col overflow-hidden border border-om-line2 bg-om-card">
                <header className="flex flex-shrink-0 flex-wrap items-center gap-3 border-b border-om-line2 px-5 py-3">
                    <span className={`font-medium text-om-text ${kiosk ? 'text-2xl' : 'text-sm'}`}>
                        {snapshot?.shift?.label}
                    </span>
                    <span className={kiosk ? 'text-xl text-om-muted' : 'text-xs text-om-muted'}>
                        {snapshot?.shift?.window}
                    </span>
                    {snapshot?.shift?.isLive && (
                        <span className={`rounded-om-sm bg-om-running/15 px-2 py-0.5 font-medium text-om-running ${kiosk ? 'text-xl' : 'text-xs'}`}>
                            {__('Live')}
                        </span>
                    )}
                    {stale && (
                        <span className={`rounded-om-sm bg-om-blocked/15 px-2 py-0.5 font-medium text-om-blocked ${kiosk ? 'text-xl' : 'text-xs'}`}>
                            {__('Board is not updating')}
                        </span>
                    )}
                    <span className={`ml-auto text-om-muted ${kiosk ? 'text-xl' : 'text-xs'}`}>
                        {__('Stations')}: {stations.length}
                    </span>
                    {!kiosk && (
                        <a
                            href={`${basePath}?${new URLSearchParams({ ...(linesQuery ? { lines: linesQuery } : {}), kiosk: '1' })}`}
                            className="rounded-om-sm border border-om-line2 px-2 py-0.5 text-xs text-om-muted hover:text-om-text"
                        >
                            {__('Full screen')}
                        </a>
                    )}
                </header>

                {stations.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-om-muted">
                        {__('No active workstation matches this board.')}
                    </p>
                ) : (
                    <div
                        className="grid min-h-0 flex-1 gap-3 overflow-auto p-4"
                        // Wider tiles on the wall: the grid fills whatever space
                        // it has, so the only thing that has to change with
                        // viewing distance is how small a tile is allowed to get.
                        style={{
                            gridTemplateColumns: `repeat(auto-fill, minmax(${kiosk ? '20rem' : '15rem'}, 1fr))`,
                        }}
                    >
                        {stations.map((station) => (
                            <StationTile
                                key={station.id}
                                station={station}
                                now={now}
                                kiosk={kiosk}
                                onOpen={openStation}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );

    if (kiosk) {
        return (
            <>
                <Head title={__('Plant board')} />
                {board}
            </>
        );
    }

    return (
        <AppLayout>
            <Head title={__('Plant board')} />
            <PageTitle>{__('Plant board')}</PageTitle>
            {board}
        </AppLayout>
    );
}

function StationTile({ station, now, kiosk, onOpen }) {
    const tone = STATE_TONE[station.state] ?? STATE_TONE.unknown;
    // Downtime only. See isDowntime: a clock on a healthy machine is noise
    // wearing the shape of an alarm.
    const elapsed = isDowntime(station.state, station.reason)
        ? elapsedLabel(station.since, now)
        : null;

    return (
        <button
            type="button"
            onClick={() => onOpen(station)}
            style={{ borderColor: tone.color, background: tone.background }}
            className={`flex flex-col gap-2 rounded-om border border-om-line2 p-3 text-left ${
                kiosk ? 'cursor-default' : 'transition hover:brightness-105'
            }`}
        >
            <div className="flex items-baseline gap-2">
                <span className={`font-semibold leading-none text-om-text ${kiosk ? 'text-3xl' : 'text-lg'}`}>
                    {station.code}
                </span>
                <span className={`truncate text-om-muted ${kiosk ? 'text-base' : 'text-xs'}`}>
                    {station.lineName}
                </span>
            </div>
            <div className={`truncate text-om-muted ${kiosk ? 'text-base' : 'text-xs'}`}>{station.name}</div>

            <div className="flex items-baseline justify-between gap-2">
                <span className={`font-medium ${kiosk ? 'text-xl' : 'text-sm'}`} style={{ color: tone.color }}>
                    {stateLabel(station.state)}
                </span>
                {elapsed && (
                    <span className={`font-mono tabular-nums text-om-text ${kiosk ? 'text-2xl' : 'text-sm'}`}>
                        {elapsed}
                    </span>
                )}
            </div>

            {/* Only on a tile that is actually stopped: the cause is the whole
                point of the board, and printing an empty slot on every running
                machine would bury it among blanks. */}
            {station.reason && (
                <div className={`truncate ${kiosk ? 'text-lg' : 'text-xs'}`} style={{ color: tone.color }}>
                    {station.reason.needsReason || !station.reason.name
                        ? __('Cause not given')
                        : station.reason.name}
                </div>
            )}

            <div className={`truncate text-om-muted ${kiosk ? 'text-base' : 'text-xs'}`}>
                {station.order
                    ? `${station.order.number}${station.order.product ? ` · ${station.order.product}` : ''}`
                    : __('No order running')}
            </div>

            {station.operators?.length > 0 && (
                <div className={`truncate text-om-muted ${kiosk ? 'text-base' : 'text-xs'}`}>
                    {station.operators.join(', ')}
                </div>
            )}

            <dl className="mt-auto grid grid-cols-3 gap-1 border-t border-om-line2 pt-2 text-center">
                <Metric label={__('Avail.')} value={station.availability} kiosk={kiosk} />
                <Metric label={__('Perf.')} value={station.performance} kiosk={kiosk} />
                <Metric label={__('Qual.')} value={station.quality} kiosk={kiosk} />
            </dl>
        </button>
    );
}

function Metric({ label, value, kiosk }) {
    return (
        <div>
            <dt className={`uppercase tracking-wide text-om-muted ${kiosk ? 'text-xs' : 'text-[0.625rem]'}`}>
                {label}
            </dt>
            {/* An em dash, not 0%: a station with no minutes to judge has no
                score, and a zero would read as a machine that failed. */}
            <dd className={`font-medium tabular-nums text-om-text ${kiosk ? 'text-xl' : 'text-sm'}`}>
                {value === null || value === undefined ? '—' : `${value}%`}
            </dd>
        </div>
    );
}
