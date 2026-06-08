import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function WorkerShow() {
    const { worker, certifications = [], skills = [], levels = [] } = usePage().props;
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

    return (
        <>
            <Head title={__('Worker — :name', { name: worker.name })} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-gray-500">{worker.code}</span>
                            {worker.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{__('Active')}</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{__('Inactive')}</span>
                            )}
                            {worker.personnelClass && (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">
                                    {worker.personnelClass.name}
                                </span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-gray-800 mt-1">{worker.name}</h1>
                        <p className="text-gray-600 mt-1 text-sm">
                            {worker.crew && <span>{__('Crew')}: {worker.crew.name} · </span>}
                            {worker.wageGroup && <span>{__('Wage group')}: {worker.wageGroup.name} · </span>}
                            {worker.email && <span>{worker.email}</span>}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a href={`/admin/workers/${worker.id}/edit`} className="btn-touch btn-secondary">{__('Edit')}</a>
                    </div>
                </div>

                {/* Certifications card */}
                <section className="card">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                        <div>
                            <h2 className="text-lg font-semibold text-gray-700">{__('Certifications')}</h2>
                            <p className="text-xs text-gray-500">{__('ISA-95 Personnel Capability — issued skill certifications with validity windows.')}</p>
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

                    {certifications.length === 0 ? (
                        <p className="text-sm text-gray-500 italic">{__('No certifications recorded.')}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                        <th className="py-2 pr-3">{__('Skill')}</th>
                                        <th className="py-2 pr-3">{__('Cert level')}</th>
                                        <th className="py-2 pr-3">{__('From')}</th>
                                        <th className="py-2 pr-3">{__('Until')}</th>
                                        <th className="py-2 pr-3">{__('Status')}</th>
                                        <th className="py-2 pr-3">{__('Notes')}</th>
                                        <th className="py-2 pr-3 text-right">{__('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {certifications.map((cert) => (
                                        <tr key={cert.skill_id}>
                                            <td className="py-2 pr-3">
                                                <div className="font-medium text-gray-800">{cert.skill_name}</div>
                                                <div className="text-xs font-mono text-gray-500">{cert.skill_code}</div>
                                            </td>
                                            <td className="py-2 pr-3">
                                                <span className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                                    {capitalize(cert.cert_level)}
                                                </span>
                                            </td>
                                            <td className="py-2 pr-3 text-gray-700">{cert.certified_from ?? '—'}</td>
                                            <td className="py-2 pr-3 text-gray-700">{cert.certified_until ?? __('Never')}</td>
                                            <td className="py-2 pr-3">
                                                <StatusBadge status={cert.status} />
                                            </td>
                                            <td className="py-2 pr-3 text-xs text-gray-500">{cert.cert_notes}</td>
                                            <td className="py-2 pr-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => handleDetach(cert.skill_id)}
                                                    className="text-red-600 hover:text-red-800 p-1"
                                                    title={__('Remove')}
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            {/* Add certification modal */}
            {showModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                    onKeyDown={(e) => e.key === 'Escape' && setShowModal(false)}
                >
                    <div
                        className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <form onSubmit={handleAttach}>
                            <div className="p-5 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-800">{__('Add certification')}</h3>
                                <p className="text-xs text-gray-500 mt-1">{__('Record a skill certification for :name.', { name: worker.name })}</p>
                            </div>
                            <div className="p-5 space-y-4">
                                <div>
                                    <label className="form-label">{__('Skill')} <span className="text-red-500">*</span></label>
                                    <select
                                        name="skill_id"
                                        className="form-input w-full"
                                        required
                                        value={form.skill_id}
                                        onChange={(e) => setForm({ ...form, skill_id: e.target.value })}
                                    >
                                        <option value="">{__('— Select —')}</option>
                                        {skills.map((s) => (
                                            <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="form-label">{__('Cert level')} <span className="text-red-500">*</span></label>
                                    <select
                                        name="cert_level"
                                        className="form-input w-full"
                                        required
                                        value={form.cert_level}
                                        onChange={(e) => setForm({ ...form, cert_level: e.target.value })}
                                    >
                                        {levels.map((lvl) => (
                                            <option key={lvl} value={lvl}>{capitalize(lvl)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="form-label">{__('Certified from')}</label>
                                        <input
                                            type="date"
                                            name="certified_from"
                                            className="form-input w-full"
                                            value={form.certified_from}
                                            onChange={(e) => setForm({ ...form, certified_from: e.target.value })}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">{__('Certified until')}</label>
                                        <input
                                            type="date"
                                            name="certified_until"
                                            className="form-input w-full"
                                            value={form.certified_until}
                                            onChange={(e) => setForm({ ...form, certified_until: e.target.value })}
                                        />
                                        <p className="text-xs text-gray-400 mt-1">{__('Leave blank for no expiry.')}</p>
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
                            <div className="p-5 border-t border-gray-200 flex justify-end gap-2">
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
        return <span className="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{__('Valid')}</span>;
    }
    if (status === 'expiring') {
        return <span className="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800">{__('Expires soon')}</span>;
    }
    return <span className="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">{__('Expired')}</span>;
}
