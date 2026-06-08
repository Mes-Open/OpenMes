import { Fragment } from 'react';
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
 *   fields      — [{ name, label, type?, required?, placeholder?, help?, options? }]
 *                 type: 'text' (default) | 'textarea' | 'number' | 'checkbox' | 'select'
 *                 help: gray hint text rendered under the field
 *                 options (select): [{ value, label }]
 *   initial     — initial form values keyed by field name
 *   submitLabel — button text
 *   cancelHref  — cancel link
 *   breadcrumbs — optional [{ label, href? }] rendered above the form
 *   backHref    — optional "‹ Back" link target rendered above the form
 *   backLabel   — text for the back link (default 'Back')
 *   title       — optional page heading rendered between the back link and form
 */
export default function ResourceForm({
    action,
    method = 'post',
    fields,
    initial,
    submitLabel = 'Save',
    cancelHref,
    breadcrumbs,
    backHref,
    backLabel = 'Back',
    title,
}) {
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <>
            {breadcrumbs?.length > 0 && (
                <nav className="text-sm text-gray-500 mb-4 flex items-center gap-1">
                    {breadcrumbs.map((b, i) => (
                        <Fragment key={i}>
                            {i > 0 && <span>/</span>}
                            {b.href ? (
                                <Link href={b.href} className="hover:underline">{b.label}</Link>
                            ) : (
                                <span className="text-gray-800">{b.label}</span>
                            )}
                        </Fragment>
                    ))}
                </nav>
            )}

            {backHref && (
                <Link href={backHref} className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    {backLabel}
                </Link>
            )}

            {title && <h1 className="text-3xl font-bold text-gray-800 mb-6">{title}</h1>}

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
        </>
    );
}

function Field({ field, value, error, setData }) {
    const { name, label, type = 'text', required, placeholder, help, options } = field;
    const set = (v) => setData(name, v);

    if (type === 'checkbox') {
        return (
            <div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={!!value} onChange={(e) => set(e.target.checked)} />
                    {label}
                </label>
                {help && <p className="text-sm text-gray-500 mt-1">{help}</p>}
            </div>
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

            {help && <p className="text-sm text-gray-500 mt-1">{help}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
