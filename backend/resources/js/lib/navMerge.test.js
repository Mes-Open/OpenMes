import { describe, expect, it } from 'vitest';

import { byOrder, DEFAULT_MODULE_ORDER, groupMatch, mergeChildren, mergeGroups } from './navMerge';

/**
 * Where a module's menu contributions land in the sidebar.
 *
 * Before this, module groups were appended after everything, so an extension
 * always trailed behind Settings no matter what it was. A workforce module
 * belongs next to Admin; a reporting add-on belongs beside Analytics. The
 * position is the module's to choose.
 */
describe('mergeGroups', () => {
    const builtIn = [
        { key: 'production', order: 30 },
        { key: 'warehouses', order: 40 },
        { key: 'adminGroup', order: 100 },
    ];

    it('leaves the built-in list alone when no module contributes', () => {
        expect(mergeGroups(builtIn, [])).toBe(builtIn);
        expect(mergeGroups(builtIn, undefined)).toBe(builtIn);
    });

    it('places a module group at the position it asked for', () => {
        const merged = mergeGroups(builtIn, [{ key: 'module:hr', order: 45 }]);

        expect(merged.map((g) => g.key)).toEqual(['production', 'warehouses', 'module:hr', 'adminGroup']);
    });

    it('puts a group with no stated position after everything built in', () => {
        // The right default for a module whose author has no opinion — better
        // than landing somewhere arbitrary in the middle.
        const merged = mergeGroups(builtIn, [{ key: 'module:whatever' }]);

        expect(merged[merged.length - 1].key).toBe('module:whatever');
        expect(merged[merged.length - 1].order).toBe(DEFAULT_MODULE_ORDER);
    });

    it('does not let a module displace a core group by matching its number', () => {
        // Ties keep registration order and built-ins are listed first, so a
        // module cannot push a core group down by copying its position.
        const merged = mergeGroups(builtIn, [{ key: 'module:squatter', order: 40 }]);

        expect(merged.map((g) => g.key)).toEqual([
            'production', 'warehouses', 'module:squatter', 'adminGroup',
        ]);
    });

    it('interleaves several module groups among the built-ins', () => {
        const merged = mergeGroups(builtIn, [
            { key: 'module:late', order: 110 },
            { key: 'module:early', order: 20 },
        ]);

        expect(merged.map((g) => g.key)).toEqual([
            'module:early', 'production', 'warehouses', 'adminGroup', 'module:late',
        ]);
    });
});

describe('mergeChildren', () => {
    const children = [{ label: 'First' }, { label: 'Second' }, { label: 'Third' }];

    it('leaves the group alone when a module contributes nothing', () => {
        expect(mergeChildren(children, [])).toBe(children);
    });

    it('slots a contributed link between existing ones', () => {
        // Built-in children carry no order of their own — their position in the
        // file is the intent — so they are numbered 10, 20, 30 to share a scale
        // with what a module asks for.
        const merged = mergeChildren(children, [{ label: 'Injected', order: 15 }]);

        expect(merged.map((c) => c.label)).toEqual(['First', 'Injected', 'Second', 'Third']);
    });

    it('appends a contributed link that states no position', () => {
        const merged = mergeChildren(children, [{ label: 'Trailing' }]);

        expect(merged[merged.length - 1].label).toBe('Trailing');
    });

    it('keeps the built-in children in their file order', () => {
        const merged = mergeChildren(children, [{ label: 'X', order: 999 }]);

        expect(merged.slice(0, 3).map((c) => c.label)).toEqual(['First', 'Second', 'Third']);
    });
});

describe('byOrder', () => {
    it('does not mutate what it was given', () => {
        const input = [{ order: 2 }, { order: 1 }];
        const sorted = byOrder(input);

        expect(input.map((e) => e.order)).toEqual([2, 1]);
        expect(sorted.map((e) => e.order)).toEqual([1, 2]);
    });

    it('keeps equal orders in the order they arrived', () => {
        const sorted = byOrder([{ key: 'a', order: 5 }, { key: 'b', order: 5 }]);

        expect(sorted.map((e) => e.key)).toEqual(['a', 'b']);
    });
});

/**
 * A group highlights (and auto-expands) when the page is under one of its
 * `match` prefixes. Built-in groups list their own children by hand; links a
 * module injects were never on that list, so the group stayed dark on exactly
 * the pages the module added — while its core siblings lit it up.
 */
describe('groupMatch', () => {
    it('adds the paths of injected children to the declared list', () => {
        const match = groupMatch(
            ['/admin/materials'],
            [{ href: '/admin/warehouses', match: ['/admin/warehouses'] }, { href: '/admin/stock-documents' }],
        );

        expect(match).toEqual(['/admin/materials', '/admin/warehouses', '/admin/stock-documents']);
    });

    it('gives a module-only group a list built from its children', () => {
        expect(groupMatch(undefined, [{ href: '/admin/sites' }, { href: '/admin/areas' }]))
            .toEqual(['/admin/sites', '/admin/areas']);
    });

    it('does not repeat a path the group already lists', () => {
        expect(groupMatch(['/admin/materials'], [{ href: '/admin/materials' }])).toEqual(['/admin/materials']);
    });

    it('leaves a group with nothing injected as declared', () => {
        expect(groupMatch(['/admin/materials'], [])).toEqual(['/admin/materials']);
    });
});
