import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Button, Modal, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __, timeAgo } from '../../../lib/i18n';
import { useSyncedShape } from '../../../lib/useSyncedShape';

// Operator-panel rows are keyed "panel:<browser id>" (WorkstationDevice::PANEL_UUID_PREFIX).
const PANEL_PREFIX = 'panel:';
// Name a panel row has until an admin renames it (WorkstationDevice::DEFAULT_PANEL_NAME).
const DEFAULT_PANEL_NAME = 'Operator panel';

const isPanel = (r) => (r.device_uuid ?? '').startsWith(PANEL_PREFIX);
const typeLabel = (r) => (isPanel(r) ? __('Operator panel') : __('Workstation client'));
// The stored default is English; show it translated until someone renames the device.
const displayName = (r) => (isPanel(r) && r.name === DEFAULT_PANEL_NAME ? __('Operator panel') : r.name);

/**
 * MAIN-side roster of shop-floor stations: operator panels (browser) and
 * OpenMES Workstation clients. Rows live-sync via the `workstation_devices`
 * shape. A station is active if it last reported "active" and heartbeat within
 * the server-configured window. A ticking clock lets a station that goes
 * silent fade to inactive without needing a row change.
 */
export default function WorkstationDevicesIndex() {
    const { onlineWindowSeconds = 60, lines = {}, workstations = {} } = usePage().props;
    const { data: devices = [] } = useSyncedShape('workstation_devices');
    const { data: users = [] } = useSyncedShape('users');
    const userNames = useMemo(() => Object.fromEntries(users.map((u) => [u.id, u.name])), [users]);
    const who = (r) => (r.user_id ? userNames[r.user_id] ?? `#${r.user_id}` : '—');
    const lineName = (r) => (r.line_id ? lines[r.line_id] ?? `#${r.line_id}` : '—');
    const benchName = (r) => (r.workstation_id ? workstations[r.workstation_id] ?? `#${r.workstation_id}` : '—');

    const [renaming, setRenaming] = useState(null);

    // Re-render every few seconds so active status decays as heartbeats stop.
    const [, setTick] = useState(0);
    useEffect(() => {
        const id = setInterval(() => setTick((t) => t + 1), 5000);
        return () => clearInterval(id);
    }, []);

    const isOnline = (r) => {
        if (r.status === 'inactive' || !r.last_seen_at) return false;
        const seen = new Date(r.last_seen_at).getTime();
        return Date.now() - seen < onlineWindowSeconds * 1000;
    };
    const activeCount = devices.filter(isOnline).length;

    const columns = [
        {
            key: 'status',
            label: __('Status'),
            value: (r) => (isOnline(r) ? __('Active') : __('Inactive')),
            render: (r) => {
                const online = isOnline(r);
                return (
                    <span className="inline-flex items-center gap-2">
                        <span
                            className={`inline-block h-2.5 w-2.5 rounded-full ${online ? 'bg-emerald-500' : 'bg-om-muted/50'}`}
                            aria-hidden="true"
                        />
                        <span className={online ? 'text-emerald-600 font-medium' : 'text-om-muted'}>
                            {online ? __('Active') : __('Inactive')}
                        </span>
                    </span>
                );
            },
        },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text', value: displayName, render: displayName },
        { key: 'type', label: __('Type'), filter: 'select', value: typeLabel, render: typeLabel },
        { key: 'user', label: __('Who'), value: who, render: who },
        { key: 'ip_address', label: __('IP address'), className: 'font-mono text-om-muted' },
        { key: 'line', label: __('Line'), value: lineName, render: lineName },
        { key: 'workstation', label: __('Workstation'), value: benchName, render: benchName },
        { key: 'app_version', label: __('Version'), className: 'font-mono text-om-muted', render: (r) => r.app_version || '—' },
        { key: 'last_seen_at', filter: 'date', label: __('Last seen'), render: (r) => (r.last_seen_at ? timeAgo(r.last_seen_at) : '—') },
    ];

    const actions = (r) => [
        {
            label: __('Rename'),
            icon: 'edit',
            onClick: () => setRenaming(r),
        },
        {
            label: __('Forget'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Forget workstation ":name"? It can re-register later.', { name: displayName(r) }),
                confirmLabel: __('Forget'),
            },
            onClick: () => router.delete(`/admin/workstation-devices/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Workstation Devices')} />
            <ResourceTable
                rows={devices}
                shape="workstation_devices"
                title={__('Workstation Devices')}
                subtitle={
                    <span className="text-sm text-om-muted">
                        {__('Active: :active / :total', { active: activeCount, total: devices.length })}
                    </span>
                }
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No workstations have connected yet. Open the operator panel on a station, or install the OpenMES Workstation client and point it at this server\'s IP.')}
            />
            <RenameModal device={renaming} onClose={() => setRenaming(null)} />
        </>
    );
}

/** Admin names a station ("Tablet - Line 2, assembly"); heartbeats never overwrite it. */
function RenameModal({ device, onClose }) {
    const [name, setName] = useState('');
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (device) {
            setName(displayName(device));
            setError(null);
        }
    }, [device]);

    const submit = (e) => {
        e.preventDefault();
        setSaving(true);
        // Redirects back with a flash message, so the result is a flash, not a toast.
        router.patch(`/admin/workstation-devices/${device.id}`, { name }, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errors) => setError(errors.name ?? null),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            open={device !== null}
            onClose={onClose}
            title={__('Rename device')}
            subtitle={device ? [typeLabel(device), device.ip_address].filter(Boolean).join(' · ') : undefined}
            closeLabel={__('Close')}
            width={440}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={saving}>{__('Cancel')}</Button>
                    <Button type="submit" form="rename-workstation-device" disabled={saving || name.trim() === ''}>{__('Save')}</Button>
                </>
            }
        >
            <form id="rename-workstation-device" onSubmit={submit}>
                <TextField
                    label={__('Name')}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    error={error ?? undefined}
                    hint={__('E.g. the place or the tablet, so stations are told apart by more than their IP.')}
                    maxLength={150}
                    required
                    autoFocus
                />
            </form>
        </Modal>
    );
}

WorkstationDevicesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
