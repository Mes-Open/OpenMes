import { __ } from './i18n';

/**
 * Serial-unit status → `<StatusBadge>` props. One map for every screen that
 * shows a unit (the SN label station, the traceability console), so a scrapped
 * unit is the same red chip with the same word everywhere.
 */
const SERIAL_STATUS_META = {
    in_production: { tone: 'active', icon: 'play', label: () => __('In production') },
    completed: { tone: 'success', icon: 'circle-check', label: () => __('Unit completed') },
    blocked: { tone: 'critical', icon: 'ban', label: () => __('Blocked') },
    scrapped: { tone: 'critical', icon: 'x', label: () => __('Scrapped') },
    shipped: { tone: 'success', icon: 'truck', label: () => __('Shipped') },
};

export function serialStatusBadge(status) {
    const meta = SERIAL_STATUS_META[status];
    return meta ? { tone: meta.tone, icon: meta.icon, label: meta.label() } : { tone: 'neutral', label: __(status ?? '') };
}

export function serialStatusLabel(status) {
    return serialStatusBadge(status).label;
}
