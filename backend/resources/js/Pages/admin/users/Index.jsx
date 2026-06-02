import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

export default function UsersIndex() {
    const { userRoles = {}, workstationNames = {}, currentUserId } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'username', label: 'Username', className: 'font-mono text-gray-700' },
        { key: 'email', label: 'Email', className: 'text-gray-600' },
        {
            key: 'account_type',
            label: 'Type',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${r.account_type === 'workstation' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}`}>
                    {r.account_type === 'workstation' ? 'Workstation' : 'User'}
                </span>
            ),
        },
        {
            key: 'role',
            label: 'Role / Station',
            className: 'text-gray-600',
            render: (r) => r.account_type === 'workstation'
                ? (workstationNames[r.workstation_id] ?? '—')
                : (userRoles[r.id] ?? '—'),
        },
        { key: 'last_login_at', label: 'Last Login', className: 'text-gray-500', render: (r) => (r.last_login_at ? r.last_login_at.slice(0, 16).replace('T', ' ') : 'never') },
    ];

    const actions = (r) => {
        const acts = [{ label: 'Edit', href: `/admin/users/${r.id}/edit` }];
        if (String(r.id) !== String(currentUserId)) {
            acts.push({
                label: 'Delete',
                className: 'text-red-600 hover:underline',
                onClick: () => {
                    if (confirm(`Delete account "${r.name}"?`)) {
                        router.delete(`/admin/users/${r.id}`, { preserveScroll: true });
                    }
                },
            });
        }
        return acts;
    };

    return (
        <>
            <Head title="Users" />
            <ResourceTable
                shape="users"
                title="Users & Accounts"
                createHref="/admin/users/create"
                createLabel="+ New Account"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No accounts yet."
            />
        </>
    );
}

UsersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
