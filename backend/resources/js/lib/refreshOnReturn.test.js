import { describe, expect, it, vi } from 'vitest';
import { refreshOnReturn } from './refreshOnReturn';

function fakes() {
    const win = new EventTarget();
    const doc = new EventTarget();
    doc.visibilityState = 'visible';
    let t = 0;
    return { win, doc, now: () => t, tick: (ms) => { t += ms; } };
}

describe('refreshOnReturn', () => {
    it('checks when the window regains focus or the page becomes visible', () => {
        const { win, doc, now, tick } = fakes();
        const check = vi.fn();
        refreshOnReturn(check, { win, doc, now });

        win.dispatchEvent(new Event('focus'));
        expect(check).toHaveBeenCalledTimes(1);
        tick(5000);
        doc.dispatchEvent(new Event('visibilitychange'));
        expect(check).toHaveBeenCalledTimes(2);
    });

    it('collapses focus and visibility firing together, and skips a hidden page', () => {
        const { win, doc, now, tick } = fakes();
        const check = vi.fn();
        refreshOnReturn(check, { win, doc, now });

        doc.dispatchEvent(new Event('visibilitychange'));
        win.dispatchEvent(new Event('focus'));
        expect(check).toHaveBeenCalledTimes(1);

        tick(5000);
        doc.visibilityState = 'hidden';
        doc.dispatchEvent(new Event('visibilitychange'));
        expect(check).toHaveBeenCalledTimes(1);
    });

    it('stops listening once removed', () => {
        const { win, doc, now } = fakes();
        const check = vi.fn();
        const stop = refreshOnReturn(check, { win, doc, now });
        stop();
        win.dispatchEvent(new Event('focus'));
        expect(check).not.toHaveBeenCalled();
    });
});
