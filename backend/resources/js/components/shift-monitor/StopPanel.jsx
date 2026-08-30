import { useEffect, useState } from 'react';

import { __, formatNumber, formatTime } from '../../lib/i18n';
import { KIND_COLOR } from './tokens';

/**
 * The drawer behind a clicked segment.
 *
 * A stop opens the cause picker — the screen's whole reason to exist. A running
 * or slow interval opens a read-only breakdown of what it produced, because
 * there is nothing to decide about time that went well.
 */
export default function StopPanel({ segment, reasonGroups, idealRatePerMinute, onClose, onClassify, onEscalate }) {
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);

    // A different stop is a different decision — never carry a note across.
    useEffect(() => setNotes(''), [segment?.key]);

    // Escape closes, so the drawer doesn't trap a keyboard user on a screen
    // that is otherwise entirely mouse-driven.
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    if (!segment) return null;

    const isStop = segment.kind === 'down';
    // Rendered in the plant timezone (lib/i18n), so the drawer's times match the
    // hour row the segment was clicked in.
    const startedAt = Date.parse(segment.startsAt);
    const startsAtClock = formatTime(startedAt);
    const endsAtClock = formatTime(startedAt + segment.minutes * 60000);

    const run = async (fn) => {
        setBusy(true);
        try {
            await fn();
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={onClose}
                aria-label={__('Close')}
                className="absolute inset-0 z-40 bg-black/50"
            />
            <aside
                className="absolute inset-y-0 right-0 z-[41] flex w-[406px] max-w-full flex-col border-l border-om-line bg-om-card shadow-[-26px_0_60px_-28px_rgba(0,0,0,0.6)]"
                role="dialog"
                aria-label={isStop ? __('Stop detail') : __('Interval detail')}
            >
                <header className="border-b border-om-line2 px-[22px] pb-[15px] pt-[18px]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <StatusChip segment={segment} />
                            <div className="text-xl font-semibold tracking-[-0.015em] text-om-ink">
                                {isStop
                                    ? segment.needsCause ? __('Unclassified stop') : segment.reason
                                    : segment.kind === 'slow' ? __('Speed loss') : __('Running interval')}
                            </div>
                            <div className="mt-[3px] font-mono text-[10.5px] text-om-faint">
                                {startsAtClock}–{endsAtClock}
                                {isStop && ` · ${__('logged automatically')}`}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label={__('Close')}
                            className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-om-sm bg-om-chip text-sm text-om-muted hover:text-om-ink"
                        >
                            ✕
                        </button>
                    </div>

                    <div className="mt-[15px] flex overflow-hidden rounded-[11px] border border-om-line2">
                        <Stat label={__('Duration')} value={`${segment.minutes} ${__('min')}`} />
                        {/* A stop lost what it could have made; a slow run lost
                            the shortfall; a run at rate lost nothing, so it
                            reports what it made instead. The server does this
                            arithmetic — the drawer used to print expected output
                            as "lost" for every kind, including healthy ones. */}
                        {segment.lost > 0 || isStop || segment.kind === 'slow' ? (
                            <Stat
                                label={__('Lost pcs')}
                                value={formatNumber(segment.lost ?? 0)}
                                tone="var(--om-blocked)"
                            />
                        ) : (
                            <Stat label={__('Produced')} value={formatNumber(segment.produced ?? 0)} />
                        )}
                        {/* The raw machine state (RUNNING, FAULT…) is a source
                            string in the catalogs, so it reads in the
                            operator's language like the rest of the drawer. */}
                        <Stat label={__('State')} value={__(segment.state)} last />
                    </div>
                </header>

                <div className="flex-1 overflow-y-auto px-[22px] pt-4">
                    {isStop ? (
                        <>
                            <div className="mb-[11px] font-mono text-[9px] uppercase tracking-[0.12em] text-om-faint">
                                {segment.needsCause ? __('Pick a cause') : __('Change the cause')}
                            </div>
                            {reasonGroups.map((group) => (
                                <div key={group.kind} className="mb-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span
                                            className="h-2 w-2 rounded-sm"
                                            style={{ background: KIND_COLOR[group.kind] }}
                                        />
                                        <span className="font-mono text-[9px] uppercase tracking-[0.08em] text-om-muted">
                                            {group.label}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap gap-[7px]">
                                        {group.items.map((reason) => {
                                            const active = segment.reasonCode === reason.code;
                                            return (
                                                <button
                                                    key={reason.id}
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() => run(() => onClassify(segment, reason, notes))}
                                                    className="flex flex-col gap-0.5 rounded-[9px] px-3 py-2 text-left text-[12.5px] font-medium disabled:opacity-60"
                                                    style={active
                                                        ? { background: KIND_COLOR[group.kind], color: '#fff' }
                                                        : { background: 'var(--om-bg)', border: '1px solid var(--om-line2)', color: 'var(--om-ink)' }}
                                                >
                                                    {reason.name}
                                                    <span
                                                        className="font-mono text-[8.5px] tracking-[0.04em]"
                                                        style={{ color: active ? 'rgba(255,255,255,0.72)' : 'var(--om-faint)' }}
                                                    >
                                                        {reason.code}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </>
                    ) : (
                        <>
                            <div className="mb-[11px] font-mono text-[9px] uppercase tracking-[0.12em] text-om-faint">
                                {__('Interval detail')}
                            </div>
                            <IntervalRow
                                label={__('Nameplate rate')}
                                value={`${idealRatePerMinute} ${__('pcs/min')}`}
                            />
                            <IntervalRow
                                label={__('Duration')}
                                value={`${segment.minutes} ${__('min')}`}
                            />
                            <IntervalRow
                                label={__('Expected output')}
                                value={`${formatNumber(Math.round(segment.minutes * idealRatePerMinute))} ${__('pcs')}`}
                            />
                            {/* Expected against actual, side by side — the
                                verdict below is a summary of these two, and
                                without the second one it had to be taken on
                                trust. */}
                            <IntervalRow
                                label={__('Actual output')}
                                value={`${formatNumber(segment.produced ?? 0)} ${__('pcs')}`}
                            />
                            <IntervalRow
                                label={__('Verdict')}
                                value={segment.kind === 'slow' ? __('Below nameplate rate') : __('At rate')}
                                tone={segment.kind === 'slow' ? 'var(--om-downtime)' : 'var(--om-running)'}
                            />
                        </>
                    )}
                </div>

                <footer className="border-t border-om-line2 px-[22px] pb-5 pt-4">
                    {isStop ? (
                        <>
                            <label className="sr-only" htmlFor="stop-note">{__('Add a note for maintenance…')}</label>
                            <input
                                id="stop-note"
                                type="text"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder={__('Add a note for maintenance…')}
                                maxLength={1000}
                                className="mb-[11px] w-full rounded-om-sm border border-om-line2 bg-om-bg px-[13px] py-[11px] text-[12.5px] text-om-ink placeholder:text-om-faint focus:border-om-accent focus:outline-none"
                            />
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => run(() => onEscalate(segment, notes))}
                                className="w-full rounded-[11px] bg-om-blocked-bg py-[13px] text-center text-[13.5px] font-semibold text-om-blocked disabled:opacity-60"
                            >
                                {__('Escalate to maintenance')}
                            </button>
                        </>
                    ) : (
                        <button
                            type="button"
                            onClick={onClose}
                            className="block w-full rounded-[11px] bg-om-ink py-[13px] text-center text-[13.5px] font-semibold text-om-on-ink"
                        >
                            {__('Close')}
                        </button>
                    )}
                </footer>
            </aside>
        </>
    );
}

function StatusChip({ segment }) {
    const [label, color, background] = segment.kind !== 'down'
        ? segment.kind === 'slow'
            ? [__('REDUCED SPEED'), 'var(--om-downtime)', 'var(--om-downtime-bg)']
            : [__('RUNNING'), 'var(--om-running)', 'var(--om-running-bg)']
        : segment.needsCause
            ? [__('NEEDS A CAUSE'), 'var(--om-blocked)', 'var(--om-blocked-bg)']
            : [__('CLASSIFIED'), 'var(--om-muted)', 'var(--om-chip)'];

    return (
        <span
            className="mb-[9px] inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[9px] tracking-[0.08em]"
            style={{ color, background }}
        >
            {label}
        </span>
    );
}

function Stat({ label, value, tone, last }) {
    return (
        <div className={`flex-1 px-[13px] py-[11px] ${last ? '' : 'border-r border-om-line2'}`}>
            <div className="mb-[5px] font-mono text-[8px] uppercase tracking-[0.1em] text-om-faint">{label}</div>
            <div className="truncate font-mono text-[15px] font-semibold" style={{ color: tone ?? 'var(--om-ink)' }}>
                {value}
            </div>
        </div>
    );
}

function IntervalRow({ label, value, tone }) {
    return (
        <div className="flex items-baseline justify-between gap-3 border-b border-om-line2 py-[11px]">
            <span className="text-[12.5px] text-om-muted">{label}</span>
            <span className="font-mono text-[12.5px] font-semibold" style={{ color: tone ?? 'var(--om-ink)' }}>
                {value}
            </span>
        </div>
    );
}
