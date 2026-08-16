import { useState } from 'react';

import { __, formatNumber, formatTime } from '../../lib/i18n';
import { PIN_STYLE, pct, ratioColor, segmentColor } from './tokens';

/**
 * One clock hour of the shift: the event balloons above it, the state bar,
 * the production pulse strip, and the hour's output against target.
 *
 * The bar is absolutely positioned percentages of the hour rather than a flex
 * row, because a segment's width has to stay true to its duration even when
 * that is under a minute — flex would round it away.
 */
export default function HourRow({ hour, onSelectSegment, selectedKey, pins }) {
    const [openPin, setOpenPin] = useState(null);

    return (
        <div className="flex min-h-0 flex-1 items-stretch border-b border-om-line2 hover:bg-om-ink/[0.02]">
            <div
                className="flex w-11 flex-shrink-0 items-center justify-center border-r border-om-line2 font-mono text-xs font-semibold"
                style={{ color: hour.isNow ? 'var(--om-accent)' : 'var(--om-muted)' }}
            >
                {hour.label}
            </div>

            <div className="relative flex min-h-0 min-w-0 flex-1 flex-col px-0 pb-1 pt-[11px]">
                {pins.map((pin) => (
                    <EventPin
                        key={pin.key}
                        pin={pin}
                        open={openPin === pin.key}
                        onOpen={() => setOpenPin((k) => (k === pin.key ? null : pin.key))}
                        onHoverOut={() => setOpenPin((k) => (k === pin.key ? null : k))}
                    />
                ))}

                {/* Grows into whatever height the page has left, so a shift's
                    rows fill the screen instead of running off the bottom of
                    it — the timeline is the thing being read, and scrolling it
                    out of view costs more than a shorter bar does. */}
                <div className="relative min-h-[34px] flex-1 overflow-hidden rounded-[3px] bg-om-track">
                    {hour.segments.map((segment) => (
                        <Segment
                            key={segment.key}
                            segment={segment}
                            hourFrom={hour.from}
                            selected={selectedKey === segment.key}
                            onSelect={() => onSelectSegment(segment)}
                        />
                    ))}
                    {hour.isNow && (
                        <div
                            className="absolute inset-y-0 z-[6] w-0.5 bg-om-accent"
                            style={{ left: pct(hour.nowOffset) }}
                            aria-hidden="true"
                        />
                    )}
                </div>

                <div className="relative mt-[3px] h-[9px] flex-shrink-0">
                    {hour.dots.map((dot) => (
                        <span
                            key={dot.offset}
                            className="absolute top-0.5 h-1.5 w-1.5 rounded-full"
                            style={{
                                left: pct(dot.offset),
                                background: dot.kind === 'slow' ? 'var(--om-downtime)' : 'var(--om-ink)',
                            }}
                        />
                    ))}
                </div>
            </div>

            <div className="flex w-[104px] flex-shrink-0 flex-col items-center justify-center gap-0.5 border-l border-om-line2">
                <span
                    className="font-mono text-[13px] font-semibold"
                    style={{ color: ratioColor(hour.target ? hour.actual / hour.target : null) }}
                >
                    {formatNumber(hour.actual)}
                </span>
                <span className="font-mono text-[9.5px] text-om-faint">
                    {hour.target === null ? '—' : `/ ${formatNumber(hour.target)}`}
                </span>
            </div>
        </div>
    );
}

/**
 * A stretch of one machine state. Running and stopped stretches are clickable
 * — a stop to give it a cause, a run to see what it produced; planned and
 * unscheduled time has nothing to say, so it isn't a button.
 */
