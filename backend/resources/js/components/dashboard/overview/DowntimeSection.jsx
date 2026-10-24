import { Link } from '@inertiajs/react';
import { __, durationLabel } from '../../../lib/i18n';
import { OverviewCard } from './OverviewGrid';

// Bar colour by DowntimeReason.kind: unplanned is the loss that hurts (red),
// planned and changeover read neutral, an unclassified stop amber — it needs
// somebody to give it a cause.
const BAR_CLASS = {
    unplanned: 'bg-om-blocked',
    changeover: 'bg-om-faint',
    planned: 'bg-om-faint',
    unclassified: 'bg-om-downtime',
};

function subtitle({ line_count: count, percent_of_planned: percent }) {
    if (percent == null) {
        return count === 1 ? __('across 1 line') : __('across :count lines', { count });
    }
    return count === 1
        ? __('across 1 line · :percent% of planned time', { percent })
        : __('across :count lines · :percent% of planned time', { count, percent });
}

/**
 * Reason rows and the Unclassified row as one list, longest first. The server
 * sorts the reasons; Unclassified is slotted in by its minutes (after equal
 * ones), so a 3 h unclassified block never sits under a 0 m reason.
 */
export function downtimeRows(data) {
    const rows = (data?.reasons ?? []).map((r) => ({ ...r, key: `reason-${r.id}` }));
    if (data?.unclassified) {
        rows.push({ ...data.unclassified, key: 'unclassified', name: __('Unclassified'), kind: 'unclassified' });
    }
    // Array.prototype.sort is stable: the server's order survives among equals.
    return rows.sort((a, b) => b.minutes - a.minutes);
}

function ReasonRow({ name, lines, minutes, kind, open, max, children }) {
    const width = max > 0 ? Math.max(2, (minutes / max) * 100) : 0;

    return (
        <li className="flex flex-col gap-[5px]" data-testid="downtime-reason" data-kind={kind}>
            <div className="flex justify-between gap-2 text-[14px] text-om-ink">
                <span className="min-w-0 break-words">
                    <span className="font-medium">{name}</span>
                    {lines.length > 0 && <span className="text-om-muted"> · {lines.join(', ')}</span>}
                    {children}
                </span>
                <span
                    className="shrink-0 font-mono font-semibold"
                    title={open ? __('Still running') : undefined}
                >
                    {durationLabel(minutes)}
                    {open && <span className="sr-only"> ({__('Still running')})</span>}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded bg-om-track">
                <div className={`h-full rounded ${BAR_CLASS[kind] ?? BAR_CLASS.unplanned}`} style={{ width: `${width}%` }} />
            </div>
        </li>
    );
}

/**
 * "Downtime" card of the plant overview: the current shift's stops grouped by
 * reason (overview.downtime from PlantOverviewService::downtime()), unclassified
 * stops as their own row, and a link to Plant Board — there is no web downtime
 * report, the board is the view that shows stops.
 */
export default function DowntimeSection({ overview, accessibleTabs = [] }) {
    const data = overview?.downtime;
    const canMonitor = accessibleTabs.includes('shift_monitor');

    const footer = canMonitor && (
        <Link href="/admin/shift-board" className="mt-auto text-[14px] text-om-accent hover:underline">
            {__('Downtime report')} →
        </Link>
    );

    let body;
    if (!data || !data.has_lines) {
        body = <p className="text-[14px] text-om-muted">{__('No lines configured')}</p>;
    } else if (data.total_minutes === 0) {
        body = <p className="text-[14px] text-om-muted">{__('No downtime this shift')}</p>;
    } else {
        const rows = downtimeRows(data);
        const unclassified = data.unclassified;
        const max = Math.max(0, ...rows.map((r) => r.minutes));
        const classifyHref = unclassified?.workstation_id
            ? `/admin/shift-monitor?workstation=${unclassified.workstation_id}`
            : '/admin/shift-monitor';

        body = (
            <>
                <div className="flex flex-col">
                    <span
                        className="font-mono text-[32px] font-semibold tracking-[-0.02em] text-om-ink"
                        data-testid="downtime-total"
                    >
                        {durationLabel(data.total_minutes)}
                    </span>
                    <span className="text-[14px] text-om-muted">{subtitle(data)}</span>
                </div>
                <ul className="m-0 flex list-none flex-col gap-3 p-0">
                    {rows.map(({ key, ...row }) => (
                        <ReasonRow key={key} max={max} {...row}>
                            {row.kind === 'unclassified' && canMonitor && (
                                <Link href={classifyHref} className="block text-om-accent hover:underline">
                                    {__('Classify in Shift Monitor')}
                                </Link>
                            )}
                        </ReasonRow>
                    ))}
                </ul>
            </>
        );
    }

    return (
        <OverviewCard
            title={__('Downtime')}
            action={<span className="text-[13px] text-om-muted">{__('this shift')}</span>}
        >
            <div className="flex h-full flex-col gap-3.5" data-testid="overview-downtime">
                {body}
                {footer}
            </div>
        </OverviewCard>
    );
}
