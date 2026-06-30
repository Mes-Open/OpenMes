/**
 * Toast — Geist White system (design ref: OpenMES Components.dc.html §09).
 *
 * Card with 1px line border + 3px severity-colored left border, toast shadow,
 * 10px severity dot, 13px semibold title, mono 10px body, faint × dismiss.
 * `ToastProvider` renders a fixed top-right stack; `useToast()` returns
 * `toast({ severity, title, body, duration = 4000 })` (auto-dismiss + manual ×).
 * API is identical to the native twin (index.native.tsx).
 */
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const severities = {
    success: { border: 'border-l-om-running', dot: 'bg-om-running' },
    warning: { border: 'border-l-om-downtime', dot: 'bg-om-downtime' },
    error: { border: 'border-l-om-blocked', dot: 'bg-om-blocked' },
};

export function Toast({ severity, title, body, onDismiss, dismissLabel, className = '', ...props }) {
    const s = severities[severity];
    return (
        <div
            role={severity === 'error' ? 'alert' : 'status'}
            className={`flex items-start gap-[11px] bg-om-card border border-om-line border-l-[3px] ${s.border} rounded-om px-[15px] py-[13px] shadow-[0_14px_34px_-18px_rgba(0,0,0,.3)] ${className}`}
            {...props}
        >
            <span aria-hidden className={`mt-[2px] size-[10px] shrink-0 rounded-full ${s.dot}`} />
            <div className="flex-1 min-w-0">
                <div className="text-[13px] font-semibold text-om-ink">{title}</div>
                {body != null && <div className="mt-[3px] font-mono text-[10px] text-om-muted">{body}</div>}
            </div>
            <button
                type="button"
                aria-label={dismissLabel}
                onClick={onDismiss}
                className="cursor-pointer text-[15px] leading-none text-om-faint hover:text-om-muted"
            >
                ×
            </button>
        </div>
    );
}

const ToastContext = createContext(null);

export function ToastProvider({ children, topInset = 18, dismissLabel }) {
    const [toasts, setToasts] = useState([]);
    const idRef = useRef(0);
    const timersRef = useRef(new Map());

    const dismiss = useCallback((id) => {
        const timer = timersRef.current.get(id);
        if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(id);
        }
        setToasts((list) => list.filter((t) => t.id !== id));
    }, []);

    const toast = useCallback(
        ({ severity, title, body, duration = 4000 }) => {
            const id = ++idRef.current;
            setToasts((list) => [...list, { id, severity, title, body }]);
            if (duration > 0) timersRef.current.set(id, setTimeout(() => dismiss(id), duration));
            return id;
        },
        [dismiss],
    );

    useEffect(() => {
        const timers = timersRef.current;
        return () => timers.forEach(clearTimeout);
    }, []);

    return (
        <ToastContext.Provider value={toast}>
            {children}
            {createPortal(
                <div className="fixed right-[18px] z-50 flex w-[300px] max-w-[calc(100vw-36px)] flex-col gap-[11px]" style={{ top: topInset }}>
                    {toasts.map((t) => (
                        <Toast
                            key={t.id}
                            severity={t.severity}
                            title={t.title}
                            body={t.body}
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
