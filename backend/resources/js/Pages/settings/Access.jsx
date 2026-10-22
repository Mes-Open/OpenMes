import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

/**
 * Role × tab access matrix. Rows are admin-panel tabs, columns are roles.
 * Backend (TabAccessMiddleware) enforces; this just edits the role permissions.
 * The Admin column is locked to full access (can never be revoked). Geist White.
 */
export default function Access() {
    const { tabs = [], roles = [], matrix = {}, grants = [], grantMatrix = {}, lockedRole = 'Admin' } = usePage().props;

    // form.access = { roleName: [tabKey, ...] }, form.grants = { roleName: [permission, ...] }
    const initial = {};
    const initialGrants = {};
    roles.forEach((role) => {
        initial[role] = role === lockedRole ? tabs.map((t) => t.key) : (matrix[role] ?? []);
        initialGrants[role] = role === lockedRole ? grants.map((g) => g.key) : (grantMatrix[role] ?? []);
    });

    const form = useForm({ access: initial, grants: initialGrants });
    const { data, setData, processing } = form;

    const isChecked = (field, role, key) => role === lockedRole || (data[field][role] ?? []).includes(key);

    const toggle = (field, role, key) => {
        if (role === lockedRole) return;
        const current = data[field][role] ?? [];
        const next = current.includes(key) ? current.filter((k) => k !== key) : [...current, key];
        setData(field, { ...data[field], [role]: next });
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/settings/access', { preserveScroll: true });
    };

    return (
        <div className="max-w-5xl mx-auto">
            <Head title={__('Tab Access')} />

            <Link href="/settings" className="mb-4 flex items-center gap-1 text-sm text-om-accent hover:underline">
                ‹ {__('Settings')}
            </Link>
            <h1 className="mb-1 text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Tab Access')}</h1>
            <p className="mb-6 text-sm text-om-muted">
                {__('Grant each role access to individual admin-panel tabs. The Admin role always has full access.')}
            </p>

            <form onSubmit={submit} className="overflow-x-auto rounded-om border border-om-line bg-om-card p-5">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-om-line2 text-left">
                            <th className="py-2 pr-4 font-mono text-[9px] font-medium uppercase tracking-[0.1em] text-om-faint">
                                {__('Tab')}
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role}
                                    className="whitespace-nowrap px-3 py-2 text-center font-mono text-[9px] font-medium uppercase tracking-[0.1em] text-om-faint"
                                >
                                    {role}
                                    {role === lockedRole && (
                                        <span className="block text-[9px] font-normal normal-case tracking-normal text-om-faintest">
                                            {__('full')}
                                        </span>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {tabs.map((tab) => (
                            <tr key={tab.key} className="border-b border-om-line2 last:border-0">
                                <td className="py-2.5 pr-4 font-medium text-om-ink">{__(tab.label)}</td>
                                {roles.map((role) => (
                                    <td key={role} className="px-3 py-2.5 text-center">
                                        <span className="inline-flex justify-center">
                                            <Checkbox
                                                checked={isChecked('access', role, tab.key)}
                                                disabled={role === lockedRole}
                                                onChange={() => toggle('access', role, tab.key)}
                                            />
                                        </span>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                {grants.length > 0 && (
                    <>
                        <h2 className="mt-6 text-sm font-semibold text-om-ink">{__('Quality')}</h2>
                        <p className="mb-2 text-xs text-om-muted">
                            {__('Who may carry out inbound inspections, and who may decide on the inspected lot. Individual people can also be granted these on their account.')}
                        </p>
                        <table className="w-full text-sm">
                            <tbody>
                                {grants.map((grant) => (
                                    <tr key={grant.key} className="border-b border-om-line2 last:border-0">
                                        <td className="py-2.5 pr-4">
                                            <span className="font-medium text-om-ink">{__(grant.label)}</span>
                                            <span className="block text-xs text-om-faint">{__(grant.description)}</span>
                                        </td>
                                        {roles.map((role) => (
                                            <td key={role} className="px-3 py-2.5 text-center">
                                                <span className="inline-flex justify-center">
                                                    <Checkbox
                                                        checked={isChecked('grants', role, grant.key)}
                                                        disabled={role === lockedRole}
                                                        onChange={() => toggle('grants', role, grant.key)}
                                                        aria-label={`${__(grant.label)} — ${role}`}
                                                    />
                                                </span>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                )}

                <div className="flex items-center gap-3 pt-4">
                    <Button type="submit" variant="primary" loading={processing}>
                        {processing ? __('Saving…') : __('Save')}
                    </Button>
                    <Link href="/settings" className="text-sm text-om-muted hover:text-om-ink">
                        {__('Cancel')}
                    </Link>
                </div>
            </form>
        </div>
    );
}

Access.layout = (page) => <AppLayout>{page}</AppLayout>;
