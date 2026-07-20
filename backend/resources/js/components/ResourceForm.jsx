import { Fragment, useEffect } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, DatePicker, Dropdown } from '@openmes/ui';
import CustomFields from './CustomFields';
import { customFieldProps, submitForm } from '../lib/customFieldForm';
import { __ } from '../lib/i18n';

/**
 * Config-driven create/edit form — the non-optimistic write-through pattern.
 *
 * Submits via Inertia useForm to a Laravel endpoint that validates and
 * redirects to the list; the committed row then shows up there via the synced
 * collection. Validation errors render from form.errors; the button reflects
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
    onCancel,
    onSuccess,
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
        submitForm(form, method, action, onSuccess ? { onSuccess } : {});
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
                    {__(backLabel)}
                </Link>
            )}

            {title && <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6">{title}</h1>}

            <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 max-w-2xl space-y-5">
                {Object.keys(errors).length > 0 && (
                    <div className="bg-om-blocked-bg border border-om-blocked/20 rounded-om-sm p-3">
                        <p className="text-[12.5px] font-semibold text-om-blocked">{__('Please fix the following:')}</p>
                        <ul className="mt-1 text-[11.5px] text-om-blocked list-disc list-inside">
                            {Object.entries(errors).map(([field, msg]) => (
                                <li key={field}>{(fields.find((f) => f.name === field)?.label ?? field)}: {msg}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {fields.map((f) => (
                    <Field key={f.name} field={f} value={data[f.name]} error={errors[f.name]} setData={setData} data={data} />
                ))}

                {customFieldDefs.length > 0 && (
                    <CustomFields {...customFieldProps(form, customFieldDefs)} />
                )}

                <div className="flex items-center gap-3 pt-2">
                    <Button type="submit" variant="primary" loading={processing}>
                        {processing ? __('Saving…') : submitLabel}
                    </Button>
                    {cancelHref && (
                        <Link
                            href={cancelHref}
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            {__('Cancel')}
                        </Link>
                    )}
                    {!cancelHref && onCancel && (
                        <button
                            type="button"
                            onClick={onCancel}
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            Cancel
                        </button>
                    )}
                </div>
            </form>
        </>
    );
}

function Field({ field, value, error, setData, data }) {
    const { name, label, type = 'text', required, placeholder, help, options } = field;
    const set = (v) => setData(name, v);

    if (type === 'checkbox') {
        return (
            <div>
                <Checkbox checked={!!value} onChange={(next) => set(next)} label={__(label)} />
                {help && <p className="text-[12px] text-om-muted mt-1">{__(help)}</p>}
            </div>
        );
    }

    if (type === 'checkbox-group') {
        return <CheckboxGroupField field={field} value={value} error={error} setData={setData} data={data} />;
    }

    return (
        <div>
            <label className={LABEL_CLASS}>
                {__(label)} {required && <span className="text-om-accent">*</span>}
            </label>

            {type === 'textarea' ? (
                <textarea
                    name={name}
                    value={value ?? ''}
                    onChange={(e) => set(e.target.value)}
                    rows={3}
                    placeholder={placeholder ? __(placeholder) : undefined}
                    className={INPUT_CLASS}
                />
            ) : type === 'select' ? (
                <Dropdown
                    className="w-full"
                    options={(options ?? []).map((o) => ({ value: String(o.value), label: __(o.label) }))}
                    value={value == null ? '' : String(value)}
                    onChange={(v) => set(v)}
                    placeholder={placeholder ? __(placeholder) : `${__(label)}…`}
                />
            ) : type === 'date' ? (
                <DatePicker
                    className="w-full"
                    value={value || null}
                    onChange={(iso) => set(iso ?? '')}
                    placeholder={placeholder ? __(placeholder) : __('Select date')}
                />
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
                    placeholder={placeholder ? __(placeholder) : undefined}
                    className={INPUT_CLASS}
                />
            )}

            {help && <p className="text-[12px] text-om-muted mt-1">{__(help)}</p>}
            {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
        </div>
    );
}

/**
 * A row of toggleable options whose value is an array of the selected option
 * values (e.g. weekdays, BOMs). Keeps value types as given in options.
 *
 * When `field.filterByField` is set, options are scoped to the current value of
 * that other field: each option carries a `group`, and only options whose
 * `group` matches `data[filterByField]` are shown. A selection that stops being
 * visible (because the driving field changed) is pruned so it isn't submitted.
 */
function CheckboxGroupField({ field, value, error, setData, data }) {
    const { name, label, required, help, options, filterByField } = field;
    const selected = Array.isArray(value) ? value : [];

    const filterVal = filterByField ? data?.[filterByField] : undefined;
    const visibleOptions = filterByField
        ? (options ?? []).filter((o) => String(o.group) === String(filterVal))
        : (options ?? []);

    useEffect(() => {
        if (!filterByField) return;
        const allowed = new Set(visibleOptions.map((o) => o.value));
        const pruned = selected.filter((v) => allowed.has(v));
        if (pruned.length !== selected.length) setData(name, pruned);
        // Prune only when the driving field changes; selected/options are derived from it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filterVal]);

    const toggle = (v) =>
        setData(name, selected.includes(v) ? selected.filter((x) => x !== v) : [...selected, v]);

    const noDrivingValue = filterVal == null || filterVal === '';

    return (
        <div>
            <label id={`${name}-label`} className={LABEL_CLASS}>
                {__(label)} {required && <span className="text-om-accent">*</span>}
            </label>
            <div name={name} role="group" aria-labelledby={`${name}-label`} className="flex flex-wrap gap-2">
                {visibleOptions.map((o) => {
                    const active = selected.includes(o.value);
                    return (
                        <button
                            type="button"
                            key={String(o.value)}
                            onClick={() => toggle(o.value)}
                            aria-pressed={active}
                            className={`px-3 py-1.5 rounded-om-sm text-[13px] border transition-colors ${
                                active
                                    ? 'bg-om-ink text-om-on-ink border-om-ink'
                                    : 'bg-om-card text-om-ink border-om-line hover:bg-om-chip'
                            }`}
                        >
                            {__(o.label)}
                        </button>
                    );
                })}
                {filterByField && visibleOptions.length === 0 && (
                    <p className="text-[12px] text-om-muted">
                        {noDrivingValue
                            ? __('Select a product type first.')
                            : __('No BOMs are available for the selected product type.')}
                    </p>
                )}
            </div>
            {help && <p className="text-[12px] text-om-muted mt-1">{__(help)}</p>}
            {error && <p className="mt-1 text-[11.5px] text-om-blocked">{error}</p>}
        </div>
    );
}
