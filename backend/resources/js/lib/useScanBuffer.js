import { useEffect, useRef } from 'react';

/**
 * The keystroke machine behind a barcode/RFID reader that behaves as a keyboard.
 *
 * USB HID readers type their payload and finish with Enter, which arrives as a
 * burst of keystrokes with no field focused. Buffering is the whole mechanism;
 * the timeout discards a half-typed code so a stray key pressed minutes earlier
 * cannot end up prefixed onto the next real scan.
 *
 * Kept separate from the hook, and free of React and the DOM, so the rules above
 * can be tested — the test environment is `node`, with no renderer.
 *
 * @param {(code: string) => void} onScan
 * @param {{timeout?: number}} options
 */
export function createScanBuffer(onScan, { timeout = 500 } = {}) {
    let buffer = '';
    let timer = null;

    const cancelDiscard = () => {
        if (timer) clearTimeout(timer);
        timer = null;
    };

    return {
        /** @param {{key: string, target?: {tagName?: string}}} event */
        handleKey(event) {
            // Typing an order number into an input must not also read as a scan.
            const tag = event.target?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            if (event.key === 'Enter') {
                const code = buffer.trim();
                buffer = '';
                cancelDiscard();
                if (code) onScan(code);
            } else if (event.key.length === 1) {
                buffer += event.key;
                cancelDiscard();
                timer = setTimeout(() => {
                    buffer = '';
                    timer = null;
                }, timeout);
            }
        },
        reset() {
            buffer = '';
            cancelDiscard();
        },
    };
}

/**
 * Listen for a keyboard-wedge reader for as long as the component is mounted.
 *
 * @param {(code: string) => void} onScan  called with each completed scan
 * @param {{enabled?: boolean, timeout?: number}} options
 *   enabled: false detaches the listener entirely — for `manual` scanner mode,
 *   where the operator types into a field instead.
 */
export default function useScanBuffer(onScan, options = {}) {
    const { enabled = true, timeout = 500 } = options;

    // The callback is read through a ref so that a re-render (which gives a
    // fresh onScan) does not detach and re-attach the listener, and cannot drop
    // a buffer mid-scan.
    const onScanRef = useRef(onScan);

    useEffect(() => {
        onScanRef.current = onScan;
    }, [onScan]);

    useEffect(() => {
        if (! enabled) return undefined;

        const scanner = createScanBuffer((code) => onScanRef.current?.(code), { timeout });
        const onKey = (e) => scanner.handleKey(e);

        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('keydown', onKey);
            scanner.reset();
        };
    }, [enabled, timeout]);
}
