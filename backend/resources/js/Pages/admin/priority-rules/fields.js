import { __ } from '../../../lib/i18n';

// Values must match App\Enums\PriorityRuleSource / PriorityCondition.
export const PRIORITY_SOURCE_VALUES = ['customer.tier', 'customer.payment_score', 'customer.total_orders', 'wo.planned_qty', 'wo.due_date'];
export const PRIORITY_CONDITION_VALUES = ['equals', 'greater_than', 'less_than', 'between', 'is_true'];

// Built as functions so labels translate at render time (after locale load).
export function sourceOptions() {
    return [
        { value: 'customer.tier', label: __('Customer tier') },
        { value: 'customer.payment_score', label: __('Customer payment score') },
        { value: 'customer.total_orders', label: __('Customer total orders') },
        { value: 'wo.planned_qty', label: __('Planned quantity') },
        { value: 'wo.due_date', label: __('Hours until due') },
    ];
}

export function conditionOptions() {
    return [
        { value: 'equals', label: __('Equals') },
        { value: 'greater_than', label: __('Greater than') },
        { value: 'less_than', label: __('Less than') },
        { value: 'between', label: __('Between') },
        { value: 'is_true', label: __('Is true') },
    ];
}

export function sourceLabel(v) {
    return Object.fromEntries(sourceOptions().map((o) => [o.value, o.label]))[v] ?? v;
}

export function conditionLabel(v) {
    return Object.fromEntries(conditionOptions().map((o) => [o.value, o.label]))[v] ?? v;
}

/** Human-readable "Between 21 and 40" / "> 500" summary of a rule's condition. */
export function conditionSummary(rule) {
    const c = rule.condition_type;
    const v = rule.condition_value;
    if (c === 'is_true') return __('is true');
    if (c === 'between') return __('between :a and :b', { a: v, b: rule.condition_value_max });
    const op = { equals: '=', greater_than: '>', less_than: '<' }[c] ?? c;
    return `${op} ${v}`;
}

export function priorityRuleFields() {
    return [
        { name: 'name', label: __('Name'), required: true },
        { name: 'field_source', label: __('Source'), type: 'select', required: true, options: [{ value: '', label: __('— Select source —') }, ...sourceOptions()] },
        { name: 'condition_type', label: __('Condition'), type: 'select', required: true, options: [{ value: '', label: __('— Select condition —') }, ...conditionOptions()] },
        { name: 'condition_value', label: __('Value'), help: __('Tier: bronze/silver/gold/vip. Numbers: a threshold. "Hours until due": a number of hours (e.g. 24). Leave blank for "is true".') },
        { name: 'condition_value_max', label: __('Upper bound'), type: 'number', help: __('Only used by the "between" condition.') },
        { name: 'points', label: __('Points'), type: 'number', required: true, help: __('Added to the score when the rule matches. May be negative.') },
        { name: 'sort_order', label: __('Sort order'), type: 'number' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
