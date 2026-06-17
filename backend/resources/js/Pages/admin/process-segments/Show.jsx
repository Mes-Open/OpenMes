import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const TYPE_COLORS = {
    production:  'bg-om-chip text-om-accent',
    inspection:  'bg-om-downtime-bg text-om-downtime',
    maintenance: 'bg-om-downtime-bg text-om-downtime',
    setup:       'bg-om-chip text-om-muted',
    cleaning:    'bg-om-running-bg text-om-running',
    transport:   'bg-om-chip text-om-ink',
    other:       'bg-om-chip text-om-muted',
};

export default function ProcessSegmentShow() {
    const { segment, usingSteps = [], requiredSkills = [] } = usePage().props;

    const typeColor = TYPE_COLORS[segment.segment_type] ?? 'bg-om-chip text-om-muted';
    const usageCount = usingSteps.length;

    const handleDelete = () => {
        if (!confirm('Delete this process segment?')) return;
        router.delete(`/admin/process-segments/${segment.id}`, { preserveScroll: false });
    };

    const usageColumns = useMemo(() => [
        {
            id: 'product',
            accessorFn: (r) => r.product_type_name,
            header: 'Product',
            cell: ({ row }) => <span className="text-om-muted">{row.original.product_type_name ?? '—'}</span>,
        },
        {
            id: 'template',
            accessorFn: (r) => r.template_name,
            header: 'Template',
            cell: ({ row }) => (
                row.original.template_url ? (
                    <a href={row.original.template_url} className="text-om-accent hover:underline">
                        {row.original.template_name}
                    </a>
                ) : (
                    <span className="text-om-muted">{row.original.template_name ?? '—'}</span>
                )
            ),
        },
        {
            id: 'step_number',
            accessorKey: 'step_number',
            header: 'Step #',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-om-muted">{row.original.step_number}</span>,
        },
        {
            id: 'name',
            accessorKey: 'name',
            header: 'Step name',
            cell: ({ row }) => <span className="text-om-ink">{row.original.name}</span>,
        },
        {
            id: 'workstation',
            accessorFn: (r) => r.workstation_name,
            header: 'Workstation',
            cell: ({ row }) => <span className="text-om-muted">{row.original.workstation_name ?? '—'}</span>,
        },
    ], []);

    return (
        <>
            <Head title={`Process Segment — ${segment.name}`} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-om-muted">{segment.code}</span>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}`}>
                                {capitalize(segment.segment_type)}
                            </span>
                            {segment.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running">Active</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-chip text-om-muted">Inactive</span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-om-ink mt-1">{segment.name}</h1>
                        {segment.description && (
                            <p className="text-om-muted mt-1">{segment.description}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <a href={`/admin/process-segments/${segment.id}/edit`} className="btn-touch btn-secondary">
                            Edit
                        </a>
                        <button
                            type="button"
                            onClick={handleDelete}
                            disabled={usageCount > 0}
                            className={`btn-touch ${usageCount > 0 ? 'bg-om-chip text-om-faint cursor-not-allowed' : 'bg-om-blocked-bg text-om-blocked hover:bg-om-blocked-bg'}`}
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">

                        {/* Definition */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-4">Definition</h2>
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-xs text-om-muted uppercase tracking-wide">Code</dt>
                                    <dd className="mt-1 font-mono text-om-ink">{segment.code}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-om-muted uppercase tracking-wide">Type</dt>
                                    <dd className="mt-1">
                                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}`}>
                                            {capitalize(segment.segment_type)}
                                        </span>
                                    </dd>
                                </div>
                                {segment.description && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-om-muted uppercase tracking-wide">Description</dt>
                                        <dd className="mt-1 text-om-muted whitespace-pre-wrap">{segment.description}</dd>
                                    </div>
                                )}
                                {segment.standard_instruction && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-om-muted uppercase tracking-wide">Standard instruction</dt>
                                        <dd className="mt-1 text-om-muted whitespace-pre-wrap p-3 bg-om-panel rounded">
                                            {segment.standard_instruction}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </section>

                        {/* Execution */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-4">Execution</h2>
                            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <dt className="text-xs text-om-muted uppercase tracking-wide">Workstation type</dt>
                                    <dd className="mt-1 text-om-ink">{segment.workstation_type_name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-om-muted uppercase tracking-wide">Estimated duration</dt>
                                    <dd className="mt-1 text-om-ink">
                                        {segment.estimated_duration_minutes != null
                                            ? `${segment.estimated_duration_minutes} min`
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-om-muted uppercase tracking-wide">Required operators</dt>
                                    <dd className="mt-1 text-om-ink">{segment.required_operators}</dd>
                                </div>

                                <div className="sm:col-span-3">
                                    <dt className="text-xs text-om-muted uppercase tracking-wide mb-1">Required skills</dt>
                                    <dd>
                                        {requiredSkills.length === 0 ? (
                                            <span className="text-sm text-om-faint">— None —</span>
                                        ) : (
                                            <div className="flex flex-wrap gap-1">
                                                {requiredSkills.map((skill) => (
                                                    <span key={skill.id} className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                                        {skill.code} · {skill.name}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </dd>
                                </div>

                                <div className="sm:col-span-3">
                                    <dt className="text-xs text-om-muted uppercase tracking-wide mb-1">Parameters</dt>
                                    <dd>
                                        {!segment.parameters ? (
                                            <span className="text-sm text-om-faint">— None —</span>
                                        ) : (
                                            <pre className="text-xs bg-om-ink text-gray-100 p-3 rounded overflow-x-auto">
                                                {JSON.stringify(segment.parameters, null, 2)}
                                            </pre>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        {/* Usage */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-1">Usage</h2>
                            <p className="text-xs text-om-muted mb-3">Template steps that reference this segment.</p>
                            <DataTable
                                data={usingSteps}
                                columns={usageColumns}
                                searchable={false}
                                columnToggle={false}
                                paginated={false}
                                emptyLabel="Not used by any process template yet."
                            />
                        </section>
                    </div>

                    {/* Metadata sidebar */}
                    <div className="space-y-4">
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-3">Metadata</h2>
                            <ul className="space-y-2 text-sm">
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">Used by</span>
                                    <span className="font-medium text-om-ink">{usageCount} step(s)</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">Created</span>
                                    <span className="text-om-muted">{segment.created_at}</span>
                                </li>
                                {segment.created_by_name && (
                                    <li className="flex justify-between gap-2">
                                        <span className="text-om-muted">Created by</span>
                                        <span className="text-om-muted">{segment.created_by_name}</span>
                                    </li>
                                )}
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">Updated</span>
                                    <span className="text-om-muted">{segment.updated_at}</span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

ProcessSegmentShow.layout = (page) => <AppLayout>{page}</AppLayout>;

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
