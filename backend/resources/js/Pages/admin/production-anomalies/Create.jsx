import { useState, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function ProductionAnomalyCreate() {
    const { workOrders = [], anomalyReasons = [], batches = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        work_order_id:     '',
        batch_id:          '',
        anomaly_reason_id: '',
        product_name:      '',
        planned_qty:       '',
        actual_qty:        '',
        comment:           '',
    });

    const filteredBatches = useMemo(
        () => data.work_order_id
            ? batches.filter((b) => String(b.work_order_id) === String(data.work_order_id))
            : [],
        [batches, data.work_order_id],
    );

    function handleWorkOrderChange(v) {
        setData((prev) => ({ ...prev, work_order_id: v, batch_id: '' }));
    }

    function submit(e) {
        e.preventDefault();
        post('/admin/production-anomalies');
    }

    return (
        <>
            <Head title={__('Record Production Anomaly')} />
            <div className="max-w-2xl mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{__('Record Production Anomaly')}</h1>
                        <p className="text-om-muted mt-1">{__('Log a deviation from the production plan')}</p>
                    </div>
                    <Link href="/admin/production-anomalies" className="btn-touch btn-secondary">
                        &larr; {__('Back')}
                    </Link>
                </div>

                <div className="card">
                    <form onSubmit={submit}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {/* Work Order */}
                            <div className="md:col-span-2">
                                <label className="form-label">
                                    {__('Work Order')} <span className="text-om-blocked">*</span>
                                </label>
                                <Dropdown
                                    value={data.work_order_id == null ? '' : String(data.work_order_id)}
                                    onChange={handleWorkOrderChange}
                                    className="w-full"
                                    placeholder={__('— Select work order —')}
                                    options={workOrders.map((wo) => ({
                                        value: String(wo.id),
                                        label: `${wo.order_no}${wo.product_name ? ` — ${wo.product_name}` : ''}`,
                                    }))}
                                />
                                {errors.work_order_id && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.work_order_id}</p>
                                )}
                            </div>

                            {/* Batch */}
                            <div className="md:col-span-2">
                                <label className="form-label">
                                    {__('Batch')} <span className="text-om-faint text-xs">(optional)</span>
                                </label>
                                <Dropdown
                                    value={data.batch_id == null ? '' : String(data.batch_id)}
                                    onChange={(v) => setData('batch_id', v)}
                                    className="w-full"
                                    disabled={filteredBatches.length === 0}
                                    options={[
                                        { value: '', label: __('— No specific batch —') },
                                        ...filteredBatches.map((b) => ({ value: String(b.id), label: b.label })),
                                    ]}
                                />
                                {data.work_order_id && filteredBatches.length === 0 && (
                                    <p className="text-sm text-om-faint mt-1">{__('No batches available for this work order.')}</p>
                                )}
                                {errors.batch_id && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.batch_id}</p>
                                )}
                            </div>

                            {/* Anomaly Reason */}
                            <div className="md:col-span-2">
                                <label className="form-label">
                                    {__('Anomaly Reason')} <span className="text-om-blocked">*</span>
                                </label>
                                <Dropdown
                                    value={data.anomaly_reason_id == null ? '' : String(data.anomaly_reason_id)}
                                    onChange={(v) => setData('anomaly_reason_id', v)}
                                    className="w-full"
                                    placeholder={__('— Select reason —')}
                                    options={anomalyReasons.map((r) => ({
                                        value: String(r.id),
                                        label: `${r.name}${r.category ? ` (${r.category})` : ''}`,
                                    }))}
                                />
                                {errors.anomaly_reason_id && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.anomaly_reason_id}</p>
                                )}
                            </div>

                            {/* Product Name */}
                            <div className="md:col-span-2">
                                <label className="form-label">
                                    {__('Product Name')} <span className="text-om-blocked">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.product_name}
                                    onChange={(e) => setData('product_name', e.target.value)}
                                    className="form-input w-full"
                                    placeholder={__('Product being produced')}
                                    required
                                    maxLength={200}
                                />
                                {errors.product_name && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.product_name}</p>
                                )}
                            </div>

                            {/* Planned Qty */}
                            <div>
                                <label className="form-label">
                                    {__('Planned Quantity')} <span className="text-om-blocked">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.planned_qty}
                                    onChange={(e) => setData('planned_qty', e.target.value)}
                                    className="form-input w-full"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="0.00"
                                />
                                {errors.planned_qty && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.planned_qty}</p>
                                )}
                            </div>

                            {/* Actual Qty */}
                            <div>
                                <label className="form-label">
                                    {__('Actual Quantity')} <span className="text-om-blocked">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.actual_qty}
                                    onChange={(e) => setData('actual_qty', e.target.value)}
                                    className="form-input w-full"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="0.00"
                                />
                                {errors.actual_qty && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.actual_qty}</p>
                                )}
                            </div>

                            {/* Comment */}
                            <div className="md:col-span-2">
                                <label className="form-label">{__('Comment')}</label>
                                <textarea
                                    value={data.comment}
                                    onChange={(e) => setData('comment', e.target.value)}
                                    rows={3}
                                    className="form-input w-full"
                                    maxLength={2000}
                                    placeholder={__('Additional details about the anomaly...')}
                                />
                                {errors.comment && (
                                    <p className="text-om-blocked text-sm mt-1">{errors.comment}</p>
                                )}
                            </div>
                        </div>

                        <div className="flex gap-3 justify-end mt-6">
                            <Link href="/admin/production-anomalies" className="btn-touch btn-secondary">
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="btn-touch btn-primary disabled:opacity-50"
                            >
                                {processing ? __('Saving…') : __('Record Anomaly')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

ProductionAnomalyCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
