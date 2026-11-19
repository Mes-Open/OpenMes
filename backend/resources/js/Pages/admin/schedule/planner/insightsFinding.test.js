import { describe, expect, it } from 'vitest';
import {
    badgeOpens, emptyReason, handleTone, insightForCell, insightForOrder, isCloseShortcut, isToggleShortcut, isTypingTarget, markSessionSeen, rangeCaption,
    openOn, readAutoOpen, sessionSeen, shouldAutoOpen, writeAutoOpen,
} from './insightsState';

const short = { key: 'short:1:2026-11-24:1', type: 'short_shift', severity: 'bad', cells: [{ line_id: 1, date: '2026-11-24', shift: 1 }], affected: [{ work_order_id: 401 }] };
const shared = { key: 'shared:1:2026-11-24:1', type: 'shared_operator', severity: 'warn', cells: [{ line_id: 1, date: '2026-11-24', shift: 1 }, { line_id: 2, date: '2026-11-24', shift: 1 }], affected: [{ work_order_id: 401 }, { work_order_id: 402 }] };
const untracked = { key: 'untracked:3:2026-W48', type: 'untracked_line', severity: 'info', cells: [], affected: [{ work_order_id: 405 }] };
const drawer = { summary: { needs_action: 1, to_watch: 2, resolved: 0 }, insights: [shared, untracked, short], resolved: [] };

function memoryStorage() {
    const m = new Map();
    return { getItem: (k) => (m.has(k) ? m.get(k) : null), setItem: (k, v) => m.set(k, String(v)), removeItem: (k) => m.delete(k) };
}
const throwing = { getItem() { throw new Error('denied'); }, setItem() { throw new Error('denied'); }, removeItem() { throw new Error('denied'); } };

describe('edge handle tone', () => {
    it('is red with something to act on, amber with only things to watch, neutral otherwise', () => {
        expect(handleTone(drawer)).toBe('bad');
        expect(handleTone({ insights: [shared, untracked] })).toBe('warn');
        expect(handleTone({ insights: [] })).toBe('neutral');
        expect(handleTone(null)).toBe('neutral');
    });
});

describe('badge and marker → insight', () => {
    it('picks the most pressing insight of a cell, short shift first', () => {
        expect(insightForCell(drawer, 1, '2026-11-24', 1)).toBe(short);
        expect(insightForCell(drawer, 2, '2026-11-24', 1)).toBe(shared);
        expect(insightForCell(drawer, 1, '2026-11-25', 1)).toBeNull();
        expect(insightForCell(null, 1, '2026-11-24', 1)).toBeNull();
    });
    it('does not depend on the server order of the list', () => {
        expect(insightForCell({ insights: [short, shared] }, 1, '2026-11-24', 1)).toBe(short);
    });
    it('finds an order by its affected list', () => {
        expect(insightForOrder(drawer, 401)).toBe(short);
        expect(insightForOrder(drawer, 402)).toBe(shared);
        expect(insightForOrder(drawer, 405)).toBe(untracked);
        expect(insightForOrder(drawer, 999)).toBeNull();
    });
    it('opens the drawer on the insight: filter reset, expanded, highlighted, focused', () => {
        const before = { open: false, filter: 'skills', openKey: null, highlight: new Set(), focusKey: null };
        const after = openOn(before, short);
        expect(after.open).toBe(true);
        expect(after.filter).toBe('all');
        expect(after.openKey).toBe(short.key);
        expect(after.focusKey).toBe(short.key);
        expect([...after.highlight]).toEqual(['1|2026-11-24|1']);
    });
    it('without an insight just opens the drawer as it was', () => {
        const before = { open: false, filter: 'people', openKey: 'x', highlight: new Set(), focusKey: null };
        expect(openOn(before, null)).toEqual({ ...before, open: true });
    });
});

describe('auto-open once per session', () => {
    it('opens on the first visit when something needs action', () => {
        expect(shouldAutoOpen({ drawer, enabled: true, seen: false, open: false })).toBe(true);
    });
    it('stays closed when already seen, turned off, already open, nothing to act on or not loaded', () => {
        expect(shouldAutoOpen({ drawer, enabled: true, seen: true, open: false })).toBe(false);
        expect(shouldAutoOpen({ drawer, enabled: false, seen: false, open: false })).toBe(false);
        expect(shouldAutoOpen({ drawer, enabled: true, seen: false, open: true })).toBe(false);
        expect(shouldAutoOpen({ drawer: { ...drawer, summary: { needs_action: 0 } }, enabled: true, seen: false, open: false })).toBe(false);
        expect(shouldAutoOpen({ drawer: null, enabled: true, seen: false, open: false })).toBe(false);
    });
    it('remembers the session visit per user', () => {
        const s = memoryStorage();
        expect(sessionSeen(s, 7)).toBe(false);
        markSessionSeen(s, 7);
        expect(sessionSeen(s, 7)).toBe(true);
        expect(sessionSeen(s, 8)).toBe(false);
    });
    it('persists "Don\'t open automatically" per user, default on', () => {
        const s = memoryStorage();
        expect(readAutoOpen(s, 7)).toBe(true);
        writeAutoOpen(s, 7, false);
        expect(readAutoOpen(s, 7)).toBe(false);
        expect(readAutoOpen(s, 8)).toBe(true);
        writeAutoOpen(s, 7, true);
        expect(readAutoOpen(s, 7)).toBe(true);
    });
    it('survives storage that throws or is missing', () => {
        expect(readAutoOpen(throwing, 7)).toBe(true);
        expect(() => writeAutoOpen(throwing, 7, false)).not.toThrow();
        expect(sessionSeen(throwing, 7)).toBe(false);
        expect(() => markSessionSeen(throwing, 7)).not.toThrow();
        expect(readAutoOpen(undefined, 7)).toBe(true);
    });
});

