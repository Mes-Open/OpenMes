import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';

/**
 * Raise a production-change request (#182).
 *
 * Only the fields the backend allows a request to propose are offered, and a field is
 * only sent when it is ticked — an untouched field means "leave it", which is what
 * keeps the request a proposal rather than a full overwrite of the order.
 */

const FIELDS = [
    { key: 'product_revision_id', label: () => __('Product revision'), type: 'revision' },
    { key: 'planned_qty', label: () => __('Planned quantity'), type: 'number' },
    { key: 'bom_template_ids', label: () => __('BOM selection'), type: 'boms' },
    { key: 'due_date', label: () => __('Due date'), type: 'date' },
    { key: 'description', label: () => __('Description'), type: 'text' },
    { key: 'production_notes', label: () => __('Production notes'), type: 'text' },
];

export default function ChangeRequestModal({ workOrder, options, onClose }) {
    const [enabled, setEnabled] = useState({});
    const form = useForm({
        title: '',
        reason: '',
        effective_from: 'NEXT_BATCH',
        produced_disposition: '',
        material_disposition: '',
        proposed: {},
    });

    function toggle(key) {
        setEnabled((prev) => {
            const next = { ...prev, [key]: !prev[key] };
            if (!next[key]) {
                const proposed = { ...form.data.proposed };
                delete proposed[key];
                form.setData('proposed', proposed);
            }
            return next;
        });
    }

    function setProposed(key, value) {
        form.setData('proposed', { ...form.data.proposed, [key]: value });
    }

    function submit(e) {
        e.preventDefault();
        form.post(`/admin/work-orders/${workOrder.id}/change-requests`, { preserveScroll: true });
    }

    const nothingProposed = Object.keys(form.data.proposed).length === 0;

    function renderInput(field) {
        const value = form.data.proposed[field.key] ?? '';
        const cls = 'w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent';

        if (field.type === 'revision') {
            return (
                <select value={value} onChange={(e) => setProposed(field.key, e.target.value)} className={cls}>
                    <option value="">{__('None')}</option>
                    {(options.productRevisions ?? []).map((r) => (
                        <option key={r.id} value={r.id}>
                            {r.revision_code}{r.lifecycle_status ? ` · ${r.lifecycle_status}` : ''}
                        </option>
                    ))}
                </select>
            );
        }

        if (field.type === 'boms') {
            const selected = Array.isArray(value) ? value : [];
            return (
                <div className="space-y-1 max-h-40 overflow-y-auto border border-om-line rounded-md p-2">
                    {(options.bomTemplates ?? []).map((t) => (
                        <label key={t.id} className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={selected.includes(t.id)}
                                onChange={(e) => setProposed(
                                    field.key,
                                    e.target.checked
                                        ? [...selected, t.id]
                                        : selected.filter((id) => id !== t.id),
                                )}
                            />
                            <span className="text-om-muted">{t.name} <span className="text-om-faint">v{t.version}</span></span>
                        </label>
                    ))}
                </div>
            );
        }

        return (
            <input
                type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                step={field.type === 'number' ? '0.01' : undefined}
                min={field.type === 'number' ? '0.01' : undefined}
                value={value}
                onChange={(e) => setProposed(field.key, e.target.value)}
                className={cls}
            />
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-2xl max-h-full overflow-y-auto">
                <h3 className="text-lg font-bold text-om-ink mb-1">{__('New change request')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    {__('The change is reviewed and approved before it touches :order. Nothing already produced is rewritten.', { order: workOrder.order_no })}
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Title')}</label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            required
                        />
                        {form.errors.title && <p className="text-xs text-om-blocked mt-1">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Reason')}</label>
                        <textarea
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            required
                        />
                        {form.errors.reason && <p className="text-xs text-om-blocked mt-1">{form.errors.reason}</p>}
                    </div>

                    <fieldset className="border border-om-line2 rounded-om-sm p-3">
                        <legend className="text-sm font-medium text-om-muted px-1">{__('What should change')}</legend>
                        <div className="space-y-3">
                            {FIELDS.map((field) => (
                                <div key={field.key}>
                                    <label className="flex items-center gap-2 text-sm mb-1">
                                        <input
                                            type="checkbox"
                                            checked={!!enabled[field.key]}
                                            onChange={() => toggle(field.key)}
                                        />
                                        <span className="font-medium text-om-ink">{field.label()}</span>
                                    </label>
                                    {enabled[field.key] && <div className="ml-6">{renderInput(field)}</div>}
                                    {form.errors[`proposed.${field.key}`] && (
                                        <p className="text-xs text-om-blocked mt-1 ml-6">{form.errors[`proposed.${field.key}`]}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                        {form.errors.proposed && <p className="text-xs text-om-blocked mt-2">{form.errors.proposed}</p>}
                    </fieldset>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Applies from')}</label>
                        <select
                            value={form.data.effective_from}
                            onChange={(e) => form.setData('effective_from', e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                        >
                            {(options.effective_points ?? []).map((p) => (
                                <option key={p.value} value={p.value}>{p.label}</option>
                            ))}
                        </select>
                        <p className="text-xs text-om-faint mt-1">
                            {__('Immediate is only possible while nothing has been executed under the current configuration.')}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-om-muted mb-1">{__('Disposition of produced units')}</label>
                            <textarea
                                rows={2}
                                value={form.data.produced_disposition}
                                onChange={(e) => form.setData('produced_disposition', e.target.value)}
                                className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-om-muted mb-1">{__('Disposition of material')}</label>
                            <textarea
                                rows={2}
                                value={form.data.material_disposition}
                                onChange={(e) => form.setData('material_disposition', e.target.value)}
                                className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            {__('Cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing || nothingProposed}
                            title={nothingProposed ? __('Tick at least one field to change.') : undefined}
                            className="px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover disabled:opacity-50"
                        >
                            {__('Create change request')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
