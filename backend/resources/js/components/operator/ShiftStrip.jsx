import { useEffect, useState } from 'react';
import { __, formatNumber, formatTime } from '../../lib/i18n';
import { monoLabelCls } from './operatorUi';

/** The clock, ticking once a minute (all the strip shows is HH:MM). */
export function useMinuteClock() {
    const [now, setNow] = useState(() => new Date());
    useEffect(() => {
        const t = setInterval(() => setNow(new Date()), 30_000);
        return () => clearInterval(t);
    }, []);
    return now;
}

export function shiftTimes(shift) {
    if (!shift?.start || !shift?.end) return '';
    const opts = { hour: '2-digit', minute: '2-digit' };
    return `${formatTime(new Date(shift.start), opts)}–${formatTime(new Date(shift.end), opts)}`;
}

/**
 * The strip under the operator top bar: the line's figures for the shift and
 * how far through the shift the clock is. Fed by the page's `shiftSummary`
 * prop (OperatorShiftSummary); renders nothing on pages without one.
 */
export default function ShiftStrip({ summary }) {
    const now = useMinuteClock();
    if (!summary) return null;

    const start = new Date(summary.shift.start).getTime();
    const end = new Date(summary.shift.end).getTime();
    const pct = end > start ? Math.min(100, Math.max(0, ((now.getTime() - start) / (end - start)) * 100)) : 0;

    const stats = [
        { label: __('In progress'), value: summary.in_progress, cls: 'text-om-running' },
        // No shift configured → nothing is booked against one, so no count to show.
        summary.made_this_shift != null && { label: __('Made this shift'), value: summary.made_this_shift, cls: 'text-om-ink' },
        { label: __('Blocked'), value: summary.blocked, cls: 'text-om-blocked' },
        { label: __('Due today'), value: summary.due_today, cls: 'text-om-accent' },
        { label: __('Workstations'), value: summary.workstations, cls: 'text-om-ink' },
    ].filter(Boolean);

    return (
        <div className="shrink-0 flex flex-wrap items-center gap-x-7 gap-y-2 px-[22px] py-3 bg-om-panel border-b border-om-line2" data-testid="shift-strip">
            {stats.map((s) => (
                <div key={s.label} className="flex items-baseline gap-2">
                    <span className={monoLabelCls}>{s.label}</span>
                    <span className={`font-mono text-[17px] font-medium ${s.cls}`}>{formatNumber(Number(s.value) || 0, { maximumFractionDigits: 0 })}</span>
                </div>
            ))}
            <div className="flex-1" />
            <div className="flex items-center gap-[9px] w-[280px] max-w-full">
                <span className={monoLabelCls} title={summary.shift.name ?? undefined}>{summary.shift.code ?? __('Shift')}</span>
                <div className="flex-1 h-1.5 rounded-full bg-om-chip overflow-hidden" role="progressbar" aria-label={__('Shift progress')} aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
                    <div className="h-full bg-om-accent" style={{ width: `${pct}%` }} />
                </div>
                <span className="font-mono text-[11px] text-om-muted">{formatTime(now, { hour: '2-digit', minute: '2-digit' })}</span>
            </div>
        </div>
    );
}
