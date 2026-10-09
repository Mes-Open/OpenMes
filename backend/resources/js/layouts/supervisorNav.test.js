import { describe, expect, it } from 'vitest';
import { SUPERVISOR_GROUPS, withGrantedAdminTabs } from './supervisorNav';

describe('supervisor granted admin tabs', () => {
    const schedule = { key: 'schedule', label: 'Schedule', url: '/admin/schedule' };

    it('keeps native navigation when no grants are supplied', () => {
        expect(withGrantedAdminTabs(SUPERVISOR_GROUPS)).toBe(SUPERVISOR_GROUPS);
        expect(withGrantedAdminTabs(SUPERVISOR_GROUPS, [])).toBe(SUPERVISOR_GROUPS);
    });

    it('exposes granted destinations without modifying native navigation', () => {
        const before = JSON.stringify(SUPERVISOR_GROUPS);
        const groups = withGrantedAdminTabs(SUPERVISOR_GROUPS, [schedule]);
        expect(groups.at(-1).children).toEqual([{
            key: 'supervisor-admin-schedule', label: 'Schedule',
            href: '/admin/schedule', match: ['/admin/schedule'],
        }]);
        expect(groups.at(-1).match).toEqual(['/admin/schedule']);
        expect(groups.slice(0, -1)).toEqual(SUPERVISOR_GROUPS);
        expect(JSON.stringify(SUPERVISOR_GROUPS)).toBe(before);
    });

    it('removes revoked destinations on the next server response', () => {
        expect(withGrantedAdminTabs(SUPERVISOR_GROUPS, [schedule])).toHaveLength(SUPERVISOR_GROUPS.length + 1);
        expect(withGrantedAdminTabs(SUPERVISOR_GROUPS, [])).toBe(SUPERVISOR_GROUPS);
    });

    it('does not render missing or non-admin destinations', () => {
        expect(withGrantedAdminTabs(SUPERVISOR_GROUPS, [
            { key: 'missing', url: null },
            { key: 'external', url: 'https://example.com/admin/schedule' },
            { key: 'native', url: '/supervisor/dashboard' },
        ])).toBe(SUPERVISOR_GROUPS);
    });
});
