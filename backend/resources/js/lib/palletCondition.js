import { __ } from './i18n';

/**
 * Physical pallet condition (damaged wrap, crushed cartons) - the warehouse's
 * record, separate from the quality status QC derives from its checks. One map
 * for every screen that shows it, so a damaged pallet reads the same everywhere.
 */
const CONDITION_META = {
    good: { tone: 'success', icon: 'circle-check', label: () => __('Condition good'), badge: 'bg-om-running-bg text-om-running' },
    damaged: { tone: 'critical', icon: 'triangle-alert', label: () => __('Condition damaged'), badge: 'bg-om-blocked-bg text-om-blocked' },
};

/** A row with no condition yet (written before the column existed) is good. */
const metaOf = (condition) => CONDITION_META[condition ?? 'good'] ?? CONDITION_META.good;

export function palletConditionLabel(condition) {
    return metaOf(condition).label();
}

/** Tailwind classes for the small pill used in list tables. */
export function palletConditionPill(condition) {
    return metaOf(condition).badge;
}

/** `<StatusBadge>` props. */
export function palletConditionBadge(condition) {
    const meta = metaOf(condition);
    return { tone: meta.tone, icon: meta.icon, label: meta.label() };
}

export const PALLET_CONDITION_LABELS = () => Object.values(CONDITION_META).map((m) => m.label());

const SOURCE_LABELS = { receipt: 'Warehouse receipt', move: 'Pallet move', manual: 'Recorded on the pallet' };

export function palletConditionSourceLabel(source) {
    return __(SOURCE_LABELS[source] ?? source ?? '');
}
