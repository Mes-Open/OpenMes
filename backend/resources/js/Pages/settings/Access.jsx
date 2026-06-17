import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

/**
 * Role × tab access matrix. Rows are admin-panel tabs, columns are roles.
 * Backend (TabAccessMiddleware) enforces; this just edits the role permissions.
 * The Admin column is locked to full access (can never be revoked).
 */
export default function Access() {
    const { tabs = [], roles = [], matrix = {}, lockedRole = 'Admin' } = usePage().props;

    // form.access = { roleName: [tabKey, ...] }
    const initial = {};
    roles.forEach((role) => {
        initial[role] = role === lockedRole ? tabs.map((t) => t.key) : (matrix[role] ?? []);
    });

    const form = useForm({ access: initial });
    const { data, setData, processing } = form;

    const isChecked = (role, key) =>
        role === lockedRole || (data.access[role] ?? []).includes(key);

    const toggle = (role, key) => {
        if (role === lockedRole) return;
        const current = data.access[role] ?? [];
        const next = current.includes(key)
            ? current.filter((k) => k !== key)
            : [...current, key];
        setData('access', { ...data.access, [role]: next });
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/settings/access', { preserveScroll: true });
    };

    return (
        <div className="max-w-5xl mx-auto">
            <Head title={__('Tab Access')} />

            <Link href="/settings" className="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1 mb-4">
                ‹ {__('Settings')}
            </Link>
            <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Tab Access')}</h1>
            <p className="text-gray-500 dark:text-gray-400 mb-6 text-sm">
                {__('Grant each role access to individual admin-panel tabs. The Admin role always has full access.')}
            </p>

            <form onSubmit={submit} className="card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th className="py-2 pr-4 font-semibold text-gray-700 dark:text-gray-200">{__('Tab')}</th>
                            {roles.map((role) => (
                                <th key={role} className="py-2 px-3 text-center font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                    {role}
                                    {role === lockedRole && (
                                        <span className="block text-[10px] font-normal text-gray-400">{__('full')}</span>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {tabs.map((tab) => (
                            <tr key={tab.key} className="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <td className="py-2 pr-4 font-medium text-gray-800 dark:text-gray-100">{__(tab.label)}</td>
                                {roles.map((role) => (
                                    <td key={role} className="py-2 px-3 text-center">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4 disabled:opacity-50"
                                            checked={isChecked(role, tab.key)}
                                            disabled={role === lockedRole}
                                            onChange={() => toggle(role, tab.key)}
                                        />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="flex items-center gap-3 pt-4">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                        {processing ? __('Saving…') : __('Save')}
                    </button>
                    <Link href="/settings" className="text-gray-500 hover:text-gray-800 text-sm">{__('Cancel')}</Link>
                </div>
            </form>
        </div>
    );
}

Access.layout = (page) => <AppLayout>{page}</AppLayout>;
