import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function CustomFieldsIndex() {
    const { definitions = [], entities = [] } = usePage().props;
    const [entity, setEntity] = useState('');

    const rows = entity ? definitions.filter((d) => d.entity_type === entity) : definitions;

    const toggle = (d) => router.post(`/admin/custom-fields/${d.id}/toggle-active`, {}, { preserveScroll: true });
    const destroy = (d) => {
        if (confirm(__('Delete custom field ":label"? Stored values on existing records are left untouched.', { label: d.label }))) {
            router.delete(`/admin/custom-fields/${d.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={__('Custom Fields')} />
            <div className="max-w-6xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{__('Custom Fields')}</h1>
                        <p className="text-sm text-om-muted mt-1">{__('Admin-defined fields attached to records across the system.')}</p>
                    </div>
                    <Link href="/admin/custom-fields/create" className="btn-touch btn-primary text-sm">{__('+ New Custom Field')}</Link>
                </div>

                <div className="mb-4">
                    <select value={entity} onChange={(e) => setEntity(e.target.value)} className="form-input">
                        <option value="">{__('All entities')}</option>
                        {entities.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </div>

                <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden">
                    <table className="min-w-full divide-y divide-om-line2">
                        <thead className="bg-om-panel">
                            <tr>
                                <Th>{__('Entity')}</Th>
                                <Th>{__('Key')}</Th>
                                <Th>{__('Label')}</Th>
                                <Th>{__('Type')}</Th>
                                <Th center>{__('Required')}</Th>
                                <Th center>{__('Position')}</Th>
                                <Th center>{__('Status')}</Th>
                                <Th right>{__('Actions')}</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-om-line2">
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-sm text-om-faint">{__('No custom fields yet.')}</td></tr>
                            )}
                            {rows.map((d) => (
                                <tr key={d.id} className="hover:bg-om-bg">
                                    <td className="px-4 py-2 text-sm text-om-muted">{d.entity_label}</td>
                                    <td className="px-4 py-2 text-sm font-mono text-om-muted">{d.key}</td>
                                    <td className="px-4 py-2 text-sm font-medium text-om-ink">{d.label}</td>
                                    <td className="px-4 py-2 text-sm text-om-muted">
                                        {d.type_label}
                                        {d.options_count > 0 && <span className="text-om-faint"> ({d.options_count})</span>}
                                    </td>
                                    <td className="px-4 py-2 text-center text-sm">{d.required ? __('Yes') : '—'}</td>
                                    <td className="px-4 py-2 text-center text-sm text-om-muted">{d.position}</td>
                                    <td className="px-4 py-2 text-center">
                                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${d.is_active ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-muted'}`}>
                                            {d.is_active ? __('Active') : __('Inactive')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2">
                                        <div className="flex items-center justify-end gap-3 text-sm">
                                            <Link href={`/admin/custom-fields/${d.id}/edit`} className="text-om-accent hover:text-om-accent">{__('Edit')}</Link>
                                            <button type="button" onClick={() => toggle(d)} className="text-om-muted hover:text-om-ink">
                                                {d.is_active ? __('Deactivate') : __('Activate')}
                                            </button>
                                            <button type="button" onClick={() => destroy(d)} className="text-om-blocked hover:text-om-blocked">{__('Delete')}</button>
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
    return <th className={`px-4 py-2 ${align} text-xs font-medium text-om-muted uppercase`}>{children}</th>;
}

CustomFieldsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
