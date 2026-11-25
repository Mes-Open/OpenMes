import { __ } from '../../../lib/i18n';
import { moduleFieldsFor } from '../../../lib/moduleFields';

/** Where an installed module hangs its own fields on this form (HookRegistry). */
export const LINE_FIELDS_HOOK = 'display.admin.lines.form.fields';

/**
 * A module's contributed fields as ResourceForm fields. A module describes its
 * field as data — core draws it — so only the types ResourceForm knows pass.
 */
function moduleLineFields(hooks) {
    return moduleFieldsFor(hooks, LINE_FIELDS_HOOK).map((f) => ({
        name: f.name,
        label: f.label,
        help: f.help,
        required: !!f.required,
        type: ['select', 'checkbox', 'checkbox-group'].includes(f.type) ? f.type : 'text',
        options: (f.options ?? []).map((o) => ({ value: String(o.value), label: o.label })),
    }));
}

export function lineFields(warehouses = [], hooks = {}) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'warehouse_id', label: __('Stock location'), type: 'select',
            help: __('Consumption booked on this line is deducted from this location.'),
            options: [
                { value: '', label: __('— None —') },
                ...warehouses.map((w) => ({ value: String(w.id), label: w.name })),
            ],
        },
        {
            name: 'planning_efficiency_pct', label: __('Planning efficiency (%)'), type: 'number', min: 1, max: 100, step: 0.5,
            help: __('Share of shift capacity (after scheduled maintenance) that planning may load. 100 = no derating. Not the same as OEE.'),
        },
        ...moduleLineFields(hooks),
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/**
 * Starting values for the module fields. A field that names a `column` of the
 * line row reads the record's own value, so the list page's drawer (whose rows
 * come from the synced collection, not from a per-line render) still opens
 * with the stored choice.
 */
function moduleLineInitial(record, hooks) {
    return Object.fromEntries(moduleFieldsFor(hooks, LINE_FIELDS_HOOK).map((f) => {
        const empty = f.type === 'checkbox' ? false : f.type === 'checkbox-group' ? [] : '';
        if (record && f.column && f.column in record) {
            const v = record[f.column];
            return [f.name, v == null ? empty : f.type === 'checkbox' ? !!v : String(v)];
        }
        const v = record ? f.value : undefined;
        return [f.name, v == null ? empty : f.type === 'select' ? String(v) : v];
    }));
}

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function lineInitial(record, hooks = {}) {
    const modules = moduleLineInitial(record, hooks);
    if (!record) {
        return { code: '', name: '', warehouse_id: '', planning_efficiency_pct: '100', description: '', is_active: true, ...modules };
    }

    return {
        ...modules,
        code: record.code ?? '',
        name: record.name ?? '',
        warehouse_id: record.warehouse_id != null ? String(record.warehouse_id) : '',
        planning_efficiency_pct: record.planning_efficiency_pct != null ? String(Number(record.planning_efficiency_pct)) : '100',
        description: record.description ?? '',
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
