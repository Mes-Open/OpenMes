import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import CustomFields from '../../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../../lib/customFieldForm';
import { __ } from '../../../lib/i18n';

// The operator tabs a bench can show, in tab order (keys match OperatorScreens::ALL).
const SCREENS = [
    { key: 'queue', label: () => __('Queue') },
    { key: 'workstation', label: () => __('Workstation') },
    { key: 'unit_labels', label: () => __('SN labels') },
    { key: 'packing', label: () => __('Packing') },
];

// What the SN label station offers at this bench (keys match UnitLabelActions::ALL); none = everything.
const LABEL_ACTIONS = [
    { key: 'start', label: () => __('Start unit on PSN'), hint: () => __('the first bench: the unit starts and its PSN label prints') },
    { key: 'label', label: () => __('Bind the serial label to the PSN'), hint: () => __('scan the PSN, then the ready serial label') },
    { key: 'issue', label: () => __('Issue numbers'), hint: () => __('next numbers from the sequences, batches of labels') },
    { key: 'components', label: () => __('Components'), hint: () => __('scan parts and sub-assemblies into a unit') },
    { key: 'subassembly', label: () => __('Sub-assemblies'), hint: () => __('register a sub-assembly made here by its serial') },
];

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
        // null = the tabs follow the routing; a list pins them.
        operator_screens: workstation?.operator_screens ?? null,
        // null = the SN label station offers everything here.
        unit_label_actions: workstation?.unit_label_actions ?? null,
        ...customFieldInitial(workstation?.custom_fields),
    });
    const derivedScreens = workstation?.derived_screens ?? ['queue', 'workstation'];
    const autoScreens = form.data.operator_screens == null;
    const toggleScreen = (key) => {
        const current = form.data.operator_screens ?? derivedScreens;
        const next = current.includes(key) ? current.filter((k) => k !== key) : [...current, key];
        // Keep at least one: a bench with no tab would strand its operator.
        if (next.length > 0) form.setData('operator_screens', SCREENS.map((sc) => sc.key).filter((k) => next.includes(k)));
    };

    const toggleLabelAction = (key) => {
        const current = form.data.unit_label_actions ?? [];
        const next = current.includes(key) ? current.filter((k) => k !== key) : [...current, key];
        form.setData('unit_label_actions', next.length ? LABEL_ACTIONS.map((a) => a.key).filter((k) => next.includes(k)) : null);
    };

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

                {/* Operator tabs: what the person at this bench sees in the top bar */}
                <div className="border-t border-om-line2 pt-5" data-testid="operator-screens">
                    <h2 className="text-base font-semibold text-om-ink mb-1">{__('Operator screens')}</h2>
                    <p className="text-sm text-om-muted mb-3">{__('The tabs an operator at this workstation sees. Supervisors, admins and the whole-line view always see every tab.')}</p>
                    <Checkbox
                        checked={autoScreens}
                        onChange={(next) => form.setData('operator_screens', next ? null : derivedScreens)}
                        label={__('Automatic (from the steps assigned to this workstation)')}
                    />
                    <p className="text-xs text-om-faint mt-1 mb-3">
                        {__('Shown automatically: :screens', { screens: SCREENS.filter((sc) => derivedScreens.includes(sc.key)).map((sc) => sc.label()).join(', ') })}
                    </p>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        {SCREENS.map((sc) => (
                            <Checkbox
                                key={sc.key}
                                label={sc.label()}
                                disabled={autoScreens}
                                checked={(form.data.operator_screens ?? derivedScreens).includes(sc.key)}
                                onChange={() => toggleScreen(sc.key)}
                            />
                        ))}
                    </div>
                    {(form.errors.operator_screens || form.errors['operator_screens.0']) && <p className="mt-1 text-xs text-om-blocked">{form.errors.operator_screens || form.errors['operator_screens.0']}</p>}
                </div>

                {/* SN label station: what the operator does here */}
                <div className="border-t border-om-line2 pt-5" data-testid="unit-label-actions">
                    <h2 className="text-base font-semibold text-om-ink mb-1">{__('SN label station')}</h2>
                    <p className="text-sm text-om-muted mb-3">{__('What the SN label station offers an operator at this workstation. Nothing ticked: everything. Supervisors, admins and the whole-line view always see everything.')}</p>
                    <div className="flex flex-col gap-2">
                        {LABEL_ACTIONS.map((a) => (
                            <Checkbox
                                key={a.key}
                                label={`${a.label()} - ${a.hint()}`}
                                checked={(form.data.unit_label_actions ?? []).includes(a.key)}
                                onChange={() => toggleLabelAction(a.key)}
                            />
                        ))}
                    </div>
                    {(form.errors.unit_label_actions || form.errors['unit_label_actions.0']) && <p className="mt-1 text-xs text-om-blocked">{form.errors.unit_label_actions || form.errors['unit_label_actions.0']}</p>}
                </div>

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
