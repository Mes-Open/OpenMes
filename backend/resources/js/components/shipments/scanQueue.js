/**
 * The loading terminal's scan queue (#400).
 *
 * A keyboard-wedge scanner fires the next code whenever the operator pulls the
 * trigger, whether or not the previous scan has been answered. Every code is
 * kept and sent one after the other, in the order it was scanned; a failed
 * send does not stop the ones behind it (send() reports its own errors).
 *
 * @param {(code: string) => Promise<unknown>} send  sends one code
 * @param {(busy: boolean) => void} [onBusyChange]  true when work starts, false when the queue drains
 */
export function createScanQueue(send, onBusyChange = () => {}) {
    const waiting = [];
    let inFlight = false;

    const next = async () => {
        if (inFlight) return;
        inFlight = true;
        onBusyChange(true);
        while (waiting.length > 0) {
            try {
                await send(waiting[0]);
            } catch {
                // send() shows its own error; the queue carries on.
            }
            waiting.shift();
        }
        inFlight = false;
        onBusyChange(false);
    };

    return {
        /** Queue one scanned code; blank input is ignored. */
        push(code) {
            const value = String(code ?? '').trim();
            if (!value) return;
            waiting.push(value);
            next();
        },
        /** Codes not yet answered, the one in flight included. */
        pending: () => waiting.length,
    };
}
