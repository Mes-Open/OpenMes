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
            field.value ?? (field.type === 'checkbox' ? false : ''),
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
