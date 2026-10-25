import { describe, expect, it } from 'vitest';
import { setTimezone } from '../../../lib/i18n';
import { niceMax, paceText } from './PlannedVsActual';
import { barLabel, summaryText } from './OutputPerHour';

describe('planned vs actual helpers', () => {
    it('rounds the y axis up to four nice steps', () => {
        expect(niceMax(1312)).toBe(1600);
        expect(niceMax(800)).toBe(800);
        expect(niceMax(0)).toBe(4);
        expect(niceMax(37)).toBe(40);
    });

    it('words the pace with a sign, and no pace without a plan', () => {
        expect(paceText(-179).text).toBe('−179 pcs behind pace');
        expect(paceText(40).text).toBe('+40 pcs ahead of pace');
        expect(paceText(0).text).toBe('On pace');
        expect(paceText(null)).toBeNull();
    });
});

describe('output per hour summary', () => {
    it('states since when the shift is below plan and the unplanned downtime', () => {
        setTimezone('UTC');
        const s = summaryText({ kind: 'below', since: '2026-10-20T09:00:00+00:00', downtime_minutes: 47, downtime_lines: ['L5', 'L3'] });
        expect(s.text).toBe('Below plan since 09:00 · 47 min unplanned downtime on L5, L3');
    });

    it('omits downtime when there was none, and says nothing for an unknown kind', () => {
        setTimezone('UTC');
        expect(summaryText({ kind: 'below', since: '2026-10-20T09:00:00Z', downtime_minutes: null, downtime_lines: [] }).text)
            .toBe('Below plan since 09:00');
        expect(summaryText({ kind: 'no_plan' }).text).toBe('No plan scheduled for this shift');
        expect(summaryText({ kind: 'no_lines' })).toBeNull();
    });
});

describe('bar labels', () => {
    it('shortens thousands only when the columns are narrow', () => {
        setTimezone('UTC');
        expect(barLabel(5318, false)).toBe('5,318');
        // en-GB's compact suffix is "K" or "k" depending on the CLDR data the
        // runtime ships (Node 20 vs 22), so only the shortening is asserted.
        expect(barLabel(5318, true)).toMatch(/^5\.3\s?[Kk]$/);
        expect(barLabel(840, true)).toBe('840');
    });
});
