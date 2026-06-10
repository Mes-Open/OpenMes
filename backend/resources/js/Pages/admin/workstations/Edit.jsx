import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFields from '../../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../../lib/customFieldForm';

export default function WorkstationEdit() {
    const { line, workstation, workers = [], customFields = [] } = usePage().props;

    const assignedWorkerIds = workers
        .filter((w) => w.workstation_id === workstation.id)
        .map((w) => w.id);

    const form = useForm({
        code: workstation.code ?? '',
        name: workstation.name ?? '',
        workstation_type: workstation.workstation_type ?? '',
        is_active: !!workstation.is_active,
        worker_ids: assignedWorkerIds,
        ...customFieldInitial(workstation.custom_fields),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, 'put', `/admin/lines/${line.id}/workstations/${workstation.id}`);
    };

    const toggleWorker = (workerId) => {
        const current = form.data.worker_ids;
        const next = current.includes(workerId)
            ? current.filter((id) => id !== workerId)
            : [...current, workerId];
        form.setData('worker_ids', next);
    };

    return (
        <div className="max-w-2xl mx-auto">
            <Head title={`Edit ${workstation.name}`} />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4 text-sm"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Workstations
                </Link>
                <h1 className="text-3xl font-bold text-gray-800">Edit Workstation</h1>
                <p className="text-sm text-gray-600 mt-1">{line.name}</p>
            </div>

            <form onSubmit={submit} className="bg-white rounded-lg shadow-sm p-6 space-y-5">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Code <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        placeholder="e.g., WS-A01, ASSEMBLY-1"
                        className="form-input w-full"
                        required
                        autoFocus
                    />
                    <p className="text-sm text-gray-500 mt-1">Unique identifier for this workstation</p>
                    {form.errors.code && <p className="mt-1 text-xs text-red-600">{form.errors.code}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Name <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g., Assembly Station 1, Quality Check Point"
                        className="form-input w-full"
                        required
                    />
                    {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Type
                    </label>
                    <input
                        type="text"
                        value={form.data.workstation_type}
                        onChange={(e) => form.setData('workstation_type', e.target.value)}
                        placeholder="e.g., Assembly, Quality Control, Packaging (optional)"
                        className="form-input w-full"
                    />
                    <p className="text-sm text-gray-500 mt-1">Optional classification for this workstation</p>
                    {form.errors.workstation_type && <p className="mt-1 text-xs text-red-600">{form.errors.workstation_type}</p>}
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                    />
                    Active (workstation is ready for use)
                </label>

                {/* Assigned Workers */}
                <div className="border-t border-gray-100 pt-5">
                    <h2 className="text-base font-semibold text-gray-800 mb-1">Assigned Workers</h2>
                    <p className="text-sm text-gray-500 mb-3">Workers regularly operating at this workstation.</p>

                    {workers.length === 0 ? (
                        <p className="text-sm text-gray-400 italic">No active workers in the system.</p>
                    ) : (
                        <div className="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                            {workers.map((worker) => {
                                const isAssigned = form.data.worker_ids.includes(worker.id);
                                return (
                                    <label
                                        key={worker.id}
                                        className={`flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-gray-50 ${isAssigned ? 'bg-blue-50' : ''}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isAssigned}
                                            onChange={() => toggleWorker(worker.id)}
                                            className="rounded border-gray-300 text-blue-600"
                                        />
                                        <div className="flex-1 min-w-0">
                                            <span className="text-sm font-medium text-gray-800">{worker.name}</span>
                                            <span className="text-xs text-gray-400 font-mono ml-2">{worker.code}</span>
                                            {worker.workstation_id && !isAssigned && (
                                                <span className="text-xs text-orange-500 ml-2">
                                                    (currently at: {worker.workstation_name ?? '…'})
                                                </span>
                                            )}
                                        </div>
                                        {worker.crew_name && (
                                            <span className="text-xs text-gray-400 shrink-0">{worker.crew_name}</span>
                                        )}
                                    </label>
                                );
                            })}
                        </div>
                    )}
                    {form.errors.worker_ids && <p className="mt-1 text-xs text-red-600">{form.errors.worker_ids}</p>}
                </div>

                {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Saving…' : 'Update Workstation'}
                    </button>
                    <Link
                        href={`/admin/lines/${line.id}/workstations`}
                        className="text-gray-500 hover:text-gray-800 text-sm"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    );
}

WorkstationEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
