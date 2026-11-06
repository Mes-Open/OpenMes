import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Checkbox, ConfirmDialog, InlineAlert, TextField, useToast } from '@openmes/ui';
import { apiCall, apiGet } from '../../lib/http';
import { __ } from '../../lib/i18n';

/**
 * ERP integration API keys (machine-to-machine, scoped) — the credentials for
 * /api/v1/erp/*. Drives the existing admin endpoints /api/v1/api-keys over
 * fetch and stays on the page, so feedback is a toast, not a flash.
 * The plaintext key is shown once, right after creation.
 */
export default function ErpApiKeys({ scopes = [] }) {
    const toast = useToast();
    const [keys, setKeys] = useState(null);
    const [loadError, setLoadError] = useState(false);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [selected, setSelected] = useState([]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [revealed, setRevealed] = useState(null); // { name, key }
    const [copied, setCopied] = useState(false);
    const [deleting, setDeleting] = useState(null);

    const scopeLabel = (value) => scopes.find((s) => s.value === value)?.label ?? value;

    const load = useCallback(async () => {
        try {
            const res = await apiGet('/api/v1/api-keys');
            if (!res.ok) throw new Error();
            const body = await res.json();
            setKeys(Array.isArray(body?.data) ? body.data : []);
            setLoadError(false);
        } catch {
            setLoadError(true);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    function toggleScope(value, on) {
        setSelected((cur) => (on ? [...cur, value] : cur.filter((v) => v !== value)));
    }

    function resetForm() {
        setCreating(false);
        setName('');
        setSelected([]);
        setErrors({});
    }

    async function create(e) {
        e.preventDefault();
        // UX pre-check; ApiKeyRequest is authoritative.
        const local = {};
        if (name.trim() === '') local.name = __('Enter a name for the key.');
        if (selected.length === 0) local.scopes = __('Select at least one scope.');
        if (Object.keys(local).length > 0) {
            setErrors(local);
            return;
        }

        setProcessing(true);
        try {
            const res = await apiCall('/api/v1/api-keys', 'POST', { name: name.trim(), scopes: selected });
            const body = await res.json().catch(() => ({}));
            if (res.status === 422) {
                const errs = body?.errors ?? {};
                setErrors({
                    name: errs.name?.[0],
                    scopes: Object.entries(errs).find(([k]) => k.startsWith('scopes'))?.[1]?.[0],
                });
                return;
            }
            if (!res.ok) throw new Error();
            setRevealed({ name: body.data?.name ?? name, key: body.plaintext_key });
            setCopied(false);
            resetForm();
            // Show the new row from the 201 itself, so the list never depends on
            // the refetch below (or on live sync) to reflect what just happened.
            if (body.data?.id) {
                setKeys((cur) => [body.data, ...(cur ?? []).filter((k) => k.id !== body.data.id)]);
            }
            toast({ severity: 'success', title: __('Key created.') });
            load();
        } catch {
            toast({ severity: 'error', title: __('Could not save the key.') });
        } finally {
            setProcessing(false);
        }
    }

    async function toggleActive(key) {
        try {
            const res = await apiCall(`/api/v1/api-keys/${key.id}/toggle-active`, 'POST');
            if (!res.ok) throw new Error();
            const body = await res.json().catch(() => ({}));
            const isActive = typeof body?.data?.is_active === 'boolean' ? body.data.is_active : !key.is_active;
            setKeys((cur) => (cur ?? []).map((k) => (k.id === key.id ? { ...k, is_active: isActive } : k)));
            toast({ severity: 'success', title: key.is_active ? __('Key deactivated.') : __('Key activated.') });
            load();
        } catch {
            toast({ severity: 'error', title: __('Could not save the key.') });
        }
    }

    async function destroy(key) {
        try {
            const res = await apiCall(`/api/v1/api-keys/${key.id}`, 'DELETE');
            if (!res.ok) throw new Error();
            setKeys((cur) => (cur ?? []).filter((k) => k.id !== key.id));
            toast({ severity: 'success', title: __('Key deleted.') });
            load();
        } catch {
            toast({ severity: 'error', title: __('Could not save the key.') });
        }
    }

    function copy() {
        navigator.clipboard?.writeText(revealed.key).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <section className="bg-om-card border border-om-line rounded-om p-6 mt-6" aria-labelledby="erp-keys-title">
            <div className="flex items-start justify-between gap-3 mb-4 pb-3 border-b border-om-line">
                <div>
                    <h2 id="erp-keys-title" className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                        {__('ERP integration keys')}
                    </h2>
                    <p className="text-[12.5px] text-om-muted mt-0.5">
                        {__('Scoped keys for the /api/v1/erp endpoints, sent in the X-Api-Key header.')}
                    </p>
                </div>
                {!creating && (
                    <Button type="button" variant="accent" onClick={() => setCreating(true)}>
                        {__('Create key')}
                    </Button>
                )}
            </div>

            {revealed && (
                <InlineAlert severity="warning" title={__('Key created: :name', { name: revealed.name })} className="mb-4">
                    <p className="mb-2">{__('Copy this key now — it will not be shown again.')}</p>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 bg-om-chip rounded-om-sm px-3 py-2 font-mono text-[13px] break-all text-om-ink select-all">
                            {revealed.key}
                        </code>
                        <Button type="button" variant="secondary" onClick={copy} className="flex-shrink-0">
                            {copied ? __('Copied!') : __('Copy')}
                        </Button>
                    </div>
                </InlineAlert>
            )}

            {creating && (
                <form onSubmit={create} className="mb-6 p-4 border border-om-line rounded-om-sm">
                    <TextField
                        label={__('Key name')}
                        id="erp_key_name"
                        value={name}
                        onChange={(v) => setName(v)}
                        error={errors.name}
                        placeholder={__('e.g. Odoo')}
                        required
                    />
                    <fieldset className="mt-4">
                        <legend className="text-[12.5px] font-medium text-om-ink mb-2">{__('Scopes')}</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {scopes.map((s) => (
                                <div key={s.value} className="flex flex-col">
                                    <Checkbox
                                        checked={selected.includes(s.value)}
                                        onChange={(on) => toggleScope(s.value, on)}
                                        label={`${s.label} (${s.value})`}
                                        data-scope={s.value}
                                    />
                                    {s.description && (
                                        <span className="ml-[27px] mt-0.5 text-[11.5px] text-om-muted">{s.description}</span>
                                    )}
                                </div>
                            ))}
                        </div>
                        {errors.scopes && <p className="mt-2 text-[12px] text-om-blocked">{errors.scopes}</p>}
                    </fieldset>
                    <div className="flex items-center gap-2 mt-4">
                        <Button type="submit" variant="accent" loading={processing}>
                            {__('Create')}
                        </Button>
                        <Button type="button" variant="secondary" onClick={resetForm}>
                            {__('Cancel')}
                        </Button>
                    </div>
                </form>
            )}

            {/* A failed background refresh keeps the rows we already have. */}
            {loadError && keys === null ? (
                <p className="text-om-muted text-[12.5px] py-4 text-center">{__('Could not load the keys.')}</p>
            ) : keys === null ? (
                <p className="text-om-muted text-[12.5px] py-4 text-center">{__('Loading…')}</p>
            ) : keys.length === 0 ? (
                <p className="text-om-muted text-[12.5px] py-4 text-center">{__('No integration keys yet.')}</p>
            ) : (
                <div className="divide-y divide-om-line">
                    {keys.map((k) => (
                        <div key={k.id} className="flex items-start justify-between gap-3 py-3">
                            <div className="flex-1 min-w-0">
                                <p className="text-[13.5px] font-medium text-om-ink">
                                    {k.name}{' '}
                                    <span className="font-mono text-[11.5px] text-om-muted">{k.key_prefix}…</span>{' '}
                                    <span className={`text-[11.5px] ${k.is_active ? 'text-om-running' : 'text-om-muted'}`}>
                                        {k.is_active ? __('Active') : __('Inactive')}
                                    </span>
                                </p>
                                <div className="flex flex-wrap gap-1 mt-1.5">
                                    {(k.scopes ?? []).map((s) => (
                                        <Badge key={s} title={scopeLabel(s)}>{s}</Badge>
                                    ))}
                                </div>
                            </div>
                            <div className="flex items-center gap-3 flex-shrink-0">
                                <button
                                    type="button"
                                    onClick={() => toggleActive(k)}
                                    className="text-om-accent hover:underline text-[12.5px] font-medium"
                                >
                                    {k.is_active ? __('Deactivate') : __('Activate')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDeleting(k)}
                                    className="text-om-blocked hover:underline text-[12.5px] font-medium"
                                >
                                    {__('Delete')}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onClose={() => setDeleting(null)}
                    onConfirm={() => {
                        destroy(deleting);
                        setDeleting(null);
                    }}
                    title={__("Delete key ':name'? Its webhooks will be deactivated.", { name: deleting.name })}
                    confirmLabel={__('Delete')}
                    cancelLabel={__('Cancel')}
                />
            )}
        </section>
    );
}
