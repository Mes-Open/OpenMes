/**
 * Helpers for the form fields an installed module contributed.
 *
 * The server side is App\Extension\HookRegistry: a controller resolves the hook
 * point its form offers and hands the contributions over as the `hooks` prop.
 * With no module installed that prop is `{}` and everything here answers empty.
 *
 * Separate from ModuleFields.jsx so the rules can be tested — the test
 * environment is `node`, with no renderer.
 */

/**
 * The contributions to one hook point that are actually usable as fields.
 *
 * A contribution with no `name` has nowhere to bind and no error to show, so it
 * is dropped rather than rendered as an anonymous input.
 */
export function moduleFieldsFor(hooks, name) {
    return (hooks?.[name] ?? []).filter((field) => field?.name);
}

/**
 * Initial form state for those fields.
 *
 * Inertia's useForm only submits the keys it holds, so a field the operator
 * never touches would otherwise not be sent at all — and the module, seeing its
 * key absent from an edit, could not tell "unchanged" from "cleared". Seeding
 * the form with the current values is what makes an untouched field round-trip.
 */
export function moduleFieldInitial(hooks, name) {
    return Object.fromEntries(
        moduleFieldsFor(hooks, name).map((field) => [
            field.name,
            field.value ?? (field.type === 'checkbox' ? false : field.type === 'checkbox-group' ? [] : ''),
        ]),
    );
}

/**
 * What to show in a field: the form's own state wins.
 *
 * `value` is what the record held when the page was rendered, and stops being
 * the truth the moment somebody types.
 */
export function moduleFieldValue(field, values) {
    return field.name in (values ?? {}) ? values[field.name] : field.value;
}

/**
 * A module's contributed fields as ResourceForm fields. A module describes its
 * field as data — core draws it — so only the types ResourceForm knows pass;
 * anything else is drawn as a text input.
 */
export function moduleFormFields(hooks, name) {
    return moduleFieldsFor(hooks, name).map((f) => ({
        name: f.name,
        label: f.label,
        help: f.help,
        required: !!f.required,
        type: ['select', 'checkbox', 'checkbox-group'].includes(f.type) ? f.type : 'text',
        options: (f.options ?? []).map((o) => ({ value: String(o.value), label: o.label })),
    }));
}

/**
 * Starting values for those fields on a ResourceForm. A field that names a
 * `column` of the record reads the record's own value, so a list page's drawer
 * (whose rows come from a synced collection, not from a per-record render)
 * still opens with the stored choice.
 *
 * Pass `{ perRecord: false }` when the hooks were rendered without the record
 * — a list page renders them once for every row. A field whose value then
 * cannot be read off the record is left out of the starting values: unless
 * someone changes it, it is not submitted, and the module keeps what it stored
 * instead of receiving a blank it would take for "cleared".
 */
export function moduleFormInitial(record, hooks, name, { perRecord = true } = {}) {
    const entries = [];
    for (const f of moduleFieldsFor(hooks, name)) {
        const empty = f.type === 'checkbox' ? false : f.type === 'checkbox-group' ? [] : '';
        if (record && f.column && f.column in record) {
            entries.push([f.name, fromStored(f, record[f.column], empty)]);
        } else if (!record || perRecord) {
            // With no record, `value` is the module's default for a new one.
            const v = f.value;
            entries.push([f.name, v == null ? empty : f.type === 'select' ? String(v) : v]);
        }
    }

    return Object.fromEntries(entries);
}

/** A stored column value as the input for the field's type shows it. */
function fromStored(field, value, empty) {
    if (value == null) return empty;
    if (field.type === 'checkbox') return !!value;
    // Option values are strings, and a checkbox group matches them strictly.
    if (field.type === 'checkbox-group') return Array.isArray(value) ? value.map(String) : empty;

    return String(value);
}
