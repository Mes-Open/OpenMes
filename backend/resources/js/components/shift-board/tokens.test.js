import { describe, expect, it } from 'vitest';
import { STATE_TONE, elapsedLabel, isDowntime } from './tokens';

// Fixed reference "now" so the ticking clock is deterministic.
const NOW = Date.UTC(2026, 8, 13, 12, 0, 0);
const ago = (seconds) => new Date(NOW - seconds * 1000).toISOString();

describe('elapsedLabel', () => {
    it('has nothing to show without a start', () => {
        expect(elapsedLabel(null, NOW)).toBeNull();
        expect(elapsedLabel(undefined, NOW)).toBeNull();
        expect(elapsedLabel('not-a-date', NOW)).toBeNull();
    });

    it('counts seconds from the start of the state', () => {
        expect(elapsedLabel(ago(0), NOW)).toBe('00:00');
        expect(elapsedLabel(ago(7), NOW)).toBe('00:07');
        expect(elapsedLabel(ago(65), NOW)).toBe('01:05');
    });

    it('keeps a fixed width so the digits do not jump while being watched', () => {
        expect(elapsedLabel(ago(9 * 60), NOW)).toBe('09:00');
        expect(elapsedLabel(ago(59 * 60 + 59), NOW)).toBe('59:59');
    });

    it('grows an hours field rather than rolling over', () => {
        // A stop that runs past the hour is the one somebody is standing in
        // front of; showing it as 00:12 again would be the worst possible lie.
        expect(elapsedLabel(ago(3600), NOW)).toBe('1:00:00');
        expect(elapsedLabel(ago(3600 + 12 * 60 + 5), NOW)).toBe('1:12:05');
        expect(elapsedLabel(ago(26 * 3600), NOW)).toBe('26:00:00');
    });

    it('floors at zero when the browser clock runs behind the server', () => {
        // Counting backwards would make a fresh stop look like it had not
        // happened yet.
        expect(elapsedLabel(new Date(NOW + 30_000).toISOString(), NOW)).toBe('00:00');
    });
});

describe('isDowntime', () => {
    it('does not put a clock on a healthy machine', () => {
        // The clock measures downtime, not uptime. A machine running since
        // yesterday would otherwise carry a 29-hour counter as the largest
        // thing on the tile, which reads as an alarm on a tile that is fine.
        expect(isDowntime('RUNNING', null)).toBe(false);
        expect(isDowntime('IDLE', null)).toBe(false);
        expect(isDowntime(null, null)).toBe(false);
    });

    it('counts every state the machine opens a stop for', () => {
        // Mirrors WorkstationState::DOWNTIME_STATES — planned downtime is still
        // downtime, and still worth knowing the length of.
        ['STOPPED', 'FAULT', 'WAITING', 'CLEANING', 'MAINTENANCE']
            .forEach((state) => expect(isDowntime(state, null)).toBe(true));
    });

    it('counts a stop raised by a human the collector has not caught up with', () => {
        expect(isDowntime('IDLE', { name: 'No material' })).toBe(true);
    });
});

describe('STATE_TONE', () => {
    it('colours every unplanned loss the same way', () => {
        // Mirrors WorkstationState::LOSS_STATES. WAITING included: it is lost
        // time, and a starved machine that reads as resting goes unattended.
        const loss = [STATE_TONE.STOPPED, STATE_TONE.FAULT, STATE_TONE.WAITING];

        loss.forEach((tone) => expect(tone.color).toBe(STATE_TONE.STOPPED.color));
        expect(STATE_TONE.RUNNING.color).not.toBe(STATE_TONE.STOPPED.color);
    });

    it('separates planned downtime from loss', () => {
        // Planned time reduces the operating window without counting against
        // availability — same colour here as the OEE report gives it.
        expect(STATE_TONE.CLEANING.color).toBe(STATE_TONE.MAINTENANCE.color);
        expect(STATE_TONE.CLEANING.color).not.toBe(STATE_TONE.STOPPED.color);
    });

    it('tells a silent station apart from an idle one', () => {
        // A station nothing has been heard from must not read as a quiet line,
        // or a dead collector never gets investigated.
        expect(STATE_TONE.unknown.background).not.toBe(STATE_TONE.IDLE.background);
    });
});
