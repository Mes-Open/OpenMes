import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { useHotShapes } from '../../LiveShapesProvider';
import { __, formatNumber } from '../../../lib/i18n';
import { bottomRow, percentDone } from './lineCardFormat';
import { lineStateLabel, lineStateTone } from './lineStates';

// State → pill / progress bar / card edge, from the mockup's palette: amber for
// blocked, red for down, and only those two get a coloured card edge.
// The pill's label and colours come from ./lineStates (shared by every section).
const STATE_STYLE = {
    running: { bar: 'bg-om-running', edge: 'border-om-line' },
    blocked: { bar: 'bg-om-downtime', edge: 'border-om-downtime/50' },
    down: { bar: 'bg-om-blocked', edge: 'border-om-blocked/50' },
    idle: { bar: 'bg-om-faint', edge: 'border-om-line' },
    // Collector went silent: a dashed edge, like the "No data" pill.
    unknown: { bar: 'bg-om-faint', edge: 'border-dashed border-om-faint' },
};

const TONE_CLASS = {
    ok: 'text-om-running',
    warn: 'text-om-downtime',
    bad: 'text-om-blocked',
    mute: 'text-om-muted',
};

/**
 * "Lines" section of the plant overview: one card per active line with its
 * state, the current work order's progress and an ETA (or why it isn't
 * moving). State comes from `overview.lines`, the order from
 * `overview.line_orders` — both refreshed every 60 s; the shared
 * `work_orders_active` collection moves the produced count in between.
 */
export default function LineCards({ overview, accessibleTabs = [] }) {
    const lines = overview?.lines ?? [];
    const canOpenShift = accessibleTabs.includes('shift_monitor');

    const hot = useHotShapes();
    const { data: liveOrders = [] } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));
    const liveById = useMemo(() => new Map(liveOrders.map((o) => [String(o.id), o])), [liveOrders]);

    const orderByLine = useMemo(
        () => new Map((overview?.line_orders ?? []).map((o) => [o.line_id, o])),
        [overview?.line_orders],
    );

    return (
        <section aria-labelledby="overview-lines-h" className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id="overview-lines-h" className="m-0 text-[18px] font-semibold text-om-ink">{__('Lines')}</h2>
                {canOpenShift && (
                    <Link href="/admin/shift-board" className="text-[14px] text-om-accent hover:underline">
                        {__('Plant board →')}
                    </Link>
                )}
            </div>

            {lines.length === 0 ? (
                <div className="bg-om-card border border-om-line rounded-[14px] px-6 py-5 text-[14px] text-om-muted">
                    {__('No active lines yet.')}
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-4">
                    {lines.map((line) => (
                        <LineCard
                            key={line.id}
                            line={line}
                            lineOrder={orderByLine.get(line.id)}
                            live={liveById}
                            href={canOpenShift ? `/admin/shift-overview?line=${line.id}` : null}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function LineCard({ line, lineOrder, live, href }) {
    const style = STATE_STYLE[line.state] ?? STATE_STYLE.unknown;
    const tone = lineStateTone(line.state);
    const order = lineOrder?.order ?? null;

    // Live quantities when the shared collection has the row; ETA stays the
    // server's (it needs the hourly rate).
    const liveRow = order ? live.get(String(order.id)) : null;
    const produced = liveRow?.produced_qty ?? order?.produced;
    const planned = liveRow?.planned_qty ?? order?.planned;
    const percent = order ? percentDone(produced, planned) : null;
    const row = bottomRow(line, lineOrder);

    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <span className="min-w-0 truncate text-[16px] font-semibold text-om-ink">{line.name}</span>
                <span
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-[3px] text-[12px] font-semibold"
                    style={tone.pill}
                >
                    {tone.marker && (
                        <span aria-hidden="true" className="h-2.5 w-2.5 rounded-[2px]" style={{ background: tone.marker }} />
                    )}
                    {lineStateLabel(line.state)}
                </span>
            </div>

            <span className="font-mono text-[34px] font-semibold leading-[1.1] tracking-[-0.02em] text-om-ink">
                {percent == null ? '—' : `${percent}%`}
            </span>

            <div className="h-1.5 overflow-hidden rounded-[3px] bg-om-track" aria-hidden="true">
                <div className={`h-full rounded-[3px] ${style.bar}`} style={{ width: `${percent ?? 0}%` }} />
            </div>

            <span className="text-[13px] text-om-muted">
                {order
                    ? [
                        __(':done / :planned pcs', { done: formatNumber(produced), planned: formatNumber(planned) }),
                        order.product_code ?? order.order_no,
                        lineOrder.more_count > 0 ? __('+:count more', { count: lineOrder.more_count }) : null,
                    ].filter(Boolean).join(' · ')
                    : '—'}
            </span>

            <span className={`text-[14px] font-medium ${TONE_CLASS[row.tone]}`}>{row.text}</span>
        </>
    );

    const className = `flex flex-col gap-2.5 rounded-[14px] border bg-om-card p-5 text-om-ink no-underline ${style.edge}`;

    return href ? (
        <Link
            href={href}
            className={`${className} transition-colors hover:bg-om-panel focus-visible:outline-2 focus-visible:outline-om-accent`}
            data-testid="line-card"
            data-state={line.state}
        >
            {body}
        </Link>
    ) : (
        <div className={className} data-testid="line-card" data-state={line.state}>
            {body}
        </div>
    );
}
