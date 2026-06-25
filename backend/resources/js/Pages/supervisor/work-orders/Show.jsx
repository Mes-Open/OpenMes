import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { WO_STATUS_STYLES } from '../../admin/work-orders/fields';
import { formatDate, formatNumber, timeAgo, __ } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

const BATCH_STATUS_STYLES = {
    PENDING: 'bg-gray-100 text-gray-600',
    IN_PROGRESS: 'bg-blue-100 text-blue-600',
    DONE: 'bg-green-100 text-green-600',
};

const STEP_STATUS_STYLES = {
    DONE: 'bg-green-100 text-green-700',
    IN_PROGRESS: 'bg-blue-100 text-blue-700',
};

const ISSUE_STATUS_STYLES = {
    OPEN: 'bg-red-100 text-red-700',
    ACKNOWLEDGED: 'bg-yellow-100 text-yellow-700',
    RESOLVED: 'bg-green-100 text-green-700',
};

function fmtQty(n) {
    return formatNumber(Number(n ?? 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return null;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return formatDate(dt, { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtDateTime(d) {
    if (!d) return null;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return formatDate(dt, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}



function BatchRow({ batch, processSnapshot }) {
    const [open, setOpen] = useState(batch.is_first ?? false);
    const batchStyle = BATCH_STATUS_STYLES[batch.status] ?? 'bg-gray-100 text-gray-400';

    // Build step-number → estimated_duration_minutes map from process_snapshot
    const snapshotSteps = {};
    if (processSnapshot && Array.isArray(processSnapshot.steps)) {
        processSnapshot.steps.forEach((s) => {
            snapshotSteps[s.step_number] = s.estimated_duration_minutes ?? null;
        });
    }

    return (
        <div className="border border-gray-100 rounded-lg p-3">
            <div
                className="flex items-center justify-between cursor-pointer"
                onClick={() => setOpen((o) => !o)}
            >
                <div className="flex items-center gap-3">
                    <span className="font-semibold text-gray-700">Batch #{batch.batch_number}</span>
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${batchStyle}`}>
                        {__(batch.status)}
                    </span>
                    <span className="text-sm text-gray-500">
                        {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                    </span>
                </div>
                <svg
                    className={`w-4 h-4 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            {open && (
                <div className="mt-3 space-y-1">
                    {(batch.steps ?? []).map((step) => {
                        const stepStyle = STEP_STATUS_STYLES[step.status] ?? 'bg-gray-100 text-gray-500';
                        const estimated = snapshotSteps[step.step_number] ?? null;
                        const overTime = estimated && step.duration_minutes != null && step.duration_minutes > estimated;
                        return (
                            <div key={step.id} className="flex items-center gap-3 py-1.5 px-2 rounded text-sm">
                                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-xs flex-shrink-0 ${stepStyle}`}>
                                    {step.step_number}
                                </span>
                                <span className="flex-1 text-gray-700">{step.name}</span>
                                <span className="text-xs text-gray-400">{__(step.status)}</span>
                                {step.duration_minutes != null ? (
                                    <span className={`text-xs font-medium ${overTime ? 'text-red-500' : 'text-green-600'}`}>
                                        {step.duration_minutes}min{estimated ? ` / est. ${estimated}min` : ''}
                                    </span>
                                ) : estimated ? (
                                    <span className="text-xs text-gray-400">est. {estimated}min</span>
                                ) : null}
                            </div>
                        );
                    })}
                    {batch.started_at && (
                        <p className="text-xs text-gray-400 pt-1">
                            Started: {fmtDateTime(batch.started_at)}
                            {batch.completed_at ? ` · Completed: ${fmtDateTime(batch.completed_at)}` : ''}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function DoneModal({ workOrder, onClose }) {
    const [qty, setQty] = useState(String(workOrder.planned_qty ?? ''));

    function handleSubmit(e) {
        e.preventDefault();
        router.post(`/supervisor/work-orders/${workOrder.id}/complete`, { produced_qty: qty }, { preserveScroll: true });
        onClose();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-lg font-bold text-gray-800 mb-4">{__('Complete Work Order')}</h3>
                <p className="text-sm text-gray-600 mb-4">
                    {__('Enter the produced quantity for :order_no.', { order_no: workOrder.order_no })}
                </p>
                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">{__('Produced Quantity')}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            max={workOrder.planned_qty * 2}
                            value={qty}
                            onChange={(e) => setQty(e.target.value)}
                            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        />
                        <p className="text-xs text-gray-500 mt-1">{__('Planned:')} {fmtQty(workOrder.planned_qty)}</p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700"
                        >
                            Mark as Done
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function SupervisorWorkOrderShow() {
    const { workOrder } = usePage().props;
    const [showDoneModal, setShowDoneModal] = useState(false);

    const post = (verb) => router.post(`/supervisor/work-orders/${workOrder.id}/${verb}`, {}, { preserveScroll: true });

    const status = workOrder.status;
    const isTerminal = TERMINAL.includes(status);

    const pct = workOrder.planned_qty > 0
        ? Math.min((workOrder.produced_qty / workOrder.planned_qty) * 100, 100)
        : 0;

    const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < new Date() && status !== 'DONE';

    return (
        <>
            <Head title={__('Work Order :no', { no: workOrder.order_no })} />

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-gray-800 font-mono">{workOrder.order_no}</h1>
                            <span className={`px-2 py-0.5 rounded text-xs font-semibold ${WO_STATUS_STYLES[status] ?? 'bg-gray-100 text-gray-700'}`}>
                                {__(status)}
                            </span>
                        </div>
                        <p className="text-gray-500 mt-1">
                            {__('Created :time', { time: timeAgo(workOrder.created_at) })}
                            {workOrder.product_type_name ? ` · ${workOrder.product_type_name}` : ''}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {status === 'PENDING' && (
                            <>
                                <button
                                    onClick={() => post('accept')}
                                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                                >
                                    Accept
                                </button>
                                <button
                                    onClick={() => { if (confirm('Reject this work order?')) post('reject'); }}
                                    className="px-4 py-2 text-sm font-medium text-red-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Reject
                                </button>
                            </>
                        )}
                        {status === 'ACCEPTED' && (
                            <button
                                onClick={() => { if (confirm('Reject this work order?')) post('reject'); }}
                                className="px-4 py-2 text-sm font-medium text-red-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Reject
                            </button>
                        )}
                        {status === 'IN_PROGRESS' && (
                            <>
                                <button
                                    onClick={() => post('pause')}
                                    className="px-4 py-2 text-sm font-medium text-yellow-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Pause
                                </button>
                                <button
                                    onClick={() => setShowDoneModal(true)}
                                    className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700"
                                >
                                    Done
                                </button>
                            </>
                        )}
                        {status === 'PAUSED' && (
                            <button
                                onClick={() => post('resume')}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                            >
                                Resume
                            </button>
                        )}

                        {isTerminal ? (
                            <button
                                onClick={() => { if (confirm('Reopen this work order?')) post('reopen'); }}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                            >
                                Reopen
                            </button>
                        ) : (
                            <>
                                <Link
                                    href={`/supervisor/work-orders/${workOrder.id}/edit`}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={() => { if (confirm('Cancel this work order?')) post('cancel'); }}
                                    className="px-4 py-2 text-sm font-medium text-orange-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                            </>
                        )}

                        <Link
                            href="/supervisor/work-orders"
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            ← Back
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main */}
                    <div className="lg:col-span-2 space-y-6">

                        {/* Details */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                            <h2 className="text-lg font-bold text-gray-800 mb-4">{__('Details')}</h2>
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p className="text-gray-500">{__('Order Number')}</p>
                                    <p className="font-mono font-semibold text-gray-800">{workOrder.order_no}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">{__('Line')}</p>
                                    <p className="font-medium text-gray-800">{workOrder.line_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">{__('Product Type')}</p>
                                    <p className="font-medium text-gray-800">{workOrder.product_type_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">{__('Planned Qty')}</p>
                                    <p className="font-medium text-gray-800">{fmtQty(workOrder.planned_qty)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">{__('Produced Qty')}</p>
                                    <p className="font-medium text-gray-800">{fmtQty(workOrder.produced_qty)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">{__('Priority')}</p>
                                    <p className="font-medium text-gray-800">{workOrder.priority ?? '—'}</p>
                                </div>
                                {workOrder.due_date && (
                                    <div>
                                        <p className="text-gray-500">{__('Due Date')}</p>
                                        <p className={`font-medium ${isDuePast ? 'text-red-600' : 'text-gray-800'}`}>
                                            {fmtDate(workOrder.due_date)}
                                        </p>
                                    </div>
                                )}
                                {workOrder.description && (
                                    <div className="col-span-2 md:col-span-3">
                                        <p className="text-gray-500">{__('Description')}</p>
                                        <p className="font-medium text-gray-800">{workOrder.description}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Batches */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                            <h2 className="text-lg font-bold text-gray-800 mb-4">
                                {__('Batches')}{' '}
                                <span className="text-sm font-normal text-gray-400">({workOrder.batches.length})</span>
                            </h2>
                            {workOrder.batches.length === 0 ? (
                                <p className="text-sm text-gray-400 py-4 text-center">{__('No batches yet.')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {workOrder.batches.map((batch, i) => (
                                        <BatchRow
                                            key={batch.id}
                                            batch={{ ...batch, is_first: i === 0 }}
                                            processSnapshot={workOrder.process_snapshot}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">

                        {/* Progress */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                            <h3 className="text-base font-bold text-gray-800 mb-3">{__('Progress')}</h3>
                            <div className="mb-3">
                                <div className="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>{__('Completion')}</span>
                                    <span>{pct.toFixed(1)}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-3">
                                    <div
                                        className={`h-3 rounded-full ${pct >= 100 ? 'bg-green-500' : 'bg-blue-500'}`}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">{__('Planned:')}</span>
                                    <span className="font-medium">{fmtQty(workOrder.planned_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">{__('Produced:')}</span>
                                    <span className="font-medium">{fmtQty(workOrder.produced_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">{__('Batches:')}</span>
                                    <span className="font-medium">{workOrder.batches.length}</span>
                                </div>
                            </div>
                        </div>

                        {/* Issues */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                            <div className="flex justify-between items-center mb-3">
                                <h3 className="text-base font-bold text-gray-800">{__('Issues')}</h3>
                                <Link
                                    href="/supervisor/issues"
                                    className="text-xs text-blue-600 hover:underline"
                                >
                                    {__('Manage →')}
                                </Link>
                            </div>
                            {workOrder.issues.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-3">{__('No issues.')}</p>
                            ) : (
                                <div className="space-y-2">
                                    {workOrder.issues.map((issue) => {
                                        const isBlocking = ['OPEN', 'ACKNOWLEDGED'].includes(issue.status) && issue.is_blocking;
                                        const issueStatusStyle = ISSUE_STATUS_STYLES[issue.status] ?? 'bg-gray-100 text-gray-500';
                                        return (
                                            <div
                                                key={issue.id}
                                                className={`p-2 rounded-lg text-xs ${isBlocking ? 'bg-red-50' : 'bg-gray-50'}`}
                                            >
                                                <div className="flex justify-between">
                                                    <span className="font-medium text-gray-800">{issue.issue_type_name}</span>
                                                    <span className={`px-1.5 py-0.5 rounded text-xs ${issueStatusStyle}`}>
                                                        {__(issue.status)}
                                                    </span>
                                                </div>
                                                <p className="text-gray-600 mt-1 truncate">{issue.title}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {showDoneModal && (
                <DoneModal workOrder={workOrder} onClose={() => setShowDoneModal(false)} />
            )}
        </>
    );
}

SupervisorWorkOrderShow.layout = (page) => <AppLayout>{page}</AppLayout>;
