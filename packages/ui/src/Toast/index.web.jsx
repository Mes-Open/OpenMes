/**
 * Toast — Geist White system (design ref: OpenMES Components.dc.html §09).
 *
 * A floating notice: it never takes space in the page, so nothing under it moves.
 * Severity reads three ways at once — tinted card + 4px left bar, an icon, and the
 * ARIA role — so it is clear at a glance, at arm's length, and to a screen reader.
 *
 * `ToastProvider` renders the stack; `useToast()` returns
 * `toast({ severity, title, body, duration })`. Severity is `success`, `info`,
 * `warning` or `error`. Without a `duration` each severity has its own:
 *
 *   success 4 s · info 6 s · warning 8 s · error stays until dismissed
 *
 * An error is the one message the reader must not miss, so it waits for a tap.
 * A thin bar along the bottom counts the time down, and pointing at or focusing a
 * toast pauses it, so a long message can be read to the end. The same message
 * twice in a row restarts the one on screen instead of stacking a copy.
 *
 * Provider options:
 *   placement — `top-right` (desk screens, default) or `top-center` (operator
 *               tablets: in the line of sight, clear of the corner the hand rests on)
 *   size      — `md` (default) or `lg` (operator: larger type and a 44 px close target)
 *   topInset  — px from the top of the viewport (set it to clear a header)
 *
 * Use this for a write that leaves you on the page — one that goes out over
 * `fetch`/`apiCall`, typically because the rows are a synced collection and the
 * screen updates itself. A write that *redirects* should flash instead
 * (`->with('success', …)`); the layouts turn the flash into a toast for free.
 *
 * **A toast does not survive an Inertia visit.** The visit remounts the layout
 * that holds `ToastProvider`, and the provider's state goes with it, so
 * `router.post(…, { onSuccess: () => toast(…) })` shows nothing at all — no
 * error, no toast. Navigate → flash. Toast → don't navigate.
 */
import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from '../Icon';

const severities = {
    success: { icon: 'circle-check', bar: 'border-l-om-running', tint: 'bg-om-running-bg', fg: 'text-om-running', timer: 'bg-om-running', duration: 4000 },
    info: { icon: 'info', bar: 'border-l-om-accepted', tint: 'bg-om-accepted-bg', fg: 'text-om-accepted', timer: 'bg-om-accepted', duration: 6000 },
    warning: { icon: 'triangle-alert', bar: 'border-l-om-downtime', tint: 'bg-om-downtime-bg', fg: 'text-om-downtime', timer: 'bg-om-downtime', duration: 8000 },
    error: { icon: 'circle-alert', bar: 'border-l-om-blocked', tint: 'bg-om-blocked-bg', fg: 'text-om-blocked', timer: 'bg-om-blocked', duration: 0 },
};

const sizes = {
    md: {
        width: 'w-[340px]',
        pad: 'pl-[14px] pr-[6px] py-[11px] gap-[11px]',
        icon: 18,
        title: 'text-[13px]',
        body: 'mt-[3px] text-[12px]',
        close: 'size-8 -my-1',
        closeIcon: 16,
    },
    lg: {
        width: 'w-[520px]',
        pad: 'pl-[18px] pr-[8px] py-[14px] gap-[14px]',
        icon: 26,
        title: 'text-[16px] leading-snug',
        body: 'mt-1 text-[13.5px] leading-snug',
        close: 'size-11 -my-2',
        closeIcon: 22,
    },
};

/** Default time on screen for a severity, in ms; 0 means until dismissed. */
export function toastDuration(severity) {
    return (severities[severity] ?? severities.info).duration;
}

