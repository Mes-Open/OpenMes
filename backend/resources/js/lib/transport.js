/**
 * Is the page served over a MULTIPLEXED transport (HTTP/2 or HTTP/3)?
 *
 * On HTTP/2+ the browser carries every request over one connection, so the
 * ~6-concurrent-connections-per-origin limit of HTTP/1.1 doesn't apply and every
 * shape can sync live. On HTTP/1.1 (the common LAN-over-plain-HTTP case) we must
 * budget held connections and poll the rest. See CLAUDE.md → "Electric
 * connection budget".
 *
 * The transport is fixed for the page's lifetime — it can't change without a
 * full reload — so it's detected once and frozen as a module constant.
 */
export function detectMultiplexed() {
    try {
        const nav = performance.getEntriesByType('navigation')[0];
        return nav?.nextHopProtocol === 'h2' || nav?.nextHopProtocol === 'h3';
    } catch {
        return false;
    }
}

export const MULTIPLEXED = detectMultiplexed();
