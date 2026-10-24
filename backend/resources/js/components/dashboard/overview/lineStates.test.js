import { describe, expect, it } from 'vitest';
import { LINE_STATES, LINE_STATE_TONE, hasLiveData, lineStateLabel, lineStateTone } from './lineStates';

describe('line state vocabulary', () => {
    it('names every state the server sends, "No data" included', () => {
        expect(LINE_STATES).toEqual(['running', 'idle', 'blocked', 'down', 'unknown']);
        expect(lineStateLabel('running')).toBe('Running');
        expect(lineStateLabel('down')).toBe('Down');
        expect(lineStateLabel('unknown')).toBe('No data');
        LINE_STATES.forEach((s) => expect(LINE_STATE_TONE[s]).toBeDefined());
    });

    it('paints "No data" hatched, not as an idle line', () => {
        expect(lineStateTone('unknown').pill.background).toContain('repeating-linear-gradient');
        expect(lineStateTone('unknown').pill.background).not.toBe(lineStateTone('idle').pill.background);
    });

    it('treats anything unrecognised as "No data"', () => {
        expect(lineStateLabel('bogus')).toBe('No data');
        expect(lineStateTone('bogus')).toBe(LINE_STATE_TONE.unknown);
    });

    it('reports unknown and missing lines as having no live data', () => {
        expect(hasLiveData({ state: 'unknown' })).toBe(false);
        expect(hasLiveData({ state: 'bogus' })).toBe(false);
        expect(hasLiveData(null)).toBe(false);
        expect(hasLiveData({ state: 'idle' })).toBe(true);
        expect(hasLiveData({ state: 'down' })).toBe(true);
    });
});
