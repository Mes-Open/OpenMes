import { describe, expect, it } from 'vitest';
import { FILTERS, cellKey, createInsightsGate, filterCounts, groupInsights, highlightOf, indexStaffing, openCount, qualityHoldFor, toggle } from './insightsState';

const drawer = {
    insights: [
        { key: 'short:1:2026-11-24:1', category: 'people', severity: 'bad', cells: [{ line_id: 1, date: '2026-11-24', shift: 1 }] },
        { key: 'shared:2:2026-11-23:2', category: 'people', severity: 'warn', cells: [] },
        { key: 'skill:3:7:2026-11-23', category: 'skills', severity: 'warn', cells: [{ line_id: 3, date: '2026-11-25', shift: 1 }, { line_id: 3, date: '2026-11-26', shift: 1 }] },
        { key: 'untracked:4:2026-11-23', category: 'plan', severity: 'info', cells: [] },
    ],
    resolved: [{ id: 9, category: 'people', insight_key: 'short:1:2026-11-23:2' }],
};

describe('groupInsights', () => {
    it('groups by needs action, to watch and resolved', () => {
        const groups = groupInsights(drawer);
        expect(groups.map((g) => g.key)).toEqual(['action', 'watch', 'resolved']);
        expect(groups[0].items).toHaveLength(1);
        expect(groups[1].items.map((i) => i.category)).toEqual(['people', 'skills', 'plan']);
    });
    it('filters every group by category and drops empty ones', () => {
        expect(groupInsights(drawer, 'skills').map((g) => g.key)).toEqual(['watch']);
        expect(groupInsights(drawer, 'people').map((g) => g.key)).toEqual(['action', 'watch', 'resolved']);
        expect(groupInsights(null)).toEqual([]);
    });
});

describe('counts and highlights', () => {
    it('counts open insights per filter', () => {
        expect(filterCounts(drawer)).toEqual({ all: 4, people: 2, skills: 1, quality: 0, plan: 1 });
        expect(openCount(drawer)).toBe(4);
        expect(openCount(undefined)).toBe(0);
    });
    it('turns an insight into the board cells to highlight', () => {
        expect([...highlightOf(drawer.insights[2])]).toEqual(['3|2026-11-25|1', '3|2026-11-26|1']);
        expect(highlightOf(null).size).toBe(0);
    });
    it('indexes staffing cells by line, date and shift', () => {
        const idx = indexStaffing({ cells: [{ line_id: 1, date: '2026-11-24', shift: 2, available: 2, needed: 3 }] });
        expect(idx[cellKey(1, '2026-11-24', 2)].needed).toBe(3);
    });
    it('toggles the expanded insight', () => {
        expect(toggle(null, 'a')).toBe('a');
        expect(toggle('a', 'a')).toBe(null);
        expect(toggle('a', 'b')).toBe('b');
    });
});

describe('createInsightsGate', () => {
    it('applies responses in order and drops a read overtaken by a newer read', () => {
        const gate = createInsightsGate();
        const first = gate.startFetch();
        const second = gate.startFetch();
        expect(gate.accept(second)).toBe(true);
        expect(gate.accept(first)).toBe(false);
    });
    it('never lets a poll started before an acknowledge resurrect the insight', () => {
        const gate = createInsightsGate();
        const poll = gate.startFetch();      // 60 s poll in flight, still sees the insight open
        const ack = gate.startAction();      // planner clicks Acknowledge
        expect(gate.accept(ack)).toBe(true); // the drawer now shows it resolved
        gate.endAction(ack);
        expect(gate.accept(poll)).toBe(false);
    });
    it('drops a read that started while a write was running, even if it answers later', () => {
        const gate = createInsightsGate();
        const move = gate.startAction();
        const live = gate.startFetch();      // live refresh fired mid-write
        expect(gate.accept(move)).toBe(true);
        gate.endAction(move);
        expect(gate.accept(live)).toBe(false); // may have read the board before the write committed
        const after = gate.startFetch();
        expect(gate.accept(after)).toBe(true);
    });
    it('a failed write releases the gate for the next read', () => {
        const gate = createInsightsGate();
        const failed = gate.startAction();
        gate.endAction(failed);              // 422 — nothing applied
        const next = gate.startFetch();
        expect(gate.accept(next)).toBe(true);
    });
});

describe('quality holds', () => {
    const held = { key: 'quality:7', type: 'quality_hold', category: 'quality', severity: 'bad', affected: [{ work_order_id: 7 }] };
    const short = { key: 'short:1:2026-11-23:1', type: 'short_shift', category: 'people', severity: 'bad', affected: [{ work_order_id: 7 }] };
    const qd = { insights: [short, held], resolved: [] };

    it('finds the quality insight of an order, not another one about it', () => {
        expect(qualityHoldFor(qd, 7)).toBe(held);
        expect(qualityHoldFor(qd, 8)).toBeNull();
        expect(qualityHoldFor(null, 7)).toBeNull();
    });
    it('counts and filters the quality chip', () => {
        expect(filterCounts(qd).quality).toBe(1);
        expect(groupInsights(qd, 'quality')[0].items).toEqual([held]);
        expect(FILTERS).toContain('quality');
    });
});
