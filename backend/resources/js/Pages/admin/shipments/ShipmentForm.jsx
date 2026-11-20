import { useRef } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button, InlineAlert } from '@openmes/ui';
import AppDateTimePicker from '../../../components/AppDateTimePicker';
import { __ } from '../../../lib/i18n';

const LABEL = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT = 'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent';
// Mirrors Shipment::IDENTIFIER_REGEX — UX only, the Form Request decides.
const IDENTIFIER = /^[A-Za-z0-9][A-Za-z0-9\-_/.]*$/;

/** Lines of the paste that would be rejected, for the hint under the textarea. */
function badLines(text) {
    return text.split(/\r\n|\r|\n/)
        .map((line, i) => [i + 1, line.split(/[,;\t]/).map((t) => t.trim().replace(/^["']|["']$/g, '')).filter(Boolean)])
        .filter(([, tokens]) => tokens.length > 0)
        .filter(([, tokens]) => {
            const id = tokens.find((t) => !['pallet', 'carton', 'p', 'c', 'box'].includes(t.toLowerCase()));
            return id !== undefined && !['type', 'unit_type', 'identifier', 'label', 'number', 'code'].includes(id.toLowerCase()) && (!IDENTIFIER.test(id) || id.length > 64);
        })
        .map(([n]) => n);
}

function lineCount(text) {
    return text.split(/\r\n|\r|\n/).filter((l) => l.trim() !== '').length;
}

/**
 * Create / edit a shipment (#400). Not a ResourceForm: the expected list is a
 * paste box that a CSV file can fill, which the generic form has no field for.
 * The server parses the text (ShipmentListParser), so a paste and an upload go
 * through the same rules.
 */
export default function ShipmentForm({ action, method = 'post', initial, submitLabel, cancelHref }) {
    const form = useForm({
        shipment_no: '',
        erp_reference: '',
        carrier: '',
        vehicle_no: '',
        destination: '',
        planned_at: '',
        notes: '',
        items_text: '',
        default_unit_type: 'auto',
        ...initial,
    });
    const { data, setData, errors, processing } = form;
    const fileRef = useRef(null);

    const itemErrors = Object.entries(errors).filter(([k]) => k === 'items' || k.startsWith('items.') || k === 'items_text');
    const invalid = badLines(data.items_text);

    const onFile = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            const text = String(reader.result ?? '');
            setData('items_text', data.items_text.trim() === '' ? text : `${data.items_text.trim()}\n${text}`);
        };
        reader.readAsText(file);
        e.target.value = '';
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, planned_at: d.planned_at || null }));
        method === 'put' ? form.put(action) : form.post(action);
    };

    const field = (name, label, props = {}) => (
        <div>
            <label htmlFor={name} className={LABEL}>{label}{props.required && <span className="text-om-blocked"> *</span>}</label>
            <input id={name} name={name} value={data[name] ?? ''} onChange={(e) => setData(name, e.target.value)} className={INPUT} {...props} />
            {errors[name] && <p className="mt-1 text-xs text-om-blocked">{errors[name]}</p>}
        </div>
    );

    return (
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 space-y-5 max-w-3xl">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {field('shipment_no', __('Shipment no.'), { required: true, maxLength: 64, placeholder: 'LOAD-2026-0142' })}
                {field('erp_reference', __('ERP reference'), { maxLength: 100 })}
                {field('carrier', __('Carrier'), { maxLength: 255 })}
                {field('vehicle_no', __('Vehicle'), { maxLength: 32, placeholder: 'WX 12345' })}
                {field('destination', __('Destination'), { maxLength: 255 })}
                <div>
                    <label className={LABEL}>{__('Planned')}</label>
                    <AppDateTimePicker name="planned_at" value={data.planned_at} onChange={(v) => setData('planned_at', v)} />
                    {errors.planned_at && <p className="mt-1 text-xs text-om-blocked">{errors.planned_at}</p>}
                </div>
            </div>

            <div>
                <label htmlFor="notes" className={LABEL}>{__('Notes')}</label>
                <textarea id="notes" name="notes" rows={2} maxLength={2000} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} className={INPUT} />
            </div>

            <div>
                <div className="flex items-end justify-between gap-3 mb-[7px]">
                    <label htmlFor="items_text" className={`${LABEL} mb-0`}>{__('Expected pallets and cartons')}<span className="text-om-blocked"> *</span></label>
                    <div className="flex items-center gap-2">
                        <select
                            name="default_unit_type"
                            aria-label={__('Type of lines without one')}
                            value={data.default_unit_type}
                            onChange={(e) => setData('default_unit_type', e.target.value)}
                            className="bg-om-bg border border-om-line rounded-om-sm px-2 py-1.5 text-[12px] text-om-ink"
                        >
                            <option value="auto">{__('Detect type automatically')}</option>
                            <option value="pallet">{__('All pallets')}</option>
                            <option value="carton">{__('All cartons')}</option>
                        </select>
                        <input ref={fileRef} type="file" accept=".csv,.txt,text/csv,text/plain" className="hidden" onChange={onFile} data-testid="csv-input" />
                        <Button size="sm" variant="ghost" onClick={() => fileRef.current?.click()}>{__('Upload CSV')}</Button>
                    </div>
                </div>
                <textarea
                    id="items_text"
                    name="items_text"
                    rows={10}
                    value={data.items_text}
                    onChange={(e) => setData('items_text', e.target.value)}
                    placeholder={'PAL-000012\nPAL-000013\ncarton,CTN-000301'}
                    className={`${INPUT} font-mono`}
                />
                <p className="mt-1 text-[11.5px] text-om-muted">
                    {__('One label per line. Optionally add the type: "pallet,PAL-000012" or "CTN-000301;carton".')}
                    {' '}{__(':count line(s).', { count: lineCount(data.items_text) })}
                </p>
                {invalid.length > 0 && (
                    <InlineAlert severity="warning" title={__('Some lines are not valid label numbers')} className="mt-2">
                        {__('Lines: :lines. Use letters, digits and - _ / . only.', { lines: invalid.slice(0, 10).join(', ') })}
                    </InlineAlert>
                )}
                {itemErrors.length > 0 && (
                    <InlineAlert severity="error" title={__('The expected list was not accepted')} className="mt-2">
                        <ul className="list-disc list-inside">
                            {itemErrors.slice(0, 10).map(([k, msg]) => <li key={k}>{msg}</li>)}
                        </ul>
                    </InlineAlert>
                )}
            </div>

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" loading={processing}>{submitLabel}</Button>
                <Link href={cancelHref} className="text-[13px] text-om-muted hover:text-om-ink">{__('Cancel')}</Link>
            </div>
        </form>
    );
}
