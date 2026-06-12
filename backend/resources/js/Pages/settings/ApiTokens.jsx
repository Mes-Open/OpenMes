// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, TextField } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

export default function ApiTokens() {
    const { tokens, newToken, newTokenName, appUrl, csrf_token } = usePage().props;
    const [copied, setCopied] = useState(false);
    // Pending revoke confirmation — holds the token row + its <form> element so
    // confirming submits the exact same native DELETE form.
    const [revoke, setRevoke] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    function handleCreate(e) {
        e.preventDefault();
        post('/settings/api-tokens', {
            onSuccess: () => reset('name'),
        });
    }

    function handleCopy() {
        if (newToken) {
            navigator.clipboard.writeText(newToken).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            });
        }
    }

    const exampleUrl = appUrl ?? window.location.origin;

    return (
        <div className="max-w-4xl mx-auto">
            <Head title={__('API Tokens')} />

            <div className="flex items-center gap-3 mb-6">
                <Link href="/settings" className="text-om-muted hover:text-om-ink transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('API Tokens')}</h1>
                    <p className="text-om-muted text-[12.5px] mt-0.5">{__('Manage personal access tokens for external integrations')}</p>
                </div>
            </div>

            {/* New token one-time reveal */}
            {newToken && (
                <div className="mb-6 p-4 bg-om-downtime-bg border border-om-line rounded-om">
                    <div className="flex items-start gap-3">
                        <svg className="w-5 h-5 text-om-downtime mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <div className="flex-1">
                            <p className="text-[13px] font-semibold text-om-ink mb-1">{__('Token created: :name', { name: newTokenName })}</p>
                            <p className="text-om-muted text-[12.5px] mb-3">{__('Copy this token now — it will not be shown again.')}</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 bg-om-chip rounded-om-sm px-3 py-2 font-mono text-[13px] break-all text-om-ink select-all">
                                    {newToken}
                                </code>
                                <Button type="button" variant="secondary" onClick={handleCopy} className="flex-shrink-0">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {copied ? __('Copied!') : __('Copy')}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Create Token */}
            <div className="bg-om-card border border-om-line rounded-om p-6 mb-6">
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-4 pb-3 border-b border-om-line">{__('Generate New Token')}</h2>
                <form onSubmit={handleCreate} className="flex items-start gap-3">
                    <TextField
                        className="flex-1"
                        label={__('Token Name')}
                        id="token_name"
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        error={errors.name}
                        placeholder={__('e.g. PrestaShop Integration')}
                        required
                    />
                    <Button type="submit" variant="accent" loading={processing} className="mt-[22px]">
                        {__('Generate Token')}
                    </Button>
                </form>
            </div>

            {/* Token List */}
            <div className="bg-om-card border border-om-line rounded-om p-6">
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-4 pb-3 border-b border-om-line">{__('Active Tokens')}</h2>
                {!tokens || tokens.length === 0 ? (
                    <p className="text-om-muted text-[12.5px] py-4 text-center">{__('No tokens generated yet.')}</p>
                ) : (
                    <div className="divide-y divide-om-line">
                        {tokens.map((token) => (
                            <div key={token.id} className="flex items-center justify-between py-3">
                                <div className="flex-1">
                                    <p className="text-[13.5px] font-medium text-om-ink">{token.name}</p>
                                    <p className="text-[11.5px] text-om-muted mt-0.5">
                                        {__('Created by :name', { name: token.tokenable_name ?? __('Unknown') })}
                                        &nbsp;&middot;&nbsp;{token.created_at_formatted}
                                        {token.last_used_at_human
                                            ? <>&nbsp;&middot;&nbsp;{__('Last used :time', { time: token.last_used_at_human })}</>
                                            : <>&nbsp;&middot;&nbsp;{__('Never used')}</>}
                                    </p>
                                </div>
                                <form
                                    method="POST"
                                    action={`/settings/api-tokens/${token.id}`}
                                    onSubmit={(e) => {
                                        // Confirm via ConfirmDialog instead of window.confirm;
                                        // on confirm the same native form is submitted.
                                        e.preventDefault();
                                        setRevoke({ token, form: e.currentTarget });
                                    }}
                                >
                                    <input type="hidden" name="_token" value={csrf_token} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button type="submit" className="text-om-blocked hover:underline text-[12.5px] font-medium">
                                        {__('Revoke')}
                                    </button>
                                </form>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {revoke && (
                <ConfirmDialog
                    open
                    onClose={() => setRevoke(null)}
                    onConfirm={() => revoke.form.submit()}
                    title={__("Revoke token ':name'? This cannot be undone.", { name: revoke.token.name })}
                    confirmLabel={__('Revoke')}
                    cancelLabel={__('Cancel')}
                />
            )}

            {/* Usage Info */}
            <div className="bg-om-card border border-om-line rounded-om p-6 mt-6">
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-3">{__('How to use')}</h2>
                <p className="text-[12.5px] text-om-muted mb-3">
                    {__('Include the token in the')} <code className="bg-om-chip px-1 rounded font-mono text-[12px] text-om-ink">Authorization</code> {__('header for all API requests:')}
                </p>
                <pre className="bg-om-chip text-om-ink font-mono text-[12px] rounded-om-sm p-4 overflow-x-auto">{`Authorization: Bearer <your-token>

# Example — create a work order:
POST ${exampleUrl}/api/v1/work-orders
Content-Type: application/json
Authorization: Bearer <your-token>

{
  "order_no": "PS-0001234",
  "planned_qty": 5,
  "description": "From PrestaShop order #1234"
}`}</pre>
            </div>
        </div>
    );
}

ApiTokens.layout = (page) => <AppLayout>{page}</AppLayout>;