function Segment({ segment, hourFrom, selected, onSelect }) {
    // Idle, planned and unrecorded time have nothing to decide about: the
    // first two are accounted for, and the third is a hole in the record that
    // a cause picker cannot fill.
    const interactive = !['idle', 'plan', 'none'].includes(segment.kind);
    const offset = segment.from - hourFrom;
    const showLabel = Boolean(segment.label) && segment.minutes >= 9;

    const style = {
        left: pct(offset),
        width: pct(segment.minutes),
        background: segmentColor(segment),
        boxShadow: selected ? 'inset 0 0 0 2px var(--om-ink)' : undefined,
    };

    // formatTime renders in the plant timezone (lib/i18n, set from the app's
    // `timezone` prop), which is the zone the hour rows are labelled in.
    const title = `${formatTime(segment.startsAt)} · ${segment.minutes} ${__('min')} · ${
        segment.needsCause ? __('stop — needs a cause') : segment.label ?? __(SEGMENT_TITLE[segment.kind])
    }`;

    const content = (
        <>
            {segment.needsCause && (
                <span
                    className="flex h-[17px] w-[17px] flex-shrink-0 animate-om-blink items-center justify-center rounded-full bg-white text-[11px] font-bold text-om-blocked"
                    aria-hidden="true"
                >
                    ?
                </span>
            )}
            {showLabel && (
                <span
                    className="truncate text-[11px] font-semibold"
                    style={{ color: segment.kind === 'plan' ? 'var(--om-done)' : '#fff' }}
                >
                    {segment.label}
                </span>
            )}
        </>
    );

    const className =
        'absolute inset-y-0 flex items-center gap-[5px] overflow-hidden px-1.5 ' +
        (segment.kind === 'idle' || segment.kind === 'none' ? '' : 'border-r border-black/20 ') +
        (interactive ? 'cursor-pointer hover:brightness-110' : '');

    if (!interactive) {
        return <div className={className} style={style} title={title} aria-hidden="true">{content}</div>;
    }

    return (
        <button type="button" className={className} style={style} title={title} onClick={onSelect}>
            <span className="sr-only">{title}</span>
            {content}
        </button>
    );
}

const SEGMENT_TITLE = {
    run: 'running',
    slow: 'reduced speed',
    down: 'stopped',
    plan: 'planned',
    idle: 'not scheduled',
    none: 'no data recorded',
};

/**
 * A balloon marking something that happened at a moment rather than over an
 * interval — a batch changing over, an issue raised, a check signed off.
 */
function EventPin({ pin, open, onOpen, onHoverOut }) {
    const { color, glyph } = PIN_STYLE[pin.type] ?? PIN_STYLE.comment;
    // A pin in the second half of the hour opens its card leftwards, so the
    // card never runs off the right edge of the timeline.
    const late = pin.offset > 38;

    return (
        <>
            <button
                type="button"
                onClick={onOpen}
                onMouseEnter={onOpen}
                onMouseLeave={onHoverOut}
                className="absolute -top-[9px] flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-[50%_50%_50%_3px] border-2 shadow-[0_2px_6px_rgba(0,0,0,0.35)]"
                style={{
                    left: pct(pin.offset),
                    background: color,
                    borderColor: 'var(--om-bg)',
                    zIndex: open ? 12 : 7,
                }}
                aria-label={`${pin.time} · ${pin.title}`}
            >
                {/* No `title`: hovering already opens the card below, and the
                    browser's own tooltip would sit on top of it saying the same
                    thing. The label above keeps it reachable without a mouse. */}
                <span className="text-[10px] leading-none text-white">{glyph}</span>
            </button>

            {open && (
                <div
                    className="absolute top-3.5 z-20 w-[212px] cursor-default rounded-[10px] border border-om-line bg-om-card px-3.5 py-3 shadow-[0_16px_40px_-14px_rgba(0,0,0,0.6)]"
                    style={late
                        ? { right: `${100 - (pin.offset / 60) * 100}%`, transform: 'translateX(10px)' }
                        : { left: pct(pin.offset), transform: 'translateX(-10px)' }}
                >
                    <div className="mb-[7px] flex items-center gap-[7px]">
                        <span className="h-[7px] w-[7px] rounded-full" style={{ background: color }} />
                        <span className="font-mono text-[9px] tracking-[0.1em]" style={{ color }}>
                            {pin.status}
                        </span>
                        <span className="ml-auto font-mono text-[9.5px] text-om-faint">{pin.time}</span>
                    </div>
                    <div className="mb-0.5 text-[13.5px] font-semibold text-om-ink">{pin.title}</div>
                    <div className="mb-[9px] text-[11.5px] text-om-muted">
                        {pin.who} — {pin.note}
                    </div>
                    {pin.rows.map(([key, value]) => (
                        <div key={key} className="flex justify-between gap-2.5 py-[3px]">
                            <span className="font-mono text-[9px] tracking-[0.08em] text-om-faint">{key}</span>
                            <span className="font-mono text-[10.5px] text-om-ink">{value}</span>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
