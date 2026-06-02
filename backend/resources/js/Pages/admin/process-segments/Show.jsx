import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const TYPE_COLORS = {
    production:  'bg-blue-100 text-blue-800',
    inspection:  'bg-amber-100 text-amber-800',
    maintenance: 'bg-orange-100 text-orange-800',
    setup:       'bg-gray-100 text-gray-700',
    cleaning:    'bg-green-100 text-green-800',
    transport:   'bg-purple-100 text-purple-800',
    other:       'bg-gray-100 text-gray-700',
};

export default function ProcessSegmentShow() {
    const { segment, usingSteps = [], requiredSkills = [] } = usePage().props;

    const typeColor = TYPE_COLORS[segment.segment_type] ?? 'bg-gray-100 text-gray-700';
    const usageCount = usingSteps.length;

    const handleDelete = () => {
        if (!confirm('Delete this process segment?')) return;
        router.delete(`/admin/process-segments/${segment.id}`, { preserveScroll: false });
    };

    return (
        <>
            <Head title={`Process Segment — ${segment.name}`} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-gray-500">{segment.code}</span>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}`}>
                                {capitalize(segment.segment_type)}
                            </span>
                            {segment.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Active</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Inactive</span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-gray-800 mt-1">{segment.name}</h1>
                        {segment.description && (
                            <p className="text-gray-600 mt-1">{segment.description}</p>
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
                            className={`btn-touch ${usageCount > 0 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-red-50 text-red-700 hover:bg-red-100'}`}
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">

                        {/* Definition */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Definition</h2>
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide">Code</dt>
                                    <dd className="mt-1 font-mono text-gray-800">{segment.code}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide">Type</dt>
                                    <dd className="mt-1">
                                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}`}>
                                            {capitalize(segment.segment_type)}
                                        </span>
                                    </dd>
                                </div>
                                {segment.description && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-gray-500 uppercase tracking-wide">Description</dt>
                                        <dd className="mt-1 text-gray-700 whitespace-pre-wrap">{segment.description}</dd>
                                    </div>
                                )}
                                {segment.standard_instruction && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-gray-500 uppercase tracking-wide">Standard instruction</dt>
                                        <dd className="mt-1 text-gray-700 whitespace-pre-wrap p-3 bg-gray-50 rounded">
                                            {segment.standard_instruction}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </section>

                        {/* Execution */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Execution</h2>
                            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide">Workstation type</dt>
                                    <dd className="mt-1 text-gray-800">{segment.workstation_type_name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide">Estimated duration</dt>
                                    <dd className="mt-1 text-gray-800">
                                        {segment.estimated_duration_minutes != null
                                            ? `${segment.estimated_duration_minutes} min`
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide">Required operators</dt>
                                    <dd className="mt-1 text-gray-800">{segment.required_operators}</dd>
                                </div>

                                <div className="sm:col-span-3">
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide mb-1">Required skills</dt>
                                    <dd>
                                        {requiredSkills.length === 0 ? (
                                            <span className="text-sm text-gray-400">— None —</span>
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
                                    <dt className="text-xs text-gray-500 uppercase tracking-wide mb-1">Parameters</dt>
                                    <dd>
                                        {!segment.parameters ? (
                                            <span className="text-sm text-gray-400">— None —</span>
                                        ) : (
                                            <pre className="text-xs bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto">
                                                {JSON.stringify(segment.parameters, null, 2)}
                                            </pre>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        {/* Usage */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Usage</h2>
                            <p className="text-xs text-gray-500 mb-3">Template steps that reference this segment.</p>
                            {usingSteps.length === 0 ? (
                                <p className="text-sm text-gray-500 italic">Not used by any process template yet.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                                <th className="py-2 pr-3">Product</th>
                                                <th className="py-2 pr-3">Template</th>
                                                <th className="py-2 pr-3 text-right">Step #</th>
                                                <th className="py-2 pr-3">Step name</th>
                                                <th className="py-2 pr-3">Workstation</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {usingSteps.map((step) => (
                                                <tr key={step.id} className="hover:bg-gray-50">
                                                    <td className="py-2 pr-3 text-gray-600">{step.product_type_name ?? '—'}</td>
                                                    <td className="py-2 pr-3">
                                                        {step.template_url ? (
                                                            <a href={step.template_url} className="text-blue-600 hover:underline">
                                                                {step.template_name}
                                                            </a>
                                                        ) : (
                                                            <span className="text-gray-500">{step.template_name ?? '—'}</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-3 text-right text-gray-700">{step.step_number}</td>
                                                    <td className="py-2 pr-3 text-gray-800">{step.name}</td>
                                                    <td className="py-2 pr-3 text-gray-600">{step.workstation_name ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </div>

                    {/* Metadata sidebar */}
                    <div className="space-y-4">
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Metadata</h2>
                            <ul className="space-y-2 text-sm">
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Used by</span>
                                    <span className="font-medium text-gray-800">{usageCount} step(s)</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Created</span>
                                    <span className="text-gray-700">{segment.created_at}</span>
                                </li>
                                {segment.created_by_name && (
                                    <li className="flex justify-between gap-2">
                                        <span className="text-gray-500">Created by</span>
                                        <span className="text-gray-700">{segment.created_by_name}</span>
                                    </li>
                                )}
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Updated</span>
                                    <span className="text-gray-700">{segment.updated_at}</span>
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
