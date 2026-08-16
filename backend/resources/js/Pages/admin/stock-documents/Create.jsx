import { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { documentTypeLabels } from './types';
import { __ } from '../../../lib/i18n';

const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

const MATERIAL_TYPES = ['material_issue', 'material_receipt'];

const emptyLine = () => ({ item_id: '', lot_number: '', quantity: '', unit_of_measure: '', notes: '' });

/**
 * Manual stock document entry. A custom form rather than ResourceForm because a
 * document is a header plus a variable number of lines, which the config-driven
 * form has no shape for.
 *
 * The item picker follows the chosen type: material documents pick materials,
 * product documents pick product types — the same rule the backend enforces, so
 * the UI cannot compose a document the server would reject.
 */
export default function StockDocumentCreate({ warehouses = [], materials = [], productTypes = [], types = [] }) {
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

        form.transform(() => ({ ...data, lines: payload })).post('/admin/stock-documents');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Stock Document')} />

            <Link href="/admin/stock-documents" className="text-[12px] text-om-muted hover:text-om-ink">
                ‹ {__('Stock Documents')}
            </Link>
            <h1 className="text-3xl font-bold text-om-ink mt-2 mb-6">{__('New Stock Document')}</h1>

            <form onSubmit={submit} className="space-y-5">
                <div className="bg-om-surface border border-om-line rounded-om p-5 grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label className={LABEL_CLASS} htmlFor="type">{__('Type')}</label>
                        <select
                            id="type"
                            className={INPUT_CLASS}
                            value={data.type}
                            onChange={(e) => {
                                setData('type', e.target.value);
                                // The item ids belong to the other table now.
                                setLines([emptyLine()]);
                                setData('warehouse_id', '');
                            }}
                        >
                            {(types.length ? types : Object.keys(typeLabels)).map((type) => (
                                <option key={type} value={type}>{typeLabels[type] ?? type}</option>
                            ))}
                        </select>
                        {errors.type && <p className="mt-1 text-[12px] text-om-danger">{errors.type}</p>}
                    </div>

                    <div>
                        <label className={LABEL_CLASS} htmlFor="warehouse_id">{__('Warehouse')}</label>
                        <select
                            id="warehouse_id"
                            className={INPUT_CLASS}
                            value={data.warehouse_id}
                            onChange={(e) => setData('warehouse_id', e.target.value)}
                        >
                            <option value="">{__('— Default for this type —')}</option>
                            {eligibleWarehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.code} — {w.name}
                                </option>
                            ))}
                        </select>
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
                        <Button type="button" variant="secondary" onClick={() => setLines((c) => [...c, emptyLine()])}>
                            {__('+ Add Line')}
                        </Button>
                    </div>

                    {errors.lines && <p className="mb-3 text-[12px] text-om-danger">{errors.lines}</p>}

                    <div className="space-y-3">
                        {lines.map((line, index) => (
                            <div key={index} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div className="md:col-span-4">
                                    <label className={LABEL_CLASS}>
                                        {isMaterialDocument ? __('Material') : __('Product')}
                                    </label>
                                    <select
                                        className={INPUT_CLASS}
                                        value={line.item_id}
                                        onChange={(e) => {
                                            const item = items.find((i) => String(i.id) === e.target.value);
                                            updateLine(index, {
                                                item_id: e.target.value,
                                                // Prefill the item's own unit; still editable.
                                                unit_of_measure: line.unit_of_measure || item?.unit_of_measure || '',
                                            });
                                        }}
                                    >
                                        <option value="">{__('— Select —')}</option>
                                        {items.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.code} — {item.name}
                                            </option>
                                        ))}
                                    </select>
                                    {(errors[`lines.${index}.material_id`] || errors[`lines.${index}.product_type_id`]) && (
                                        <p className="mt-1 text-[12px] text-om-danger">
                                            {errors[`lines.${index}.material_id`] ?? errors[`lines.${index}.product_type_id`]}
                                        </p>
                                    )}
                                </div>

                                {isMaterialDocument && (
                                    <div className="md:col-span-2">
                                        <label className={LABEL_CLASS}>{__('Lot')}</label>
                                        <input
                                            className={INPUT_CLASS}
                                            value={line.lot_number}
                                            onChange={(e) => updateLine(index, { lot_number: e.target.value })}
                                        />
                                    </div>
                                )}

                                <div className="md:col-span-2">
                                    <label className={LABEL_CLASS}>{__('Quantity')}</label>
                                    <input
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
                                    <label className={LABEL_CLASS}>{__('Unit')}</label>
                                    <input
                                        className={INPUT_CLASS}
                                        value={line.unit_of_measure}
                                        onChange={(e) => updateLine(index, { unit_of_measure: e.target.value })}
                                    />
                                </div>

                                <div className={isMaterialDocument ? 'md:col-span-2' : 'md:col-span-4'}>
                                    <label className={LABEL_CLASS}>{__('Notes')}</label>
                                    <input
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
                    <Link href="/admin/stock-documents" className="text-[13px] text-om-muted hover:text-om-ink">
                        {__('Cancel')}
                    </Link>
                    <span className="text-[12px] text-om-faint">
                        {__('A new document is a draft — posting it is a separate, explicit step.')}
                    </span>
                </div>
            </form>
        </div>
    );
}

StockDocumentCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
