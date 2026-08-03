import { Head, Link, router } from '@inertiajs/react';
import { Button, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const TYPE_LABELS = {
    material_issue: __('Material release'),
    material_receipt: __('Material receipt'),
    product_receipt: __('Product receipt'),
    product_issue: __('Product release'),
};

const STATUS_PILL = { draft: 'pending', posted: 'done', cancelled: 'blocked' };

function Field({ label, children }) {
    return (
        <div>
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{label}</div>
            <div className="text-[13px] text-om-ink">{children ?? '—'}</div>
        </div>
    );
}

/**
 * One warehouse document: its header, its lines and the two state transitions.
 * Posting and cancelling go through the server (StockDocumentService owns the
 * balance and ledger effects), so this page never mutates stock itself.
 */
export default function StockDocumentShow({ document: doc }) {
    const sign = doc.direction === 'in' ? '+' : '−';

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={doc.document_no} />

            <Link href="/admin/stock-documents" className="text-[12px] text-om-muted hover:text-om-ink">
                ‹ {__('Stock Documents')}
            </Link>

            <div className="flex items-center justify-between gap-4 mt-2 mb-6">
                <h1 className="text-3xl font-bold text-om-ink font-mono">{doc.document_no}</h1>
                <div className="flex items-center gap-2">
                    <StatusPill status={STATUS_PILL[doc.status] ?? 'pending'} label={__(doc.status)} />
                    {doc.status === 'draft' && (
                        <Button
                            onClick={() => {
                                if (confirm(__('Post document :no? This moves stock.', { no: doc.document_no }))) {
                                    router.post(`/admin/stock-documents/${doc.id}/post`, {}, { preserveScroll: true });
                                }
                            }}
                        >
                            {__('Post')}
                        </Button>
                    )}
                    {doc.status === 'posted' && (
                        <Button
                            variant="secondary"
                            onClick={() => {
                                if (confirm(__('Cancel document :no? This reverses the stock it moved.', { no: doc.document_no }))) {
                                    router.post(`/admin/stock-documents/${doc.id}/cancel`, {}, { preserveScroll: true });
                                }
                            }}
                        >
                            {__('Cancel Document')}
                        </Button>
                    )}
                </div>
            </div>

            <div className="bg-om-surface border border-om-line rounded-om p-5 mb-5 grid grid-cols-2 md:grid-cols-4 gap-5">
                <Field label={__('Type')}>{TYPE_LABELS[doc.type] ?? doc.type}</Field>
                <Field label={__('Warehouse')}>
                    {doc.warehouse ? `${doc.warehouse.code} — ${doc.warehouse.name}` : null}
                </Field>
                <Field label={__('Work Order')}>{doc.work_order_no}</Field>
                <Field label={__('Direction')}>
                    {doc.direction === 'in' ? __('Into warehouse') : __('Out of warehouse')}
                </Field>
                <Field label={__('Created')}>{doc.created_at}</Field>
                <Field label={__('Created By')}>{doc.created_by}</Field>
                <Field label={__('Posted')}>{doc.posted_at}</Field>
                <Field label={__('Posted By')}>{doc.posted_by}</Field>
                <Field label={__('ERP Reference')}>{doc.erp_reference}</Field>
                <Field label={__('ERP Synced')}>{doc.erp_synced_at}</Field>
                {doc.notes && (
                    <div className="col-span-2 md:col-span-4">
                        <Field label={__('Notes')}>{doc.notes}</Field>
                    </div>
                )}
            </div>

            <div className="bg-om-surface border border-om-line rounded-om overflow-hidden">
                <div className="px-5 py-3 border-b border-om-line text-[13px] font-medium text-om-ink">
                    {__('Lines')}
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr className="text-om-faint font-mono text-[9.5px] uppercase tracking-[0.08em]">
                                <th className="text-left px-5 py-2">{__('Code')}</th>
                                <th className="text-left px-5 py-2">{__('Item')}</th>
                                <th className="text-left px-5 py-2">{__('Lot')}</th>
                                <th className="text-right px-5 py-2">{__('Quantity')}</th>
                                <th className="text-left px-5 py-2">{__('Notes')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {doc.lines.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-4 text-om-muted">
                                        {__('This document has no lines.')}
                                    </td>
                                </tr>
                            )}
                            {doc.lines.map((line) => (
                                <tr key={line.id} className="border-t border-om-line2">
                                    <td className="px-5 py-2.5 font-mono text-om-muted">{line.item_code ?? '—'}</td>
                                    <td className="px-5 py-2.5 text-om-ink">{line.item_name ?? '—'}</td>
                                    <td className="px-5 py-2.5 font-mono text-om-muted">{line.lot_number ?? '—'}</td>
                                    <td className="px-5 py-2.5 text-right text-om-ink">
                                        {sign}
                                        {Number(line.quantity).toLocaleString()} {line.unit_of_measure ?? ''}
                                    </td>
                                    <td className="px-5 py-2.5 text-om-muted">{line.notes ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

StockDocumentShow.layout = (page) => <AppLayout>{page}</AppLayout>;
