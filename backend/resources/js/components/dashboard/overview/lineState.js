import { __ } from '../../../lib/i18n';
import { SEGMENT_COLOR } from '../../shift-monitor/tokens';

/**
 * The four line states every plant-overview section speaks
 * (App\Services\Dashboard\PlantOverviewService), plus `none` for minutes
 * nothing was reported for. CSS custom properties, so light and dark resolve
 * from the theme.
 */
export const LINE_STATE_COLOR = {
    running: 'var(--om-running)',
    idle: 'var(--om-planned)',
    blocked: 'var(--om-downtime)',
    down: 'var(--om-blocked)',
    none: SEGMENT_COLOR.none,
};

/** Legend order, as in the mockup. */
export const LINE_STATES = ['running', 'idle', 'blocked', 'down'];

export function lineStateLabel(state) {
    switch (state) {
        case 'running': return __('Running');
        case 'idle': return __('Idle');
        case 'blocked': return __('Blocked');
        case 'down': return __('Down');
        default: return __('No data');
    }
}
