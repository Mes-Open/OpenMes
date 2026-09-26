import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createScanBuffer } from './useScanBuffer';

/** A keydown as the reader delivers it: one character, nothing focused. */
const key = (k, tagName = 'BODY') => ({ key: k, target: { tagName } });

/** Type a whole code the way a reader does, Enter included. */
function scan(buffer, code, tagName = 'BODY') {
    for (const ch of code) buffer.handleKey(key(ch, tagName));
    buffer.handleKey(key('Enter', tagName));
}

describe('createScanBuffer', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('reports a code once the reader sends Enter', () => {
        const onScan = vi.fn();
        scan(createScanBuffer(onScan), '5901234567890');

        expect(onScan).toHaveBeenCalledExactlyOnceWith('5901234567890');
    });

    it('reports nothing until Enter arrives', () => {
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        for (const ch of '590123') buffer.handleKey(key(ch));

        expect(onScan).not.toHaveBeenCalled();
    });

    it('starts a fresh code after each scan', () => {
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        scan(buffer, 'AAA');
        scan(buffer, 'BBB');

        expect(onScan.mock.calls).toEqual([['AAA'], ['BBB']]);
    });

    it('discards a half-typed code after the timeout', () => {
        // Somebody brushing the keyboard must not leave a prefix sitting there
        // to be glued onto whatever is scanned next.
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        buffer.handleKey(key('x'));
        vi.advanceTimersByTime(500);
        scan(buffer, '123');

        expect(onScan).toHaveBeenCalledExactlyOnceWith('123');
    });

    it('keeps buffering while the keys keep coming', () => {
        // A reader's keystrokes are milliseconds apart, so the discard timer has
        // to restart on every one of them, not run from the first.
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        for (const ch of '12345') {
            buffer.handleKey(key(ch));
            vi.advanceTimersByTime(400);
        }
        buffer.handleKey(key('Enter'));

        expect(onScan).toHaveBeenCalledExactlyOnceWith('12345');
    });

    it('ignores keys typed into a form field', () => {
        // The station has its own inputs; typing into one is not a scan.
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        scan(buffer, '123', 'INPUT');
        scan(buffer, '456', 'TEXTAREA');
        scan(buffer, '789', 'SELECT');

        expect(onScan).not.toHaveBeenCalled();
    });

    it('reports nothing for a bare Enter', () => {
        const onScan = vi.fn();
        createScanBuffer(onScan).handleKey(key('Enter'));

        expect(onScan).not.toHaveBeenCalled();
    });

    it('ignores keys that are not characters', () => {
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        buffer.handleKey(key('Shift'));
        buffer.handleKey(key('ArrowLeft'));
        scan(buffer, '42');

        expect(onScan).toHaveBeenCalledExactlyOnceWith('42');
    });

    it('drops a partial code when reset', () => {
        // What unmounting does: the next mount must not inherit a prefix.
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan);

        buffer.handleKey(key('9'));
        buffer.reset();
        scan(buffer, '7');

        expect(onScan).toHaveBeenCalledExactlyOnceWith('7');
    });

    it('honours a custom timeout', () => {
        const onScan = vi.fn();
        const buffer = createScanBuffer(onScan, { timeout: 100 });

        buffer.handleKey(key('x'));
        vi.advanceTimersByTime(100);
        scan(buffer, 'y');

        expect(onScan).toHaveBeenCalledExactlyOnceWith('y');
    });
});