export function Toast({ severity, title, body, onDismiss, dismissLabel, duration = 0, size = 'md', className = '', ...props }) {
    const s = severities[severity] ?? severities.info;
    const z = sizes[size] ?? sizes.md;
    return (
        // Opaque card under the tint: the dark-mode tints are translucent, and a
        // floating notice must not let the page show through its text.
        <div
            role={severity === 'error' || severity === 'warning' ? 'alert' : 'status'}
            className={`group pointer-events-auto relative overflow-hidden rounded-om bg-om-card shadow-[0_18px_40px_-16px_rgba(0,0,0,.35)] animate-om-toast-in motion-reduce:animate-none ${className}`}
            {...props}
        >
            <div className={`flex items-start border border-om-line border-l-4 rounded-om ${s.bar} ${s.tint} ${z.pad}`}>
                <span aria-hidden className={`shrink-0 ${s.fg}`}>
                    <Icon name={s.icon} size={z.icon} stroke={2.25} />
                </span>
                <div className="flex-1 min-w-0 self-center">
                    <div className={`font-semibold text-om-ink break-words ${z.title}`}>{title}</div>
                    {body != null && body !== '' && <div className={`text-om-muted break-words ${z.body}`}>{body}</div>}
                </div>
                <button
                    type="button"
                    aria-label={dismissLabel}
                    onClick={onDismiss}
                    className={`shrink-0 inline-flex items-center justify-center rounded-om-sm cursor-pointer text-om-faint hover:text-om-ink hover:bg-om-chip transition-colors ${z.close}`}
                >
                    <Icon name="x" size={z.closeIcon} />
                </button>
            </div>
            {duration > 0 && (
                // The countdown is the timer: when the bar runs out the toast goes.
                // Pointing at or focusing the toast pauses both together.
                <span
                    aria-hidden
                    onAnimationEnd={onDismiss}
                    style={{ animationDuration: `${duration}ms` }}
                    className={`absolute left-0 bottom-0 h-[3px] w-full origin-left opacity-60 animate-om-toast-timer group-hover:[animation-play-state:paused] group-focus-within:[animation-play-state:paused] ${s.timer}`}
                />
            )}
        </div>
    );
}

const ToastContext = createContext(null);

// Most toasts kept on screen; the oldest goes first when another arrives.
const MAX_VISIBLE = 4;

const placements = {
    'top-right': 'right-[18px] items-end',
    'top-center': 'left-1/2 -translate-x-1/2 items-center',
};

export function ToastProvider({ children, topInset = 18, dismissLabel, placement = 'top-right', size = 'md' }) {
    const [toasts, setToasts] = useState([]);
    const idRef = useRef(0);

    const dismiss = useCallback((id) => {
        setToasts((list) => list.filter((t) => t.id !== id));
    }, []);

    const toast = useCallback(({ severity, title, body, duration }) => {
        const id = ++idRef.current;
        const entry = { id, severity, title, body, duration: duration ?? toastDuration(severity) };
        setToasts((list) => [
            // The same message again (a second tap, a double-mounted effect)
            // replaces the visible one — a fresh id restarts its countdown.
            ...list.filter((t) => !(t.severity === severity && t.title === title && t.body === body)),
            entry,
        ].slice(-MAX_VISIBLE));
        return id;
    }, []);

    const z = sizes[size] ?? sizes.md;

    return (
        <ToastContext.Provider value={toast}>
            {children}
            {typeof document !== 'undefined' && createPortal(
                <div
                    aria-live="polite"
                    className={`pointer-events-none fixed z-[60] flex max-w-[calc(100vw-32px)] flex-col gap-[10px] ${placements[placement] ?? placements['top-right']} ${z.width}`}
                    style={{ top: topInset }}
                >
                    {toasts.map((t) => (
                        <Toast
                            key={t.id}
                            className="w-full"
                            severity={t.severity}
                            title={t.title}
                            body={t.body}
                            duration={t.duration}
                            size={size}
                            dismissLabel={dismissLabel}
                            onDismiss={() => dismiss(t.id)}
                        />
                    ))}
                </div>,
                document.body,
            )}
        </ToastContext.Provider>
    );
}

/** Returns the `toast({ severity, title, body, duration })` function. */
export function useToast() {
    const toast = useContext(ToastContext);
    if (!toast) throw new Error('useToast must be used within a <ToastProvider>');
    return toast;
}
