/**
 * Helpers for wiring custom fields into any Inertia useForm()-based form
 * (ResourceForm and bespoke forms alike).
 *
 * Custom-field state on the form's data:
 *   custom_fields           — { key: scalarValue }
 *   custom_field_files      — { key: File }   (new uploads, staged)
 *   custom_field_files_remove — [key]         (existing files to clear)
 */

/** Initial custom-field keys to spread into a useForm() initial object. */
export function customFieldInitial(existing = {}) {
    return {
        custom_fields: existing ?? {},
        custom_field_files: {},
        custom_field_files_remove: [],
    };
}

/** Props for the <CustomFields> component, derived from a useForm() instance. */
export function customFieldProps(form, definitions) {
    const { data, setData, errors } = form;
    return {
        definitions,
        values: data.custom_fields ?? {},
        onChange: (v) => setData('custom_fields', v),
        files: data.custom_field_files ?? {},
        onFileChange: (key, file) =>
            setData('custom_field_files', { ...(data.custom_field_files ?? {}), [key]: file }),
        removed: data.custom_field_files_remove ?? [],
        onRemovedChange: (arr) => setData('custom_field_files_remove', arr),
        errors,
    };
}

/**
 * Submit a useForm() instance, spoofing the method over POST when a custom-field
 * file is staged (FormData can't be sent via PUT/PATCH).
 */
export function submitForm(form, method, action, options = {}) {
    const staged = form.data.custom_field_files ?? {};
    const hasFiles = Object.values(staged).some((f) => f instanceof File);
    if (hasFiles && method.toLowerCase() !== 'post') {
        form.transform((d) => ({ ...d, _method: method }));
        form.post(action, options);
    } else {
        form.transform((d) => d);
        form.submit(method, action, options);
    }
}
