import { describe, expect, it } from 'vitest';

import { ADMIN_GROUPS, ADMIN_LINKS } from './adminNav';
import { flattenNavItems, groupVisible } from '../lib/navSearch';

/** The admin tree's own rule (AppLayout useNavTree): a tab must be accessible. */
const showTabs = (tabs) => (key) => !key || tabs.includes(key);

const hrefsShown = (tabs) => {
    const showTab = showTabs(tabs);
    const visible = ADMIN_GROUPS.filter((group) => groupVisible(group, showTab));
    return flattenNavItems(showTab, visible, ADMIN_LINKS).map((item) => item.href);
};

describe('admin nav with an optional module switched off', () => {
    // The Warehouses group holds the material catalogue (Materials & tracing
    // module) and the pallets (Packaging module). It was gated by the
    // Warehouses module as a whole, so switching that one off hid the
    // material screens too — still enabled, still reachable by URL, just gone
    // from the menu.
    it('keeps the material catalogue when only the Warehouses module is off', () => {
        const hrefs = hrefsShown(['materials', 'packaging']);

        expect(hrefs).toEqual(expect.arrayContaining([
            '/admin/materials', '/admin/material-types', '/admin/material-lots', '/admin/pallets',
        ]));
    });

    it('hides the material catalogue when Materials & tracing is off', () => {
        const hrefs = hrefsShown(['packaging']);

        expect(hrefs).not.toContain('/admin/materials');
        expect(hrefs).not.toContain('/admin/material-types');
        expect(hrefs).toContain('/admin/pallets');
    });
});
