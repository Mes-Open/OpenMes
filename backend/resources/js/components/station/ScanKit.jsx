import { __ } from '../../lib/i18n';

/*
 * The pieces a scanning station is built from (the SN label and packing
 * stations): the scanner's field, the step's big action, a choice chip and the
 * slot the last scan's outcome stays in.
 */

/** The scanner's field: tall, mono, readable from arm's length. */
export function ScanInput({ ref, onChange, className = '', ...props }) {
    return (
        <input
            ref={ref}
            type="text"
            autoComplete="off"
            spellCheck={false}
            onChange={(e) => onChange(e.target.value)}
            className={`h-14 w-full min-w-0 rounded-[10px] border border-om-line bg-om-bg px-3.5 font-mono text-[19px] text-om-ink outline-none placeholder:font-sans placeholder:text-[15px] placeholder:text-om-faintest focus:border-om-faint ${className}`}
            {...props}
        />
    );
}

/** The step's full-width action: accent once there is something to send, quiet until then. */
export function BigButton({ ready, className = '', children, ...props }) {
    return (
        <button
            className={`block w-full rounded-[11px] py-4 text-center text-[15px] font-semibold ${ready ? 'cursor-pointer bg-om-accent text-white hover:brightness-95' : 'bg-om-chip text-om-faint'} disabled:cursor-not-allowed ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}

/** One choice of a few, as a pill; the caller wraps them in a `role="radiogroup"`. */
export function Chip({ on, onClick, children }) {
    return (
        <button type="button" role="radio" aria-checked={on} onClick={onClick}
            className={`cursor-pointer whitespace-nowrap rounded-full px-3 py-[7px] text-[12.5px] font-medium ${on ? 'bg-om-ink text-om-on-ink' : 'border border-om-line2 bg-om-bg text-om-muted hover:text-om-ink'}`}>
            {children}
        </button>
    );
}

const RESULT_TONE = {
    success: { bg: 'bg-om-running-bg', icon: 'bg-om-running text-white', text: 'text-om-running', glyph: '✓' },
    error: { bg: 'bg-om-blocked-bg', icon: 'bg-om-blocked text-white', text: 'text-om-blocked', glyph: '!' },
    warning: { bg: 'bg-om-downtime-bg', icon: 'bg-om-downtime text-white', text: 'text-om-downtime', glyph: '!' },
    info: { bg: 'bg-om-chip', icon: 'bg-om-ink text-om-on-ink', text: 'text-om-ink', glyph: 'i' },
};

/**
 * Where the last scan's outcome stays: the same place every time, so the eyes
 * know where to look after a beep. Empty, it is a dashed outline that keeps
 * the panel from jumping when the first result arrives. `children` go under
 * the outcome (an action that follows from it).
 */
export function ResultSlot({ result, children }) {
    const tone = RESULT_TONE[result?.severity] ?? RESULT_TONE.info;
    return (
        <div role="status" aria-live="polite"
            className={`flex min-h-[74px] gap-[11px] rounded-[11px] px-3.5 py-[13px] ${result ? `items-start border border-transparent ${tone.bg}` : 'items-center justify-center border-[1.5px] border-dashed border-om-line'}`}>
            {result ? (
                <>
                    <span aria-hidden="true" className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-bold ${tone.icon}`}>{tone.glyph}</span>
                    <div className="min-w-0 flex-1">
                        <div className={`break-words text-[13.5px] font-semibold ${tone.text}`}>{result.title}</div>
                        {result.body && <div className="mt-[3px] break-words font-mono text-[11.5px] leading-normal text-om-muted">{result.body}</div>}
                        {children}
                    </div>
                </>
            ) : (
                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faintest">{__('Last result')}</span>
            )}
        </div>
    );
}
