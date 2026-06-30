import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

export default function WorkerShow() {
    const { worker, certifications = [], skills = [], levels = [], customFields = [] } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({
        skill_id: '',
        cert_level: levels[1] ?? 'operator',
        certified_from: '',
        certified_until: '',
        cert_notes: '',
    });

    const handleAttach = (e) => {
        e.preventDefault();
        router.post(`/admin/workers/${worker.id}/skills`, form, {
            onSuccess: () => {
                setShowModal(false);
                setForm({ skill_id: '', cert_level: levels[1] ?? 'operator', certified_from: '', certified_until: '', cert_notes: '' });
            },
            preserveScroll: true,
        });
    };

    const handleDetach = (skillId) => {
        if (!confirm(__('Remove this certification?'))) return;
        router.delete(`/admin/workers/${worker.id}/skills/${skillId}`, { preserveScroll: true });
    };

    const certColumns = useMemo(() => [
        {
            id: 'skill',
            accessorFn: (r) => r.skill_name,
            header: __('Skill'),
            cell: ({ row }) => (
                <>
                    <div className="font-medium text-om-ink">{row.original.skill_name}</div>
                    <div className="text-xs font-mono text-om-muted">{row.original.skill_code}</div>
                </>
            ),
        },
        {
            id: 'cert_level',
            accessorKey: 'cert_level',
            header: __('Cert level'),
            cell: ({ row }) => (
                <span className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                    {capitalize(row.original.cert_level)}
                </span>
            ),
        },
        {
            id: 'certified_from',
            accessorKey: 'certified_from',
            header: __('From'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.certified_from ?? '—'}</span>,
        },
        {
            id: 'certified_until',
            accessorKey: 'certified_until',
            header: __('Until'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.certified_until ?? __('Never')}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            id: 'cert_notes',
            accessorKey: 'cert_notes',
            header: __('Notes'),
            cell: ({ row }) => <span className="text-xs text-om-muted">{row.original.cert_notes}</span>,
        },
        {
            id: 'actions',
            header: __('Actions'),
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => (
                <button
                    type="button"
                    onClick={() => handleDetach(row.original.skill_id)}
                    className="text-om-blocked hover:text-om-blocked p-1"
                    title={__('Remove')}
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            ),
        },
    ], []);

    return (
        <>
            <Head title={__('Worker — :name', { name: worker.name })} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-om-muted">{worker.code}</span>
                            {worker.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running">{__('Active')}</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-chip text-om-muted">{__('Inactive')}</span>
                            )}
                            {worker.personnelClass && (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">
                                    {worker.personnelClass.name}
                                </span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-om-ink mt-1">{worker.name}</h1>
                        <p className="text-om-muted mt-1 text-sm">
                            {worker.crew && <span>{__('Crew')}: {worker.crew.name} · </span>}
                            {worker.wageGroup && <span>{__('Wage group')}: {worker.wageGroup.name} · </span>}
                            {worker.email && <span>{worker.email}</span>}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={`/admin/workers/${worker.id}/edit`} className="btn-touch btn-secondary">{__('Edit')}</a>
                    </div>
                </div>

                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={worker.custom_fields ?? {}} />
                </div>

                {/* Certifications card */}
                <section className="card">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                        <div>
                            <h2 className="text-lg font-semibold text-om-muted">{__('Certifications')}</h2>
                            <p className="text-xs text-om-muted">{__('ISA-95 Personnel Capability — issued skill certifications with validity windows.')}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowModal(true)}
                            className="btn-touch btn-primary inline-flex items-center gap-2 text-sm"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {__('Add certification')}
                        </button>
                    </div>

                    <DataTable
                        data={certifications}
                        columns={certColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={__('No certifications recorded.')}
                    />
                </section>
            </div>

            {/* Add certification modal */}
            {showModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                    onKeyDown={(e) => e.key === 'Escape' && setShowModal(false)}
                >
                    <div
                        className="bg-om-card rounded-om-sm shadow-xl w-full max-w-lg mx-4"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <form onSubmit={handleAttach}>
                            <div className="p-5 border-b border-om-line2">
                                <h3 className="text-lg font-semibold text-om-ink">{__('Add certification')}</h3>
                                <p className="text-xs text-om-muted mt-1">{__('Record a skill certification for :name.', { name: worker.name })}</p>
                            </div>
                            <div className="p-5 space-y-4">
                                <div>
                                    <label className="form-label">{__('Skill')} <span className="text-om-blocked">*</span></label>
                                    <Dropdown
                                        className="w-full"
                                        placeholder={__('— Select —')}
                                        options={skills.map((s) => ({ value: String(s.id), label: `${s.name} (${s.code})` }))}
                                        value={form.skill_id == null ? '' : String(form.skill_id)}
                                        onChange={(v) => setForm({ ...form, skill_id: v })}
                                    />
                                </div>
                                <div>
                                    <label className="form-label">{__('Cert level')} <span className="text-om-blocked">*</span></label>
                                    <Dropdown
                                        className="w-full"
                                        options={levels.map((lvl) => ({ value: String(lvl), label: capitalize(lvl) }))}
                                        value={form.cert_level == null ? '' : String(form.cert_level)}
                                        onChange={(v) => setForm({ ...form, cert_level: v })}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="form-label">{__('Certified from')}</label>
                                        <DatePicker
                                            className="w-full"
                                            value={form.certified_from || null}
                                            onChange={(iso) => setForm({ ...form, certified_from: iso ?? '' })}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">{__('Certified until')}</label>
                                        <DatePicker
                                            className="w-full"
                                            value={form.certified_until || null}
                                            onChange={(iso) => setForm({ ...form, certified_until: iso ?? '' })}
                                        />
                                        <p className="text-xs text-om-faint mt-1">{__('Leave blank for no expiry.')}</p>
                                    </div>
                                </div>
                                <div>
                                    <label className="form-label">{__('Notes')}</label>
                                    <textarea
                                        name="cert_notes"
                                        rows="2"
                                        className="form-input w-full"
                                        maxLength="1000"
                                        value={form.cert_notes}
                                        onChange={(e) => setForm({ ...form, cert_notes: e.target.value })}
                                    />
                                </div>
                            </div>
                            <div className="p-5 border-t border-om-line2 flex justify-end gap-2">
                                <button type="button" onClick={() => setShowModal(false)} className="btn-touch btn-secondary">{__('Cancel')}</button>
                                <button type="submit" className="btn-touch btn-primary">{__('Save certification')}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

WorkerShow.layout = (page) => <AppLayout>{page}</AppLayout>;

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function StatusBadge({ status }) {
    if (status === 'valid') {
        return <span className="px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running">{__('Valid')}</span>;
    }
    if (status === 'expiring') {
        return <span className="px-2 py-0.5 rounded-full text-xs bg-om-downtime-bg text-om-downtime">{__('Expires soon')}</span>;
    }
    return <span className="px-2 py-0.5 rounded-full text-xs bg-om-blocked-bg text-om-blocked">{__('Expired')}</span>;
}
