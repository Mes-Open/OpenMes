import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function CustomFieldsIndex() {
    const { definitions = [], entities = [] } = usePage().props;
    const [entity, setEntity] = useState('');

    const rows = entity ? definitions.filter((d) => d.entity_type === entity) : definitions;

    const toggle = (d) => router.post(`/admin/custom-fields/${d.id}/toggle-active`, {}, { preserveScroll: true });
    const destroy = (d) => {
        if (confirm(`Delete custom field "${d.label}"? Stored values on existing records are left untouched.`)) {
            router.delete(`/admin/custom-fields/${d.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Custom Fields" />
            <div className="max-w-6xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800">Custom Fields</h1>
                        <p className="text-sm text-gray-500 mt-1">Admin-defined fields attached to records across the system.</p>
                    </div>
                    <Link href="/admin/custom-fields/create" className="btn-touch btn-primary text-sm">+ New Custom Field</Link>
                </div>

                <div className="mb-4">
                    <select value={entity} onChange={(e) => setEntity(e.target.value)} className="form-input">
                        <option value="">All entities</option>
                        {entities.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </div>

                <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <Th>Entity</Th>
                                <Th>Key</Th>
                                <Th>Label</Th>
                                <Th>Type</Th>
                                <Th center>Required</Th>
                                <Th center>Position</Th>
                                <Th center>Status</Th>
                                <Th right>Actions</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-400">No custom fields yet.</td></tr>
                            )}
                            {rows.map((d) => (
                                <tr key={d.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 text-sm text-gray-700">{d.entity_label}</td>
                                    <td className="px-4 py-2 text-sm font-mono text-gray-600">{d.key}</td>
                                    <td className="px-4 py-2 text-sm font-medium text-gray-800">{d.label}</td>
                                    <td className="px-4 py-2 text-sm text-gray-600">
                                        {d.type_label}
                                        {d.options_count > 0 && <span className="text-gray-400"> ({d.options_count})</span>}
                                    </td>
                                    <td className="px-4 py-2 text-center text-sm">{d.required ? 'Yes' : '—'}</td>
                                    <td className="px-4 py-2 text-center text-sm text-gray-500">{d.position}</td>
                                    <td className="px-4 py-2 text-center">
                                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${d.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                                            {d.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2">
                                        <div className="flex items-center justify-end gap-3 text-sm">
                                            <Link href={`/admin/custom-fields/${d.id}/edit`} className="text-blue-600 hover:text-blue-800">Edit</Link>
                                            <button type="button" onClick={() => toggle(d)} className="text-gray-600 hover:text-gray-900">
                                                {d.is_active ? 'Deactivate' : 'Activate'}
                                            </button>
                                            <button type="button" onClick={() => destroy(d)} className="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function Th({ children, right, center }) {
    const align = right ? 'text-right' : center ? 'text-center' : 'text-left';
    return <th className={`px-4 py-2 ${align} text-xs font-medium text-gray-500 uppercase`}>{children}</th>;
}

CustomFieldsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
