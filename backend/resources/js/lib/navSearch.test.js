import { describe, expect, it } from 'vitest';

import { ADMIN_GROUPS, ADMIN_LINKS } from '../layouts/adminNav';
import { filterNavItems, flattenNavItems, searchResultKeys } from './navSearch';

const showAll = () => true;

/**
 * A Settings group that already points at /settings, plus one page under it.
 * The footer link must not be indexed a second time.
 */
const settingsGroup = {
    key: 'settings',
    label: 'Settings',
    href: '/settings',
    alwaysVisible: true,
    children: [
        { label: 'Profile', href: '/settings/profile' },
    ],
};

/** A heading with no page of its own. Search still has to find its children. */
const connectivityGroup = {
    key: 'connectivity',
    label: 'Connectivity',
    children: [
        { label: 'Overview', href: '/admin/connectivity' },
        { label: 'MQTT', href: '/admin/connectivity/mqtt' },
    ],
};

/** A different page whose own name contains "settings". */
const ordersGroup = {
    key: 'orders',
    label: 'Orders',
    href: '/admin/work-orders',
    children: [
        { label: 'Priority Settings', href: '/admin/priority-rules' },
    ],
};

describe('flattenNavItems', () => {
    it('does not add a second Settings row when the group is already there', () => {
        const items = flattenNavItems(showAll, [settingsGroup], []);
        const settings = items.filter((item) => item.label === 'Settings' && item.href === '/settings');

        expect(settings).toHaveLength(1);
        expect(items.map((item) => item.label)).toEqual(['Settings', 'Profile']);
    });

    it('adds the footer Settings row when the tree has no Settings group', () => {
        const items = flattenNavItems(showAll, [ordersGroup], []);
        const settings = items.filter((item) => item.label === 'Settings' && item.href === '/settings');

        expect(settings).toHaveLength(1);
        expect(items.map((item) => item.label)).toEqual(['Orders', 'Priority Settings', 'Settings']);
    });
});

describe('filterNavItems', () => {
    const items = flattenNavItems(showAll, [connectivityGroup, ordersGroup, settingsGroup], []);

    it('returns null for a blank query so the tree stays on screen', () => {
        expect(filterNavItems(items, '   ')).toBeNull();
    });

    it('lists one /settings row and the pages under Settings', () => {
        const results = filterNavItems(items, 'settings');

        expect(results.filter((item) => item.href === '/settings')).toHaveLength(1);
        expect(results.map((item) => item.label)).toEqual([
            'Priority Settings', 'Settings', 'Profile',
        ]);
    });

    it('lists the pages under a group when the query is the group name', () => {
        expect(filterNavItems(items, 'connectivity').map((item) => item.label)).toEqual([
            'Overview', 'MQTT',
        ]);
    });

    it('matches a translated label', () => {
        const results = filterNavItems(items, 'profil', (text) => (text === 'Profile' ? 'Profil' : text));

        expect(results.map((item) => item.label)).toEqual(['Profile']);
    });

    it('keeps the footer Settings row and a page named Priority Settings', () => {
        const supervisor = flattenNavItems(showAll, [ordersGroup], []);
        const results = filterNavItems(supervisor, 'settings');

        expect(results.filter((item) => item.href === '/settings')).toHaveLength(1);
        expect(results.map((item) => item.label)).toEqual(['Priority Settings', 'Settings']);
    });
});

describe('searchResultKeys', () => {
    it('gives two identical Settings rows different keys', () => {
        const row = { label: 'Settings', href: '/settings', trail: [] };

        expect(searchResultKeys([row, row])).toEqual(['>Settings', '>Settings#1']);
    });
});

/**
 * The duplicate key this bug depends on is a property of the shipped menu,
 * which a fixture cannot see. A second Settings entry added there would
 * bring the pile-up back.
 */
describe('admin menu', () => {
    it('indexes Settings once and gives every row its own key', () => {
        const items = flattenNavItems(showAll, ADMIN_GROUPS, ADMIN_LINKS);
        const settings = items.filter((item) => item.label === 'Settings' && item.href === '/settings');

        expect(settings).toHaveLength(1);
        expect(new Set(searchResultKeys(items)).size).toBe(items.length);
    });
});
