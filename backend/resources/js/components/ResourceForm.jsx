import { Fragment, useEffect } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import CustomFields from './CustomFields';
import { customFieldProps, submitForm } from '../lib/customFieldForm';

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
 *   customFields — optional admin-defined custom-field definitions (clientConfig);
 *                  rendered after the static fields, bound to data.custom_fields
 */

// Geist White input + label idiom (light-only v1 — dark: variants removed).
const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

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
    customFields,
}) {
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    // After a failed submit, jump to (and focus) the first invalid field so the
    // user isn't left guessing what to fix — the main cause of form abandonment.
    useEffect(() => {
        const keys = Object.keys(errors);
        if (keys.length === 0) return;
        const el = document.querySelector(`[name="${keys[0]}"]`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.focus({ preventScroll: true });
        }
    }, [errors]);

    // Custom-field definitions: explicit prop wins, else fall back to the page's
    // `customFields` prop so any ResourceForm-based page whose controller passes
    // it renders custom fields without per-page wiring.
    const pageCustomFields = usePage().props.customFields;
    const customFieldDefs = customFields ?? pageCustomFields ?? [];

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, method, action);
    };

    return (
        <>
            {breadcrumbs?.length > 0 && (
                <nav className="text-[13px] text-om-muted mb-4 flex items-center gap-1">
                    {breadcrumbs.map((b, i) => (
                        <Fragment key={i}>
                            {i > 0 && <span>/</span>}
                            {b.href ? (
                                <Link href={b.href} className="hover:underline hover:text-om-ink">{b.label}</Link>
                            ) : (
                                <span className="text-om-ink">{b.label}</span>
                            )}
                        </Fragment>
                    ))}
                </nav>
            )}

            {backHref && (
                <Link href={backHref} className="text-[13px] text-om-muted hover:text-om-ink flex items-center gap-2 mb-4 transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    {backLabel}
                </Link>
            )}

            {title && <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6">{title}</h1>}

            <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 max-w-2xl space-y-5">
                {Object.keys(errors).length > 0 && (
                    <div className="bg-om-blocked-bg border border-om-blocked/20 rounded-om-sm p-3">
                        <p className="text-[12.5px] font-semibold text-om-blocked">Please fix the following:</p>
                        <ul className="mt-1 text-[11.5px] text-om-blocked list-disc list-inside">
                            {Object.entries(errors).map(([field, msg]) => (
                                <li key={field}>{(fields.find((f) => f.name === field)?.label ?? field)}: {msg}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {fields.map((f) => (
                    <Field key={f.name} field={f} value={data[f.name]} error={errors[f.name]} setData={setData} />
                ))}

                {customFieldDefs.length > 0 && (
                    <CustomFields {...customFieldProps(form, customFieldDefs)} />
                )}

                <div className="flex items-center gap-3 pt-2">
                    <Button type="submit" variant="primary" loading={processing}>
                        {processing ? 'Saving…' : submitLabel}
                    </Button>
                    {cancelHref && (
                        <Link
                            href={cancelHref}
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
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
                <label className="flex items-center gap-2.5 text-[13px] text-om-ink">
                    {/* 18px accent checkbox idiom — native input kept for name/focus/e2e semantics */}
                    <input
                        type="checkbox"
                        name={name}
                        checked={!!value}
                        onChange={(e) => set(e.target.checked)}
                        className="size-[18px] accent-om-accent"
                    />
                    {label}
                </label>
                {help && <p className="text-[12px] text-om-muted mt-1">{help}</p>}
            </div>
        );
    }

    // A row of toggleable options whose value is an array of the selected option
    // values (e.g. weekdays). Keeps value types as given in options.
    if (type === 'checkbox-group') {
        const selected = Array.isArray(value) ? value : [];
        const toggle = (v) =>
            set(selected.includes(v) ? selected.filter((x) => x !== v) : [...selected, v]);

        return (
            <div>
                <label className={LABEL_CLASS}>
                    {label} {required && <span className="text-om-accent">*</span>}
                </label>
                <div name={name} className="flex flex-wrap gap-2">
                    {(options ?? []).map((o) => {
                        const active = selected.includes(o.value);
                        return (
                            <button
                                type="button"
                                key={String(o.value)}
                                onClick={() => toggle(o.value)}
                                className={`px-3 py-1.5 rounded-om-sm text-[13px] border transition-colors ${
                                    active
                                        ? 'bg-om-ink text-white border-om-ink'
                                        : 'bg-om-card text-om-ink border-om-line hover:bg-om-chip'
                                }`}
                            >
                                {o.label}
                            </button>
                        );
                    })}
                </div>
                {help && <p className="text-[12px] text-om-muted mt-1">{help}</p>}
                {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
            </div>
        );
    }

    return (
        <div>
            <label className={LABEL_CLASS}>
                {label} {required && <span className="text-om-accent">*</span>}
            </label>

            {type === 'textarea' ? (
                <textarea
                    name={name}
                    value={value ?? ''}
                    onChange={(e) => set(e.target.value)}
                    rows={3}
                    placeholder={placeholder}
                    className={INPUT_CLASS}
                />
            ) : type === 'select' ? (
                /* Native <select> kept for now — same input styling. */
                <select name={name} value={value ?? ''} onChange={(e) => set(e.target.value)} className={INPUT_CLASS}>
                    {(options ?? []).map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
            ) : type === 'color' ? (
                <input
                    type="color"
                    name={name}
                    value={value || '#3b82f6'}
                    onChange={(e) => set(e.target.value)}
                    className="h-9 w-16 rounded-om-sm border border-om-line bg-om-bg p-0.5"
                />
            ) : (
                <input
                    type={{ number: 'number', date: 'date', time: 'time', datetime: 'datetime-local' }[type] ?? 'text'}
                    name={name}
                    value={value ?? ''}
                    onChange={(e) => set(e.target.value)}
                    placeholder={placeholder}
                    className={INPUT_CLASS}
                />
            )}

            {help && <p className="text-[12px] text-om-muted mt-1">{help}</p>}
            {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
        </div>
    );
}
