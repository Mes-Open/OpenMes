import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function WorkstationsIndex() {
    const { line, workstations = [] } = usePage().props;

    const handleToggle = (ws) => {
        router.post(`/admin/lines/${line.id}/workstations/${ws.id}/toggle-active`, {}, { preserveScroll: true });
    };

    const handleDelete = (ws) => {
        if (confirm(__('Are you sure you want to delete this workstation?'))) {
            router.delete(`/admin/lines/${line.id}/workstations/${ws.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Workstations — :name', { name: line.name })} />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}`}
                    className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4 text-sm"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {__('Back to :name', { name: line.name })}
                </Link>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800">{__('Workstations')}</h1>
                        <p className="text-sm text-gray-600 mt-1">{line.name}</p>
                    </div>
                    <Link
                        href={`/admin/lines/${line.id}/workstations/create`}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                    >
                        {__('+ Add Workstation')}
                    </Link>
                </div>
            </div>

            {workstations.length === 0 ? (
                <div className="bg-white rounded-lg shadow-sm text-center py-12">
                    <svg className="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <p className="text-lg font-medium text-gray-700">{__('No workstations yet')}</p>
                    <p className="text-sm text-gray-500 mt-1 mb-4">{__('Get started by creating your first workstation for this line.')}</p>
                    <Link
                        href={`/admin/lines/${line.id}/workstations/create`}
                        className="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                    >
                        {__('+ Create Workstation')}
                    </Link>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {workstations.map((ws) => (
                        <div key={ws.id} className="bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition-shadow">
                            <div className="flex items-start justify-between mb-3">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 mb-1">
                                        <h3 className="text-lg font-bold text-gray-800">{ws.name}</h3>
                                        <ActiveBadge active={ws.is_active} />
                                    </div>
                                    <p className="text-sm text-gray-500 font-mono">{ws.code}</p>
                                    {ws.workstation_type && (
                                        <p className="text-xs text-gray-600 mt-1">{__('Type: :type', { type: ws.workstation_type })}</p>
                                    )}
                                </div>
                            </div>

                            <div className="mb-4 p-3 bg-gray-50 rounded-lg grid grid-cols-2 gap-3">
                                <div className="text-center">
                                    <p className="text-2xl font-bold text-purple-600">{ws.template_steps_count}</p>
                                    <p className="text-xs text-gray-600">{__('Template Steps')}</p>
                                </div>
                                <div className="text-center">
                                    <p className="text-2xl font-bold text-blue-600">{ws.workers_count}</p>
                                    <p className="text-xs text-gray-600">{__('Workers')}</p>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4 border-t border-gray-200">
                                <Link
                                    href={`/admin/lines/${line.id}/workstations/${ws.id}/edit`}
                                    className="flex-1 text-center text-sm px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium"
                                >
                                    {__('Edit')}
                                </Link>
                                <button
                                    onClick={() => handleToggle(ws)}
                                    className="p-2 text-gray-600 hover:text-gray-800"
                                    title={ws.is_active ? __('Deactivate') : __('Activate')}
                                >
                                    {ws.is_active ? (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                    ) : (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    )}
                                </button>
                                {ws.template_steps_count === 0 ? (
                                    <button
                                        onClick={() => handleDelete(ws)}
                                        className="p-2 text-red-600 hover:text-red-800"
                                        title={__('Delete')}
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                ) : (
                                    <span className="p-2 text-gray-300" title={__('Cannot delete — has template steps')}>
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </span>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

WorkstationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
