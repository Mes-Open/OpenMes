import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Icon, IconButton, Modal } from '@openmes/ui';
import WorkstationForm from './WorkstationForm';
import AppLayout from '../../../layouts/AppLayout';
import { ActiveBadge } from '../../../components/ResourceTable';
import Tooltip from '../../../components/Tooltip';
import useConfirm from '../../../components/useConfirm';
import { __ } from '../../../lib/i18n';

export default function WorkstationsIndex() {
    const { line, workstations = [], workers = [], customFields = [] } = usePage().props;
    const [editing, setEditing] = useState(null);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleToggle = (ws) => {
        router.post(`/admin/lines/${line.id}/workstations/${ws.id}/toggle-active`, {}, { preserveScroll: true });
    };

    const handleDelete = (ws) => {
        confirm(
            {
                title: __('Are you sure you want to delete this workstation?'),
                confirmLabel: __('Delete workstation'),
            },
            () => router.delete(`/admin/lines/${line.id}/workstations/${ws.id}`, { preserveScroll: true }),
        );
    };

    return (
        <div className="w-full px-4 py-5 sm:px-6 sm:py-6">
            <Head title={__('Workstations — :name', { name: line.name })} />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}`}
                    className="inline-flex items-center gap-2 mb-4 rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-[12px] font-medium text-om-ink hover:bg-om-chip"
                >
                    <Icon name="arrow-left" size={16} />
                    {__('Back to :name', { name: line.name })}
                </Link>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-[28px] font-semibold tracking-tight text-om-ink">{__('Workstations')}</h1>
                        <p className="text-sm text-om-muted mt-1">{line.name}</p>
                    </div>
                    <Link
                        href={`/admin/lines/${line.id}/workstations/create`}
                        className="inline-flex items-center gap-1.5 bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
                    >
                        <Icon name="plus" size={14} />
                        {__('Add Workstation')}
                    </Link>
                </div>
            </div>

            {workstations.length === 0 ? (
                <div className="bg-om-card rounded-om border border-om-line text-center px-5 py-12">
                    <Icon name="monitor" size={40} className="mx-auto text-om-faint mb-4" />
                    <p className="text-lg font-medium text-om-muted">{__('No workstations yet')}</p>
                    <p className="text-sm text-om-muted mt-1 mb-4">{__('Get started by creating your first workstation for this line.')}</p>
                    <Link
                        href={`/admin/lines/${line.id}/workstations/create`}
                        className="inline-flex items-center gap-1.5 bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
                    >
                        <Icon name="plus" size={14} />
                        {__('Create Workstation')}
                    </Link>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {workstations.map((ws) => (
                        <div key={ws.id} className="bg-om-card rounded-om border border-om-line p-5">
                            <div className="flex items-start justify-between mb-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2 mb-1">
                                        <h3 className="text-[15px] font-semibold text-om-ink break-words">{ws.name}</h3>
                                        <ActiveBadge active={ws.is_active} />
                                    </div>
                                    <p className="text-[11px] text-om-faint font-mono">{ws.code}</p>
                                    {ws.workstation_type && (
                                        <p className="text-xs text-om-muted mt-1">{__('Type: :type', { type: ws.workstation_type })}</p>
                                    )}
                                </div>
                            </div>

                            <div className="mb-4 p-3 bg-om-panel rounded-om-sm grid grid-cols-2 gap-3">
                                <div className="text-center">
                                    <p className="font-mono text-[22px] text-om-ink">{ws.template_steps_count}</p>
                                    <p className="flex items-center justify-center gap-1.5 text-xs text-om-muted"><Icon name="workflow" size={13} />{__('Template Steps')}</p>
                                </div>
                                <div className="text-center">
                                    <p className="font-mono text-[22px] text-om-ink">{ws.workers_count}</p>
                                    <p className="flex items-center justify-center gap-1.5 text-xs text-om-muted"><Icon name="users" size={13} />{__('Workers')}</p>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4 border-t border-om-line2">
                                <button type="button"
                                    onClick={() => setEditing(ws)}
                                    className="flex-1 inline-flex items-center justify-center gap-2 text-[12px] px-3 py-2 border border-om-line rounded-om-sm text-om-muted hover:bg-om-bg font-medium"
                                >
                                    <Icon name="pencil" size={14} />
                                    {__('Edit')}
                                </button>
                                <Tooltip label={ws.is_active ? __('Deactivate') : __('Activate')}>
                                    <IconButton
                                        onClick={() => handleToggle(ws)}
                                        className="border border-om-line"
                                        aria-label={ws.is_active ? __('Deactivate') : __('Activate')}
                                    >
                                        {ws.is_active ? (
                                            <Icon name="circle-slash" size={16} />
                                        ) : (
                                            <Icon name="circle-check" size={16} />
                                        )}
                                    </IconButton>
                                </Tooltip>
                                {ws.template_steps_count === 0 ? (
                                    <Tooltip label={__('Delete')}>
                                        <IconButton
                                            onClick={() => handleDelete(ws)}
                                            variant="danger"
                                            aria-label={__('Delete')}
                                        >
                                            <Icon name="trash-2" size={16} />
                                        </IconButton>
                                    </Tooltip>
                                ) : (
                                    <Tooltip label={__('Cannot delete — has template steps')}>
                                        <span className="inline-flex size-[38px] items-center justify-center text-om-faintest">
                                            <Icon name="lock-keyhole" size={16} />
                                        </span>
                                    </Tooltip>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                side="right"
                width={560}
                title={__('Edit Workstation')}
                subtitle={editing ? `${line.name} · ${editing.name}` : line.name}
                closeLabel={__('Close')}
            >
                {editing && <WorkstationForm
                    key={editing.id}
                    line={line}
                    workstation={editing}
                    workers={workers}
                    customFields={customFields}
                    onSuccess={() => setEditing(null)}
                    onCancel={() => setEditing(null)}
                />}
            </Modal>
            {confirmDialog}
        </div>
    );
}

WorkstationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
