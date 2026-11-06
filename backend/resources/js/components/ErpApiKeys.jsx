// ERP integration API keys (machine-to-machine credentials for /api/v1/erp/*).
// Rendered on Settings → API Tokens while the ERP module is on. Writes go over
// fetch to /settings/api-keys and the page does not navigate, so feedback is a
// toast (a flash would need a visit).
import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Checkbox, ConfirmDialog, InlineAlert, TextField, useToast } from '@openmes/ui';
import AppDateTimePicker from './AppDateTimePicker';
import { apiCall, apiGet } from '../lib/http';
import { __, formatDateTime } from '../lib/i18n';

// Session-guarded twin of /api/v1/api-keys (same controller + validation).
const ENDPOINT = '/settings/api-keys';
const NAME_MAX = 120;
const EMPTY_FORM = { name: '', scopes: [], expires_at: '' };

export default function ErpApiKeys({ scopes }) {
    const toast = useToast();
    const [keys, setKeys] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadFailed, setLoadFailed] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    // The plaintext secret, shown once right after creation and never refetched.
    const [created, setCreated] = useState(null);
    const [copied, setCopied] = useState(false);
    const [revoke, setRevoke] = useState(null);

    const load = useCallback(async () => {
        setLoadFailed(false);
        try {
            const res = await apiGet(ENDPOINT);
            if (!res.ok) throw new Error(String(res.status));
            const body = await res.json();
            setKeys(Array.isArray(body?.data) ? body.data : []);
        } catch {
            setLoadFailed(true);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    function toggleScope(value, on) {
        setForm((f) => ({
            ...f,
            scopes: on ? [...f.scopes, value] : f.scopes.filter((s) => s !== value),
        }));
    }

    // UX-only checks; ApiKeyRequest is the authoritative rule set.
    function validate() {
        const e = {};
        const name = form.name.trim();
        if (name === '') e.name = __('Key name is required.');
        else if (name.length > NAME_MAX) e.name = __('Key name may not be longer than :max characters.', { max: NAME_MAX });
        if (form.scopes.length === 0) e.scopes = __('Select at least one scope.');
        return e;
    }

    async function handleCreate(e) {
        e.preventDefault();
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length > 0) return;

        setSaving(true);
        try {
            const res = await apiCall(ENDPOINT, 'POST', {
                name: form.name.trim(),
                scopes: form.scopes,
                expires_at: form.expires_at || null,
            });
            const body = await res.json().catch(() => ({}));
            if (res.status === 422) {
                const fieldErrors = {};
                Object.entries(body?.errors ?? {}).forEach(([field, messages]) => {
                    // scopes.0 → scopes: one message under the scope list is enough.
                    const key = field.split('.')[0];
                    fieldErrors[key] ??= messages[0];
                });
                setErrors(fieldErrors);
                return;
            }
            if (!res.ok) throw new Error(String(res.status));

            setCreated({ name: body.data?.name, key: body.plaintext_key });
            setCopied(false);
            setForm(EMPTY_FORM);
            setErrors({});
            toast({ severity: 'success', title: __('API key created.') });
            load();
        } catch {
            toast({ severity: 'error', title: __('Could not save the API key.') });
        } finally {
            setSaving(false);
        }
    }

    async function confirmRevoke() {
        const key = revoke;
        setRevoke(null);
        try {
            const res = await apiCall(`${ENDPOINT}/${key.id}`, 'DELETE');
            if (!res.ok) throw new Error(String(res.status));
            setKeys((list) => list.filter((k) => k.id !== key.id));
            toast({ severity: 'success', title: __('API key revoked.') });
        } catch {
            toast({ severity: 'error', title: __('Could not revoke the API key.') });
        }
    }

    function handleCopy() {
        if (!created?.key) return;
        navigator.clipboard.writeText(created.key).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <div className="bg-om-card border border-om-line rounded-om p-6 mt-6" data-testid="erp-api-keys">
            <div className="mb-4 pb-3 border-b border-om-line">
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">{__('ERP API keys')}</h2>
                <p className="text-om-muted text-[12.5px] mt-0.5">{__('Machine-to-machine keys for the /api/v1/erp endpoints.')}</p>
            </div>

            {created && (
                <div className="mb-6 p-4 bg-om-downtime-bg border border-om-line rounded-om" data-testid="erp-api-key-reveal">
                    <p className="text-[13px] font-semibold text-om-ink mb-1">{__('API key created: :name', { name: created.name })}</p>
                    <p className="text-om-muted text-[12.5px] mb-3">{__('Copy this key now — it will not be shown again.')}</p>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 bg-om-chip rounded-om-sm px-3 py-2 font-mono text-[13px] break-all text-om-ink select-all">
                            {created.key}
                        </code>
                        <Button type="button" variant="secondary" onClick={handleCopy} className="flex-shrink-0">
                            {copied ? __('Copied!') : __('Copy')}
                        </Button>
                        <Button type="button" variant="ghost" onClick={() => setCreated(null)} className="flex-shrink-0">
                            {__('Close')}
                        </Button>
                    </div>
                </div>
            )}

            <form onSubmit={handleCreate} noValidate className="space-y-4 mb-6">
                <TextField
                    label={__('Key name')}
                    id="erp_key_name"
                    value={form.name}
                    onChange={(v) => setForm((f) => ({ ...f, name: v }))}
                    error={errors.name}
                    placeholder={__('e.g. Odoo')}
                    maxLength={NAME_MAX}
                    required
                />

                <fieldset>
                    <legend className="text-[12.5px] font-medium text-om-ink mb-2">{__('Scopes')}</legend>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {scopes.map((scope) => (
                            <div key={scope.value} className="flex flex-col">
                                <Checkbox
                                    checked={form.scopes.includes(scope.value)}
                                    onChange={(on) => toggleScope(scope.value, on)}
                                    label={
                                        <span>
                                            <code className="font-mono text-[12px]">{scope.value}</code>
                                            <span className="text-om-ink"> — {scope.label}</span>
                                        </span>
                                    }
                                    data-scope={scope.value}
                                />
                                <span className="ml-[27px] mt-0.5 text-[11.5px] text-om-muted">{scope.description}</span>
                            </div>
                        ))}
                    </div>
                    {errors.scopes && (
                        <div role="alert" className="mt-[5px] text-[11.5px] text-om-blocked">{errors.scopes}</div>
                    )}
                </fieldset>

                <div className="max-w-sm">
                    <label htmlFor="erp_key_expires_at" className="block text-[12.5px] font-medium text-om-ink mb-1">
                        {__('Expires at')} <span className="text-om-muted font-normal">({__('optional')})</span>
                    </label>
                    <AppDateTimePicker
                        id="erp_key_expires_at"
                        aria-label={__('Expires at')}
                        value={form.expires_at}
                        onChange={(v) => setForm((f) => ({ ...f, expires_at: v }))}
                    />
                    {errors.expires_at && (
                        <div role="alert" className="mt-[5px] text-[11.5px] text-om-blocked">{errors.expires_at}</div>
                    )}
                </div>

                <Button type="submit" variant="accent" loading={saving}>
                    {__('Create key')}
                </Button>
            </form>

            {loadFailed && (
                <InlineAlert severity="error" className="mb-4">{__('Could not load ERP API keys.')}</InlineAlert>
            )}

            {loading ? (
                <p className="text-om-muted text-[12.5px] py-4 text-center">{__('Loading…')}</p>
            ) : keys.length === 0 ? (
                !loadFailed && <p className="text-om-muted text-[12.5px] py-4 text-center">{__('No ERP API keys yet.')}</p>
            ) : (
                <div className="divide-y divide-om-line">
                    {keys.map((key) => (
                        <div key={key.id} className="flex items-start justify-between gap-4 py-3" data-testid="erp-api-key-row">
                            <div className="flex-1 min-w-0">
                                <p className="text-[13.5px] font-medium text-om-ink">
                                    {key.name}
                                    <span className="ml-2 font-mono text-[11.5px] text-om-muted">{key.key_prefix}…</span>
                                </p>
                                <div className="flex flex-wrap gap-1.5 mt-1.5">
                                    {(key.scopes ?? []).map((s) => (
                                        <Badge key={s}>{s}</Badge>
                                    ))}
                                </div>
                                <p className="text-[11.5px] text-om-muted mt-1.5">
                                    {key.is_active ? __('Active') : __('Inactive')}
                                    &nbsp;&middot;&nbsp;
                                    {key.expires_at
                                        ? __('Expires :date', { date: formatDateTime(key.expires_at) })
                                        : __('Never expires')}
                                    &nbsp;&middot;&nbsp;
                                    {key.last_used_at
                                        ? __('Last used :time', { time: formatDateTime(key.last_used_at) })
                                        : __('Never used')}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setRevoke(key)}
                                className="text-om-blocked hover:underline text-[12.5px] font-medium flex-shrink-0"
                            >
                                {__('Revoke key')}
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {revoke && (
                <ConfirmDialog
                    open
                    onClose={() => setRevoke(null)}
                    onConfirm={confirmRevoke}
                    title={__("Revoke API key ':name'? Integrations using it will stop working.", { name: revoke.name })}
                    confirmLabel={__('Revoke key')}
                    cancelLabel={__('Cancel')}
                />
            )}
        </div>
    );
}
