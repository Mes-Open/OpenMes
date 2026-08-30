import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../../layouts/AppLayout';
import { StatusDot } from '../ui';
import { __, formatNumber } from '../../../../lib/i18n';

const inputCls =
    'w-full px-3 py-2 text-sm border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent';

export default function DevicesIndex() {
    const { devices = [], pendingCodes = [], lines = [], workstations = [], generatedCode } = usePage().props;
    const [copied, setCopied] = useState(false);

    const form = useForm({ name: '', line_id: '', workstation_id: '' });

    const lineWorkstations = useMemo(
        () => workstations.filter((w) => String(w.line_id) === String(form.data.line_id)),
        [workstations, form.data.line_id],
    );

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/connectivity/devices/pairing-codes', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const copyCode = () => {
        if (!generatedCode?.plaintext) return;
        navigator.clipboard?.writeText(generatedCode.plaintext).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const revokeCode = (id) => {
        if (!confirm(__('Revoke this pairing code? A device that has not enrolled yet will no longer be able to.'))) return;
        router.delete(`/admin/connectivity/devices/pairing-codes/${id}`, { preserveScroll: true });
    };

    const removeDevice = (id) => {
        if (!confirm(__('Remove this device? Its token is revoked immediately and its sensor stops counting.'))) return;
        router.delete(`/admin/connectivity/devices/${id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={__('Devices')} />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-om-ink">{__('Self-enrolled Devices')}</h1>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Sensors that register themselves over HTTP with a one-time pairing code, then push counting pulses under a device token.')}
                        </p>
                    </div>
                    <Link href="/admin/connectivity" className="text-sm text-om-muted hover:text-om-ink">
                        ← {__('Machine Connectivity')}
                    </Link>
                </div>

                {/* Freshly generated code — shown once */}
                {generatedCode?.plaintext && (
                    <div className="rounded-om border border-om-accent bg-om-chip p-4">
                        <p className="text-sm font-medium text-om-ink">
                            {__('Pairing code for')} “{generatedCode.name}” — {__('copy it now, it is shown only once:')}
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <code className="flex-1 px-3 py-2 rounded bg-om-card border border-om-line font-mono text-sm text-om-ink break-all">
                                {generatedCode.plaintext}
                            </code>
                            <button type="button" onClick={copyCode} className="text-xs px-3 py-2 bg-om-accent text-white rounded-md font-medium">
                                {copied ? __('Copied') : __('Copy')}
                            </button>
                        </div>
                    </div>
                )}

                {/* Generate a pairing code */}
                <div className="bg-om-card rounded-om border border-om-line2 shadow-sm p-5">
                    <h2 className="font-semibold text-om-ink mb-3">{__('Generate a pairing code')}</h2>
                    <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div>
                            <label className="block text-xs text-om-muted mb-1">{__('Device name')}</label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className={inputCls}
                                placeholder={__('e.g. Beam sensor — Station 2')}
                            />
                            {form.errors.name && <p className="text-xs text-om-blocked mt-1">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-xs text-om-muted mb-1">{__('Line (optional)')}</label>
                            <select
                                value={form.data.line_id}
                                onChange={(e) => form.setData({ ...form.data, line_id: e.target.value, workstation_id: '' })}
                                className={inputCls}
                            >
                                <option value="">{__('— Any / set later —')}</option>
                                {lines.map((l) => (
                                    <option key={l.id} value={String(l.id)}>{l.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs text-om-muted mb-1">{__('Station (optional)')}</label>
                            <select
                                value={form.data.workstation_id}
                                onChange={(e) => form.setData('workstation_id', e.target.value)}
                                className={inputCls}
                                disabled={!form.data.line_id}
                            >
                                <option value="">{__('— By step number —')}</option>
                                {lineWorkstations.map((w) => (
                                    <option key={w.id} value={String(w.id)}>{w.name}</option>
                                ))}
                            </select>
                        </div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="px-4 py-2 bg-om-accent text-white rounded-md text-sm font-medium disabled:opacity-50"
                        >
                            {__('Generate code')}
                        </button>
                    </form>
                </div>

                {/* Pending pairing codes */}
                {pendingCodes.length > 0 && (
                    <div className="bg-om-card rounded-om border border-om-line2 shadow-sm p-5">
                        <h2 className="font-semibold text-om-ink mb-3">{__('Pending pairing codes')}</h2>
                        <ul className="divide-y divide-om-line2">
                            {pendingCodes.map((code) => (
                                <li key={code.id} className="flex items-center justify-between py-2 gap-3">
                                    <div className="min-w-0">
                                        <span className="text-sm text-om-ink font-medium">{code.name}</span>
                                        <span className="ml-2 font-mono text-xs text-om-faint">{code.prefix}…</span>
                                        {code.line && <span className="ml-2 text-xs text-om-muted">· {code.line}{code.workstation ? ` / ${code.workstation}` : ''}</span>}
                                        {code.is_expired && <span className="ml-2 text-xs text-om-blocked">{__('expired')}</span>}
                                    </div>
                                    <button type="button" onClick={() => revokeCode(code.id)} className="text-xs px-2 py-1 text-om-blocked hover:underline">
                                        {__('Revoke')}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Enrolled devices */}
                <div className="bg-om-card rounded-om border border-om-line2 shadow-sm p-5">
                    <h2 className="font-semibold text-om-ink mb-3">{__('Enrolled devices')}</h2>
                    {devices.length === 0 ? (
                        <p className="text-sm text-om-faint py-6 text-center">{__('No devices have enrolled yet.')}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-om-muted border-b border-om-line2">
                                        <th className="py-2 pr-3">{__('Device')}</th>
                                        <th className="py-2 pr-3">{__('MAC')}</th>
                                        <th className="py-2 pr-3">{__('Line')}</th>
                                        <th className="py-2 pr-3">{__('Token')}</th>
                                        <th className="py-2 pr-3">{__('Pulses')}</th>
                                        <th className="py-2 pr-3">{__('Last seen')}</th>
                                        <th className="py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {devices.map((d) => (
                                        <tr key={d.id} className="border-b border-om-line2 last:border-0">
                                            <td className="py-2 pr-3">
                                                <span className="inline-flex items-center gap-2">
                                                    <StatusDot color={d.status_color} pulse={d.status === 'connected'} />
                                                    <span className="text-om-ink font-medium">{d.name}</span>
                                                </span>
                                            </td>
                                            <td className="py-2 pr-3 font-mono text-xs text-om-muted">{d.mac_address ?? '—'}</td>
                                            <td className="py-2 pr-3 text-om-muted">{d.line ?? '—'}</td>
                                            <td className="py-2 pr-3">
                                                {d.token ? (
                                                    <span className="font-mono text-xs text-om-faint">{d.token.prefix}…</span>
                                                ) : (
                                                    <span className="text-xs text-om-blocked">{__('none')}</span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-3 text-om-muted">{formatNumber(Number(d.messages_received))}</td>
                                            <td className="py-2 pr-3 text-om-faint text-xs">{d.last_connected_at ?? '—'}</td>
                                            <td className="py-2 text-right">
                                                <button type="button" onClick={() => removeDevice(d.id)} className="text-xs px-2 py-1 text-om-blocked hover:underline">
                                                    {__('Remove')}
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

DevicesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
