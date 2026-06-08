import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import InspectionPlanForm from './Form';
import { __, formatDateTime } from '../../../lib/i18n';

function VersionBadge({ v }) {
    const cls = v.is_draft
        ? 'bg-amber-100 text-amber-800'
        : v.is_active
        ? 'bg-green-100 text-green-800'
        : 'bg-gray-100 text-gray-500';
    const label = v.is_draft ? __('Draft') : v.is_active ? __('Published') : __('Archived');
    return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${cls}`}>{label}</span>;
}

export default function InspectionPlanEdit() {
    const { plan, materials = [], materialTypes = [], history = [] } = usePage().props;

    const form = useForm({
        name: plan.name ?? '',
        description: plan.description ?? '',
        scope: plan.scope ?? 'generic',
        material_id: plan.material_id != null ? String(plan.material_id) : '',
        material_type_id: plan.material_type_id != null ? String(plan.material_type_id) : '',
        criteria: plan.criteria ?? [],
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/admin/inspection-plans/${plan.id}`);
    };

    const publish = () => {
        if (confirm(__('Publish this version? It becomes the live plan used for new inspections.'))) {
            router.post(`/admin/inspection-plans/${plan.id}/publish`);
        }
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${__('Edit')} ${plan.name}`} />

            <div className="flex items-center gap-3 mb-2">
                <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('Edit Inspection Plan')}</h1>
                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">v{plan.version}</span>
                <VersionBadge v={{ is_draft: plan.is_draft, is_active: plan.is_active }} />
            </div>

            {/* Lifecycle banner */}
            {plan.is_draft ? (
                <div className="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 flex items-center justify-between gap-4">
                    <p className="text-sm text-amber-800">
                        {__('This is a draft — editable in place and not yet used for inspections.')}
                    </p>
                    <button type="button" onClick={publish} className="btn-touch btn-primary whitespace-nowrap">
                        {__('Publish')}
                    </button>
                </div>
            ) : (
                <div className="mb-5 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                    <p className="text-sm text-blue-800">
                        {__('This version is published and immutable. Saving creates a new draft version — the published version stays unchanged so past inspections remain reproducible.')}
                    </p>
                </div>
            )}

            <InspectionPlanForm
                form={form}
                materials={materials}
                materialTypes={materialTypes}
                submitLabel={plan.is_draft ? __('Save Changes') : __('Save as new version')}
                onSubmit={submit}
            />

            {/* Version history */}
            {history.length > 1 && (
                <div className="mt-8 max-w-4xl">
                    <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-3">{__('Version history')}</h2>
                    <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm divide-y divide-gray-100 dark:divide-slate-700">
                        {history.map((v) => (
                            <div key={v.id} className="flex items-center gap-3 px-4 py-2.5 text-sm">
                                <span className="font-mono text-gray-500 w-10">v{v.version}</span>
                                <VersionBadge v={v} />
                                <span className="text-gray-500 flex-1">
                                    {v.is_draft
                                        ? __('Last edited :t', { t: formatDateTime(v.updated_at) })
                                        : __('Published :t', { t: formatDateTime(v.published_at) })}
                                </span>
                                {v.id !== plan.id && (
                                    <a href={`/admin/inspection-plans/${v.id}/edit`} className="text-blue-600 hover:underline">
                                        {__('Open')}
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

InspectionPlanEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
