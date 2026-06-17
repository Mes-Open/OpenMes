import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import CustomFields from '../../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../../lib/customFieldForm';

export default function WorkstationCreate() {
    const { line, customFields = [] } = usePage().props;
    const form = useForm({
        code: '',
        name: '',
        workstation_type: '',
        is_active: true,
        ...customFieldInitial(),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, 'post', `/admin/lines/${line.id}/workstations`);
    };

    return (
        <div className="max-w-2xl mx-auto">
            <Head title="Create Workstation" />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Workstations
                </Link>
                <h1 className="text-3xl font-bold text-om-ink">Create Workstation</h1>
                <p className="text-sm text-om-muted mt-1">{line.name}</p>
            </div>

            <form onSubmit={submit} className="bg-om-card rounded-om-sm shadow-sm p-6 space-y-5">
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">
                        Workstation Code <span className="text-om-blocked">*</span>
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
                    <p className="text-sm text-om-muted mt-1">Unique identifier for this workstation</p>
                    {form.errors.code && <p className="mt-1 text-xs text-om-blocked">{form.errors.code}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">
                        Workstation Name <span className="text-om-blocked">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g., Assembly Station 1, Quality Check Point"
                        className="form-input w-full"
                        required
                    />
                    {form.errors.name && <p className="mt-1 text-xs text-om-blocked">{form.errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">
                        Workstation Type
                    </label>
                    <input
                        type="text"
                        value={form.data.workstation_type}
                        onChange={(e) => form.setData('workstation_type', e.target.value)}
                        placeholder="e.g., Assembly, Quality Control, Packaging (optional)"
                        className="form-input w-full"
                    />
                    <p className="text-sm text-om-muted mt-1">Optional classification for this workstation</p>
                    {form.errors.workstation_type && <p className="mt-1 text-xs text-om-blocked">{form.errors.workstation_type}</p>}
                </div>

                <Checkbox
                    checked={form.data.is_active}
                    onChange={(next) => form.setData('is_active', next)}
                    label="Active (workstation is ready for use)"
                />

                {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}

                <div className="flex items-center gap-3 pt-2">
                    <Button type="submit" variant="primary" loading={form.processing} disabled={form.processing}>
                        {form.processing ? 'Creating…' : 'Create Workstation'}
                    </Button>
                    <Link
                        href={`/admin/lines/${line.id}/workstations`}
                        className="text-om-muted hover:text-om-ink text-sm"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    );
}

WorkstationCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
