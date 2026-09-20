import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import CustomFields from '../../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../../lib/customFieldForm';
import { __ } from '../../../lib/i18n';

export default function WorkstationForm({ line, workstation = null, workers = [], customFields = [], onSuccess, onCancel }) {
    const editing = workstation != null;
    const assignedWorkerIds = workers
        .filter((w) => editing && w.workstation_id === workstation.id)
        .map((w) => w.id);

    const form = useForm({
        code: workstation?.code ?? '',
        name: workstation?.name ?? '',
        workstation_type: workstation?.workstation_type ?? '',
        is_active: editing ? !!workstation.is_active : true,
        worker_ids: assignedWorkerIds,
        ...customFieldInitial(workstation?.custom_fields),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, editing ? 'put' : 'post', editing ? `/admin/lines/${line.id}/workstations/${workstation.id}` : `/admin/lines/${line.id}/workstations`, { preserveScroll: true, onSuccess });
    };

    const toggleWorker = (workerId) => {
        const current = form.data.worker_ids;
        const next = current.includes(workerId)
            ? current.filter((id) => id !== workerId)
            : [...current, workerId];
        form.setData('worker_ids', next);
    };

    return (
            <form onSubmit={submit} className="space-y-5">
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__('Workstation Code')} <span className="text-om-blocked">*</span>
                    </div>
                    <input
                        aria-label={__('Workstation Code')}
                        type="text"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        placeholder={__('e.g., WS-A01, ASSEMBLY-1')}
                        className="form-input w-full"
                        required
                        autoFocus
                    />
                    <p className="text-sm text-om-muted mt-1">{__('Unique identifier for this workstation')}</p>
                    {form.errors.code && <p className="mt-1 text-xs text-om-blocked">{form.errors.code}</p>}
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__('Workstation Name')} <span className="text-om-blocked">*</span>
                    </div>
                    <input
                        aria-label={__('Workstation Name')}
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder={__('e.g., Assembly Station 1, Quality Check Point')}
                        className="form-input w-full"
                        required
                    />
                    {form.errors.name && <p className="mt-1 text-xs text-om-blocked">{form.errors.name}</p>}
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__('Workstation Type')}
                    </div>
                    <input
                        aria-label={__('Workstation Type')}
                        type="text"
                        value={form.data.workstation_type}
                        onChange={(e) => form.setData('workstation_type', e.target.value)}
                        placeholder={__('e.g., Assembly, Quality Control, Packaging (optional)')}
                        className="form-input w-full"
                    />
                    <p className="text-sm text-om-muted mt-1">{__('Optional classification for this workstation')}</p>
                    {form.errors.workstation_type && <p className="mt-1 text-xs text-om-blocked">{form.errors.workstation_type}</p>}
                </div>

                <Checkbox
                    checked={form.data.is_active}
                    onChange={(next) => form.setData('is_active', next)}
                    label={__('Active (workstation is ready for use)')}
                />

                {/* Assigned Workers */}
                {editing && <div className="border-t border-om-line2 pt-5">
                    <h2 className="text-base font-semibold text-om-ink mb-1">{__('Assigned Workers')}</h2>
                    <p className="text-sm text-om-muted mb-3">{__('Workers regularly operating at this workstation.')}</p>

                    {workers.length === 0 ? (
                        <p className="text-sm text-om-faint italic">{__('No active workers in the system.')}</p>
                    ) : (
                        <div className="divide-y divide-om-line2 border border-om-line2 rounded-om-sm overflow-hidden">
                            {workers.map((worker) => {
                                const isAssigned = form.data.worker_ids.includes(worker.id);
                                return (
                                    <div
                                        key={worker.id}
                                        className={`flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-om-bg ${isAssigned ? 'bg-om-chip' : ''}`}
                                    >
                                        <Checkbox
                                            aria-label={worker.name}
                                            label={worker.name}
                                            checked={isAssigned}
                                            onChange={() => toggleWorker(worker.id)}
                                        />
                                        <div className="flex-1 min-w-0">
                                            <span className="text-xs text-om-faint font-mono ml-2">{worker.code}</span>
                                            {worker.workstation_id && !isAssigned && (
                                                <span className="text-xs text-orange-500 ml-2">
                                                    {__('(currently at: :station)', { station: worker.workstation_name ?? '…' })}
                                                </span>
                                            )}
                                        </div>
                                        {worker.crew_name && (
                                            <span className="text-xs text-om-faint shrink-0">{worker.crew_name}</span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    {form.errors.worker_ids && <p className="mt-1 text-xs text-om-blocked">{form.errors.worker_ids}</p>}
                </div>}

                {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}

                <div className="flex items-center gap-3 pt-2">
                    <Button type="submit" variant="primary" loading={form.processing}>
                        {form.processing ? __('Saving…') : editing ? __('Update Workstation') : __('Create Workstation')}
                    </Button>
                    {onCancel ? (
                        <Button variant="outline" onClick={onCancel} disabled={form.processing}>{__('Cancel')}</Button>
                    ) : (
                        <Link href={`/admin/lines/${line.id}/workstations`} className="text-sm text-om-muted hover:text-om-ink">{__('Cancel')}</Link>
                    )}
                </div>
            </form>
    );
}
