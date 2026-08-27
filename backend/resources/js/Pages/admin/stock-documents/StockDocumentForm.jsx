import { useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button, Dropdown, Icon } from '@openmes/ui';
import { documentTypeLabels } from './types';
import { __ } from '../../../lib/i18n';

const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

const MATERIAL_TYPES = ['material_issue', 'material_receipt'];

const emptyLine = () => ({ item_id: '', lot_number: '', quantity: '', unit_of_measure: '', notes: '' });

/**
 * THE stock-document create form — the standalone Create page and the list's
 * "New Document" modal both render this, so a field added here appears in both.
 *
 * A custom form rather than ResourceForm because a document is a header plus a
 * variable number of lines, which the config-driven form has no shape for.
 *
 * The item picker follows the chosen type: material documents pick materials,
 * product documents pick product types — the same rule the backend enforces, so
 * the UI cannot compose a document the server would reject.
 *
 * `stay` posts a `stay` flag the controller answers with back() instead of
 * redirecting to the new document — the caller keeps its page (the list), and
 * the new row arrives on its own through the synced collection.
 */
export default function StockDocumentForm({
    warehouses = [], materials = [], productTypes = [], types = [],
    stay = false, cancelHref, onCancel, onSuccess,
}) {
    const [lines, setLines] = useState([emptyLine()]);
    const typeLabels = documentTypeLabels();

    const form = useForm({
        type: types[0] ?? 'material_issue',
        warehouse_id: '',
        notes: '',
        lines: [],
    });
    const { data, setData, errors, processing } = form;

    const isMaterialDocument = MATERIAL_TYPES.includes(data.type);
    const items = isMaterialDocument ? materials : productTypes;

    // Only warehouses that may hold what this document moves.
    const eligibleWarehouses = useMemo(
        () => warehouses.filter((w) => (isMaterialDocument
            ? ['raw_material', 'mixed'].includes(w.kind)
            : ['finished_goods', 'mixed'].includes(w.kind))),
        [warehouses, isMaterialDocument],
    );

    const updateLine = (index, patch) =>
        setLines((current) => current.map((line, i) => (i === index ? { ...line, ...patch } : line)));

    const submit = (event) => {
        event.preventDefault();

        // Map the UI's single item picker onto the column the backend expects.
        const payload = lines
            .filter((line) => line.item_id !== '' || line.quantity !== '')
            .map((line) => ({
                [isMaterialDocument ? 'material_id' : 'product_type_id']: line.item_id || null,
                lot_number: line.lot_number || null,
                quantity: line.quantity,
                unit_of_measure: line.unit_of_measure || null,
                notes: line.notes || null,
            }));

        form
            .transform(() => ({ ...data, lines: payload, ...(stay ? { stay: 1 } : {}) }))
            .post('/admin/stock-documents', { preserveScroll: stay, onSuccess });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="bg-om-surface border border-om-line rounded-om p-5 grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <div className={LABEL_CLASS}>{__('Type')}</div>
                    <Dropdown
                        className="w-full"
                        aria-label={__('Type')}
                        options={(types.length ? types : Object.keys(typeLabels)).map((type) => ({
                            value: type,
                            label: typeLabels[type] ?? type,
                        }))}
                        value={data.type}
                        onChange={(v) => {
                            setData('type', v);
                            // The item ids belong to the other table now.
                            setLines([emptyLine()]);
                            setData('warehouse_id', '');
                        }}
                    />
                    {errors.type && <p className="mt-1 text-[12px] text-om-danger">{errors.type}</p>}
                </div>

                <div>
                    <div className={LABEL_CLASS}>{__('Warehouse')}</div>
                    <Dropdown
                        className="w-full"
                        aria-label={__('Warehouse')}
                        // The empty row is a real choice ("use the default for this
                        // type"), not an empty state, so it is an option rather than a
                        // placeholder — a placeholder could not be picked back.
                        options={[
                            { value: '', label: __('— Default for this type —') },
                            ...eligibleWarehouses.map((w) => ({ value: String(w.id), label: `${w.code} — ${w.name}` })),
                        ]}
                        value={String(data.warehouse_id ?? '')}
                        onChange={(v) => setData('warehouse_id', v)}
                    />
                    {errors.warehouse_id && <p className="mt-1 text-[12px] text-om-danger">{errors.warehouse_id}</p>}
                </div>

                <div>
                    <label className={LABEL_CLASS} htmlFor="notes">{__('Notes')}</label>
                    <input
                        id="notes"
                        className={INPUT_CLASS}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </div>
            </div>

            <div className="bg-om-surface border border-om-line rounded-om p-5">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-[13px] font-medium text-om-ink">{__('Document Lines')}</h2>
                    <Button type="button" variant="secondary" leftIcon={<Icon name="plus" size={14} />} onClick={() => setLines((c) => [...c, emptyLine()])}>
                        {__('Add Line')}
                    </Button>
                </div>

                {errors.lines && <p className="mb-3 text-[12px] text-om-danger">{errors.lines}</p>}

                <div className="space-y-3">
                    {lines.map((line, index) => (
                        <div key={index} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div className="md:col-span-4">
                                <div className={LABEL_CLASS}>
                                    {isMaterialDocument ? __('Material') : __('Product')}
                                </div>
                                <Dropdown
                                    className="w-full"
                                    aria-label={isMaterialDocument ? __('Material') : __('Product')}
                                    // An option, not a placeholder, for the same
                                    // reason as the Warehouse dropdown above:
                                    // clearing a line is how you drop it from the
                                    // submit, and a placeholder can't be picked
                                    // back once something else has been chosen.
                                    options={[
                                        { value: '', label: __('— Select —') },
                                        ...items.map((item) => ({
                                            value: String(item.id),
                                            label: `${item.code} — ${item.name}`,
                                        })),
                                    ]}
                                    value={String(line.item_id ?? '')}
                                    onChange={(v) => {
                                        const item = items.find((i) => String(i.id) === v);
                                        updateLine(index, {
                                            item_id: v,
                                            // Prefill the item's own unit; still editable.
                                            unit_of_measure: line.unit_of_measure || item?.unit_of_measure || '',
                                        });
                                    }}
                                />
                                {(errors[`lines.${index}.material_id`] || errors[`lines.${index}.product_type_id`]) && (
                                    <p className="mt-1 text-[12px] text-om-danger">
                                        {errors[`lines.${index}.material_id`] ?? errors[`lines.${index}.product_type_id`]}
                                    </p>
                                )}
                            </div>

                            {isMaterialDocument && (
                                <div className="md:col-span-2">
                                    <div className={LABEL_CLASS}>{__('Lot')}</div>
                                    <input
                                        aria-label={__('Lot')}
                                        className={INPUT_CLASS}
                                        value={line.lot_number}
                                        onChange={(e) => updateLine(index, { lot_number: e.target.value })}
                                    />
                                </div>
                            )}

                            <div className="md:col-span-2">
                                <div className={LABEL_CLASS}>{__('Quantity')}</div>
                                <input
                                    aria-label={__('Quantity')}
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    className={INPUT_CLASS}
                                    value={line.quantity}
                                    onChange={(e) => updateLine(index, { quantity: e.target.value })}
                                />
                                {errors[`lines.${index}.quantity`] && (
                                    <p className="mt-1 text-[12px] text-om-danger">{errors[`lines.${index}.quantity`]}</p>
                                )}
                            </div>

                            <div className="md:col-span-1">
                                <div className={LABEL_CLASS}>{__('Unit')}</div>
                                <input
                                    aria-label={__('Unit')}
                                    className={INPUT_CLASS}
                                    value={line.unit_of_measure}
                                    onChange={(e) => updateLine(index, { unit_of_measure: e.target.value })}
                                />
                            </div>

                            <div className={isMaterialDocument ? 'md:col-span-2' : 'md:col-span-4'}>
                                <div className={LABEL_CLASS}>{__('Notes')}</div>
                                <input
                                    aria-label={__('Notes')}
                                    className={INPUT_CLASS}
                                    value={line.notes}
                                    onChange={(e) => updateLine(index, { notes: e.target.value })}
                                />
                            </div>

                            <div className="md:col-span-1">
                                <button
                                    type="button"
                                    className="text-[12px] text-om-muted hover:text-om-danger px-2 py-2.5"
                                    onClick={() => setLines((c) => (c.length === 1 ? [emptyLine()] : c.filter((_, i) => i !== index)))}
                                >
                                    {__('Remove')}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing ? __('Saving…') : __('Create Draft')}
                </Button>
                {onCancel ? (
                    <button type="button" onClick={onCancel} className="text-[13px] text-om-muted hover:text-om-ink">
                        {__('Cancel')}
                    </button>
                ) : (
                    <Link href={cancelHref ?? '/admin/stock-documents'} className="text-[13px] text-om-muted hover:text-om-ink">
                        {__('Cancel')}
                    </Link>
                )}
                <span className="text-[12px] text-om-faint">
                    {__('A new document is a draft — posting it is a separate, explicit step.')}
                </span>
            </div>
        </form>
    );
}
