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

/** A stored id as a <select> value: the options carry strings, the record numbers. */
const str = (v) => (v === null || v === undefined ? '' : String(v));

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function triggerInitial(record) {
    if (!record) {
        return { ...TRIGGER_INITIAL };
    }

    return {
        name: record.name ?? '',
        trigger_type: record.trigger_type,
        quality_check_template_id: str(record.quality_check_template_id),
        threshold_n: str(record.threshold_n),
        downtime_min_minutes: str(record.downtime_min_minutes),
        line_id: str(record.line_id),
        workstation_id: str(record.workstation_id),
        product_type_id: str(record.product_type_id),
        is_blocking: !!record.is_blocking,
        is_active: !!record.is_active,
    };
}
