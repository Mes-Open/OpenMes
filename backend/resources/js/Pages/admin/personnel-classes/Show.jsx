import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function PersonnelClassShow() {
    const { personnelClass, workers = [], requiredSkills = [] } = usePage().props;

    const requiredSkillsColumns = useMemo(() => [
        {
            id: 'name',
            accessorKey: 'name',
            header: __('Skill'),
            cell: ({ row }) => <span className="text-om-ink">{row.original.name}</span>,
        },
        {
            id: 'code',
            accessorKey: 'code',
            header: __('Code'),
            cell: ({ row }) => <span className="font-mono text-xs text-om-muted">{row.original.code}</span>,
        },
        {
            id: 'min_level',
            accessorKey: 'min_level',
            header: __('Minimum cert level'),
            cell: ({ row }) => (
                <span className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                    {capitalize(row.original.min_level)}
                </span>
            ),
        },
    ], []);

    const workersColumns = useMemo(() => [
        {
            id: 'code',
            accessorKey: 'code',
            header: __('Code'),
            cell: ({ row }) => <span className="font-mono text-xs text-om-muted">{row.original.code}</span>,
        },
        {
            id: 'name',
            accessorKey: 'name',
            header: __('Name'),
            cell: ({ row }) => (
                <a href={`/admin/workers/${row.original.id}`} className="text-om-accent hover:underline">
                    {row.original.name}
                </a>
            ),
        },
        {
            id: 'qualified',
            accessorKey: 'qualified',
            header: __('Qualified'),
            cell: ({ row }) => (
                row.original.qualified ? (
                    <span className="px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running">{__('Yes')}</span>
                ) : (
                    <span className="px-2 py-0.5 rounded-full text-xs bg-om-blocked-bg text-om-blocked">{__('Gap')}</span>
                )
            ),
        },
    ], []);

    const handleDelete = () => {
        if (!confirm(__('Delete this personnel class?'))) return;
        router.delete(`/admin/personnel-classes/${personnelClass.id}`, { preserveScroll: false });
    };

    return (
        <>
            <Head title={__('Personnel Class — :name', { name: personnelClass.name })} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm text-om-muted">{personnelClass.code}</span>
                            {personnelClass.is_active ? (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running">{__('Active')}</span>
                            ) : (
                                <span className="px-2 py-0.5 rounded-full text-xs bg-om-chip text-om-muted">{__('Inactive')}</span>
                            )}
                        </div>
                        <h1 className="text-3xl font-bold text-om-ink mt-1">{personnelClass.name}</h1>
                        {personnelClass.description && (
                            <p className="text-om-muted mt-1">{personnelClass.description}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <a href={`/admin/personnel-classes/${personnelClass.id}/edit`} className="btn-touch btn-secondary">
                            {__('Edit')}
                        </a>
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="btn-touch bg-om-blocked-bg text-om-blocked hover:bg-om-blocked-bg"
                        >
                            {__('Delete')}
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">
                        {/* Required skills */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-3">{__('Required skills')}</h2>
                            {requiredSkills.length === 0 ? (
                                <p className="text-sm text-om-faint">{__('No required skills configured.')}</p>
                            ) : (
                                <DataTable
                                    data={requiredSkills}
                                    columns={requiredSkillsColumns}
                                    searchable={false}
                                    columnToggle={false}
                                    paginated={false}
                                />
                            )}
                        </section>

                        {/* Workers in this class */}
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-3">{__('Workers in this class')}</h2>
                            {workers.length === 0 ? (
                                <p className="text-sm text-om-faint italic">{__('No workers assigned yet.')}</p>
                            ) : (
                                <DataTable
                                    data={workers}
                                    columns={workersColumns}
                                    searchable={false}
                                    columnToggle={false}
                                    paginated={false}
                                />
                            )}
                        </section>
                    </div>

                    {/* Metadata sidebar */}
                    <div className="space-y-4">
                        <section className="card">
                            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-3">{__('Metadata')}</h2>
                            <ul className="space-y-2 text-sm">
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">{__('Workers')}</span>
                                    <span className="font-medium text-om-ink">{workers.length}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">{__('Required skills')}</span>
                                    <span className="font-medium text-om-ink">{requiredSkills.length}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">{__('Created')}</span>
                                    <span className="text-om-muted">{personnelClass.created_at}</span>
                                </li>
                                <li className="flex justify-between gap-2">
                                    <span className="text-om-muted">{__('Updated')}</span>
                                    <span className="text-om-muted">{personnelClass.updated_at}</span>
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
