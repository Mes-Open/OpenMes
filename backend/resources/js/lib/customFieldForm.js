/**
 * Helpers for wiring custom fields into any Inertia useForm()-based form
 * (ResourceForm and bespoke forms alike), plus `submitForm` — the app's shared
 * submit, which every one of those forms goes through whether it has custom
 * fields or not.
 *
 * Custom-field state on the form's data:
 *   custom_fields           — { key: scalarValue }
 *   custom_field_files      — { key: File }   (new uploads, staged)
 *   custom_field_files_remove — [key]         (existing files to clear)
 */

import { jsonColumn } from './syncedRow';

/** Initial custom-field keys to spread into a useForm() initial object. */
export function customFieldInitial(existing = {}) {
    return {
        custom_fields: existing ?? {},
        custom_field_files: {},
        custom_field_files_remove: [],
    };
}

/**
 * Custom-field values as an object, whichever way the record reached us — see
 * `lib/syncedRow.js` for why the two ways differ.
 */
export function customFieldValues(raw) {
    return jsonColumn(raw, {});
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
 * Submit a useForm() instance, spoofing the method over POST when the request
 * has to go out as multipart (FormData can't be sent via PUT/PATCH).
 *
 * That's the case for a staged custom-field file or a plain top-level File
 * value (ResourceForm's `image` field type). Files nested anywhere else are
 * not detected — put them at the top level or under `custom_field_files`.
 */
export function submitForm(form, method, action, options = {}) {
    const staged = form.data.custom_field_files ?? {};
    // Staged custom-field files, plus any plain top-level File field (e.g. the
    // product photo) — both force the request to multipart.
    const hasFiles = Object.values(staged).some((f) => f instanceof File)
        || Object.values(form.data).some((v) => v instanceof File);
    if (hasFiles && method.toLowerCase() !== 'post') {
        form.transform((d) => ({ ...d, _method: method }));
        form.post(action, options);
    } else {
        form.transform((d) => d);
        form.submit(method, action, options);
    }
}
