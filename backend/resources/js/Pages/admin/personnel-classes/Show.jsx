import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function PersonnelClassShow() {
    const { personnelClass, workers = [], requiredSkills = [] } = usePage().props;

    const handleDelete = () => {
        if (!confirm('Delete this personnel class?')) return;
        router.delete(`/admin/personnel-classes/${personnelClass.id}`, { preserveScroll: false });
    };

    return (
        <>
            <Head title={`Personnel Class — ${personnelClass.name}`} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-gray-500">{personnelClass.code}</span>
                            {personnelClass.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Active</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">Inactive</span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-gray-800 mt-1">{personnelClass.name}</h1>
                        {personnelClass.description && (
                            <p className="text-gray-600 mt-1">{personnelClass.description}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <a href={`/admin/personnel-classes/${personnelClass.id}/edit`} className="btn-touch btn-secondary">
                            Edit
                        </a>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="btn-touch bg-red-50 text-red-700 hover:bg-red-100"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">
                        {/* Required skills */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Required skills</h2>
                            {requiredSkills.length === 0 ? (
                                <p className="text-sm text-gray-400">No required skills configured.</p>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                            <th className="py-2 pr-3">Skill</th>
                                            <th className="py-2 pr-3">Code</th>
                                            <th className="py-2 pr-3">Minimum cert level</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {requiredSkills.map((skill) => (
                                            <tr key={skill.id}>
                                                <td className="py-2 pr-3 text-gray-800">{skill.name}</td>
                                                <td className="py-2 pr-3 font-mono text-xs text-gray-600">{skill.code}</td>
                                                <td className="py-2 pr-3">
                                                    <span className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                                        {capitalize(skill.min_level)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </section>

                        {/* Workers in this class */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Workers in this class</h2>
                            {workers.length === 0 ? (
                                <p className="text-sm text-gray-400 italic">No workers assigned yet.</p>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                            <th className="py-2 pr-3">Code</th>
                                            <th className="py-2 pr-3">Name</th>
                                            <th className="py-2 pr-3">Qualified</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {workers.map((worker) => (
                                            <tr key={worker.id}>
                                                <td className="py-2 pr-3 font-mono text-xs text-gray-600">{worker.code}</td>
                                                <td className="py-2 pr-3">
                                                    <a href={`/admin/workers/${worker.id}`} className="text-blue-600 hover:underline">
                                                        {worker.name}
                                                    </a>
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {worker.qualified ? (
                                                        <span className="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Yes</span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">Gap</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </section>
                    </div>

                    {/* Metadata sidebar */}
                    <div className="space-y-4">
                        <section className="card">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Metadata</h2>
                            <ul className="space-y-2 text-sm">
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Workers</span>
                                    <span className="font-medium text-gray-800">{workers.length}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Required skills</span>
                                    <span className="font-medium text-gray-800">{requiredSkills.length}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Created</span>
                                    <span className="text-gray-700">{personnelClass.created_at}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-gray-500">Updated</span>
                                    <span className="text-gray-700">{personnelClass.updated_at}</span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

PersonnelClassShow.layout = (page) => <AppLayout>{page}</AppLayout>;

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
