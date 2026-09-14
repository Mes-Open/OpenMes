/**
 * Reference-counted private channels on one Echo instance.
 *
 * `echo.private(name)` hands every caller the SAME channel object, and
 * `echo.leave(name)` unsubscribes it for all of them. Two synced collections
 * routinely hold one channel at once: an Inertia visit back to the same list
 * (the redirect after a create or delete) mounts a new collection while the old
 * one waits out its gcTime — and when that old one cleaned up with a plain
 * `leave`, it silently unsubscribed the new one, so the list stopped receiving
 * live rows until the browser was refreshed.
 *
 * Each user joins with its own handlers and gets back a release function that
 * detaches only those handlers; the channel itself is left only when its last
 * user releases it.
 */
export function createSharedChannels(echo) {
    const users = new Map();

    /**
     * @param {string} name       channel name (without the "private-" prefix)
     * @param {string} event      event to listen for, e.g. '.changed'
     * @param {Function} onEvent
     * @param {Function} onSubscribed  called on every (re)subscription
     * @returns {() => void} release
     */
    const join = (name, event, onEvent, onSubscribed) => {
        users.set(name, (users.get(name) ?? 0) + 1);

        const channel = echo.private(name);
        channel.listen(event, onEvent);
        // Bound directly rather than via `channel.subscribed()`, which wraps the
        // callback and so could never be unbound again.
        channel.on('pusher:subscription_succeeded', onSubscribed);

        let released = false;
        return () => {
            if (released) return;
            released = true;

            channel.stopListening(event, onEvent);
            channel.subscription?.unbind('pusher:subscription_succeeded', onSubscribed);

            const left = (users.get(name) ?? 1) - 1;
            if (left > 0) {
                users.set(name, left);
            } else {
                users.delete(name);
                echo.leave(name);
            }
        };
    };

    return { join, userCount: (name) => users.get(name) ?? 0 };
}
