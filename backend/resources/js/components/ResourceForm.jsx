import { Link, useForm } from '@inertiajs/react';

/**
 * Config-driven create/edit form — the non-optimistic write-through pattern.
 *
 * Submits via Inertia useForm to a Laravel endpoint that validates and
 * redirects to the list; the committed row then shows up there via the Electric
 * shape. Validation errors render from form.errors; the button reflects
 * form.processing. No optimistic state.
 *
 * Props:
 *   action      — endpoint URL
 *   method      — 'post' (create) | 'put' (edit)
 *   fields      — [{ name, label, type?, required?, placeholder?, options? }]
 *                 type: 'text' (default) | 'textarea' | 'number' | 'checkbox' | 'select'
 *                 options (select): [{ value, label }]
 *   initial     — initial form values keyed by field name
 *   submitLabel — button text
 *   cancelHref  — back link
 */
export default function ResourceForm({ action, method = 'post', fields, initial, submitLabel = 'Save', cancelHref }) {
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <form onSubmit={submit} className="bg-white rounded-lg shadow-sm p-6 max-w-2xl space-y-5">
            {fields.map((f) => (
                <Field key={f.name} field={f} value={data[f.name]} error={errors[f.name]} setData={setData} />
            ))}

            <div className="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
                {cancelHref && (
                    <Link href={cancelHref} className="text-gray-500 hover:text-gray-800 text-sm">
                        Cancel
                    </Link>
                )}
            </div>
        </form>
    );
}

function Field({ field, value, error, setData }) {
    const { name, label, type = 'text', required, placeholder, options } = field;
    const set = (v) => setData(name, v);

    if (type === 'checkbox') {
        return (
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" checked={!!value} onChange={(e) => set(e.target.checked)} />
                {label}
            </label>
        );
    }

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>

            {type === 'textarea' ? (
                <textarea
                    value={value ?? ''}
                    onChange={(e) => set(e.target.value)}
                    rows={3}
                    placeholder={placeholder}
                    className="form-input w-full"
                />
            ) : type === 'select' ? (
                <select value={value ?? ''} onChange={(e) => set(e.target.value)} className="form-input w-full">
                    {(options ?? []).map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
            ) : type === 'color' ? (
                <input
                    type="color"
                    value={value || '#3b82f6'}
                    onChange={(e) => set(e.target.value)}
                    className="h-9 w-16 rounded border border-gray-300 p-0.5"
                />
            ) : (
                <input
                    type={{ number: 'number', date: 'date', time: 'time', datetime: 'datetime-local' }[type] ?? 'text'}
                    value={value ?? ''}
                    onChange={(e) => set(e.target.value)}
                    placeholder={placeholder}
                    className="form-input w-full"
                />
            )}

            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
