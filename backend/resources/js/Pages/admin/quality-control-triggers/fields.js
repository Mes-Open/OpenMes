import { __ } from '../../../lib/i18n';

export const TRIGGER_TYPE_LABELS = {
    in_production: __('In production'),
    every_n_units: __('Every N units'),
    every_n_minutes: __('Every N minutes'),
    after_downtime: __('After downtime'),
    after_setup: __('After setup'),
    roaming: __('Roaming'),
};

const TRIGGER_TYPE_OPTIONS = Object.entries(TRIGGER_TYPE_LABELS).map(([value, label]) => ({ value, label }));

const noneOption = (label) => ({ value: '', label });

/**
 * Build the ResourceForm field set. Option lists (templates, lines, …) come
 * from the controller as Inertia props, so the field config is built per-render.
 */
export function triggerFields({ templates = [], lines = [], workstations = [], productTypes = [] }) {
    const toOptions = (rows) => rows.map((r) => ({ value: String(r.id), label: r.name }));

    return [
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'trigger_type',
            label: __('Trigger type'),
            type: 'select',
            required: true,
            options: TRIGGER_TYPE_OPTIONS,
        },
        {
            name: 'quality_check_template_id',
            label: __('Quality check template'),
            type: 'select',
            options: [noneOption(__('None')), ...toOptions(templates)],
            help: __('The control performed when this trigger fires.'),
        },
        {
            name: 'threshold_n',
            label: __('Threshold (N)'),
            type: 'number',
            help: __('Units (Every N units) or minutes (Every N minutes). Required for frequency triggers.'),
        },
        {
            name: 'downtime_min_minutes',
            label: __('Minimum downtime (min)'),
            type: 'number',
            help: __('Only fire after a downtime at least this long. Used by after-downtime / after-setup.'),
        },
        {
            name: 'line_id',
            label: __('Line scope'),
            type: 'select',
            options: [noneOption(__('Any line')), ...toOptions(lines)],
        },
        {
            name: 'workstation_id',
            label: __('Workstation scope'),
            type: 'select',
            options: [noneOption(__('Any workstation')), ...toOptions(workstations)],
        },
        {
            name: 'product_type_id',
            label: __('Product type scope'),
            type: 'select',
            options: [noneOption(__('Any product')), ...toOptions(productTypes)],
        },
        { name: 'is_blocking', label: __('Blocking'), type: 'checkbox', help: __('Hard-gate production until the control is done.') },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

export const TRIGGER_INITIAL = {
    name: '',
    trigger_type: 'in_production',
    quality_check_template_id: '',
    threshold_n: '',
    downtime_min_minutes: '',
    line_id: '',
    workstation_id: '',
    product_type_id: '',
    is_blocking: false,
    is_active: true,
};