describe('the I shortcut', () => {
    const key = (k, extra = {}) => ({ key: k, target: { tagName: 'DIV' }, ...extra });
    it('toggles on a plain i or I', () => {
        expect(isToggleShortcut(key('i'))).toBe(true);
        expect(isToggleShortcut(key('I'))).toBe(true);
    });
    it('is ignored while typing, with modifiers, on repeat, other keys or with a modal open', () => {
        expect(isToggleShortcut(key('i', { target: { tagName: 'INPUT' } }))).toBe(false);
        expect(isToggleShortcut(key('i', { target: { tagName: 'textarea' } }))).toBe(false);
        expect(isToggleShortcut(key('i', { target: { tagName: 'SELECT' } }))).toBe(false);
        expect(isToggleShortcut(key('i', { target: { tagName: 'DIV', isContentEditable: true } }))).toBe(false);
        expect(isToggleShortcut(key('i', { ctrlKey: true }))).toBe(false);
        expect(isToggleShortcut(key('i', { metaKey: true }))).toBe(false);
        expect(isToggleShortcut(key('i', { altKey: true }))).toBe(false);
        expect(isToggleShortcut(key('i', { repeat: true }))).toBe(false);
        expect(isToggleShortcut(key('i', { defaultPrevented: true }))).toBe(false);
        expect(isToggleShortcut(key('j'))).toBe(false);
        expect(isToggleShortcut(key('i'), { modalOpen: true })).toBe(false);
    });
    it('knows typing targets', () => {
        expect(isTypingTarget(null)).toBe(false);
        expect(isTypingTarget({ tagName: 'BUTTON' })).toBe(false);
        expect(isTypingTarget({ tagName: 'INPUT' })).toBe(true);
        expect(isTypingTarget({ tagName: 'INPUT', type: 'text' })).toBe(true);
        expect(isTypingTarget({ tagName: 'INPUT', type: 'checkbox' })).toBe(false);
    });
});

describe('Esc', () => {
    const key = (k, extra = {}) => ({ key: k, target: { tagName: 'DIV' }, ...extra });
    it('closes the open drawer', () => {
        expect(isCloseShortcut(key('Escape'), { open: true })).toBe(true);
        expect(isCloseShortcut(key('Esc'), { open: true })).toBe(true);
    });
    it('leaves a confirm dialog to close first, and does nothing when the drawer is closed', () => {
        expect(isCloseShortcut(key('Escape'), { open: true, modalOpen: true })).toBe(false);
        expect(isCloseShortcut(key('Escape'), { open: false })).toBe(false);
        expect(isCloseShortcut(key('Escape', { defaultPrevented: true }), { open: true })).toBe(false);
        expect(isCloseShortcut(key('Escape', { repeat: true }), { open: true })).toBe(false);
        expect(isCloseShortcut(key('i'), { open: true })).toBe(false);
    });
});

describe('badges that open the drawer', () => {
    const drawer = {
        insights: [{ key: 'short:1:2026-11-24:1', severity: 'bad', type: 'short_shift', cells: [{ line_id: 1, date: '2026-11-24', shift: 1 }] }],
    };
    it('opens only from a badge an insight is about', () => {
        expect(badgeOpens(drawer, 1, '2026-11-24', 1)).toBe(true);
        // An amber "exactly enough" badge next door has no insight: tooltip only.
        expect(badgeOpens(drawer, 2, '2026-11-24', 1)).toBe(false);
        expect(badgeOpens(drawer, 1, '2026-11-24', 2)).toBe(false);
        expect(badgeOpens(null, 1, '2026-11-24', 1)).toBe(false);
    });
});

describe('the drawer header and its empty state', () => {
    it('names the range shown', () => {
        expect(rangeCaption({ range: { start: '2026-12-07', end: '2026-12-13' } }, 'weekly')).toEqual({ key: 'Week :from – :to', params: { from: '07.12', to: '13.12' } });
        expect(rangeCaption({ range: { start: '2026-11-24', end: '2026-11-24' } }, 'daily')).toEqual({ key: 'Day :date', params: { date: '24.11' } });
        expect(rangeCaption(null, 'weekly')).toBeNull();
    });
    it('says why nothing is open, even with resolved entries below', () => {
        const quiet = { insights: [], resolved: [{ id: 1, category: 'people' }] };
        expect(emptyReason(quiet, 'all', 'weekly')).toBe('Nothing needs attention in this week: every shift has the people its work needs.');
        expect(emptyReason(quiet, 'people', 'daily')).toBe('Nothing needs attention on this day: every shift has the people its work needs.');
        expect(emptyReason(quiet, 'skills')).toBe('Nothing here.');
        expect(emptyReason({ insights: [{ key: 'a', category: 'people' }] }, 'all')).toBeNull();
        expect(emptyReason({ insights: [{ key: 'a', category: 'plan' }] }, 'people')).toBe('Nothing needs attention in this week: every shift has the people its work needs.');
        expect(emptyReason(null)).toBeNull();
    });
});
