import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function UsersIndex() {
    const { userRoles = {}, workstationNames = {}, currentUserId } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'username', label: __('Username'), className: 'font-mono text-om-muted' },
        { key: 'email', label: __('Email'), className: 'text-om-muted' },
        {
            key: 'account_type',
            label: __('Type'),
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${r.account_type === 'workstation' ? 'bg-om-chip text-om-ink' : 'bg-om-chip text-om-accent'}`}>
                    {r.account_type === 'workstation' ? __('Workstation') : __('User')}
                </span>
            ),
        },
        {
            key: 'role',
            label: __('Role / Station'),
            className: 'text-om-muted',
            render: (r) => r.account_type === 'workstation'
                ? (workstationNames[r.workstation_id] ?? '—')
                : (userRoles[r.id] ?? '—'),
        },
        { key: 'last_login_at', label: __('Last Login'), className: 'text-om-muted', render: (r) => (r.last_login_at ? r.last_login_at.slice(0, 16).replace('T', ' ') : __('never')) },
    ];

    const actions = (r) => {
        const acts = [{ label: __('Edit'), icon: 'edit', href: `/admin/users/${r.id}/edit` }];
        if (String(r.id) !== String(currentUserId)) {
            acts.push({
                label: __('Delete'),
                icon: 'delete',
                variant: 'danger',
                onClick: () => {
                    if (confirm(__('Delete account ":name"?', { name: r.name }))) {
                        router.delete(`/admin/users/${r.id}`, { preserveScroll: true });
                    }
                },
            });
        }
        return acts;
    };

    return (
        <>
            <Head title={__('Users')} />
            <ResourceTable
                shape="users"
                title={__('Users & Accounts')}
                createHref="/admin/users/create"
                createLabel={__('+ New Account')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No accounts yet.')}
            />
        </>
    );
}

UsersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
