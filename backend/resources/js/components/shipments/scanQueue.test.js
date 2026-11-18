import { describe, it, expect, vi } from 'vitest';
import { createScanQueue } from './scanQueue';

/** A send() whose calls stay in flight until the test settles them. */
function controlledSend() {
    const calls = [];
    const send = vi.fn((code) => new Promise((resolve, reject) => calls.push({ code, resolve, reject })));
    return { send, calls };
}

const flush = () => new Promise((r) => setTimeout(r, 0));

describe('createScanQueue', () => {
    it('sends a code at once when nothing is in flight', () => {
        const { send } = controlledSend();
        createScanQueue(send).push('PAL-1');

        expect(send).toHaveBeenCalledExactlyOnceWith('PAL-1');
    });

    it('never drops a code scanned while a request is in flight', async () => {
        const { send, calls } = controlledSend();
        const queue = createScanQueue(send);

        queue.push('PAL-1');
        queue.push('PAL-2');
        queue.push('PAL-3');
        expect(send).toHaveBeenCalledTimes(1);
        expect(queue.pending()).toBe(3);

        calls[0].resolve();
        await flush();
        expect(send).toHaveBeenCalledTimes(2);
        calls[1].resolve();
        await flush();
        calls[2].resolve();
        await flush();

        expect(send.mock.calls).toEqual([['PAL-1'], ['PAL-2'], ['PAL-3']]);
        expect(queue.pending()).toBe(0);
    });

    it('keeps going after a failed send', async () => {
        const { send, calls } = controlledSend();
        const queue = createScanQueue(send);

        queue.push('A');
        queue.push('B');
        calls[0].reject(new Error('offline'));
        await flush();

        expect(send.mock.calls).toEqual([['A'], ['B']]);
    });

    it('reports when it becomes busy and idle again', async () => {
        const { send, calls } = controlledSend();
        const onBusyChange = vi.fn();
        const queue = createScanQueue(send, onBusyChange);

        queue.push('A');
        queue.push('B');
        calls[0].resolve();
        await flush();
        calls[1].resolve();
        await flush();

        expect(onBusyChange.mock.calls).toEqual([[true], [false]]);
    });

    it('ignores blank codes', () => {
        const { send } = controlledSend();
        const queue = createScanQueue(send);
        queue.push('   ');
        queue.push('');

        expect(send).not.toHaveBeenCalled();
    });
});
