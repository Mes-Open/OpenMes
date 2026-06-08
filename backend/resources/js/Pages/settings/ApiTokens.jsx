import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

export default function ApiTokens() {
    const { tokens, newToken, newTokenName, appUrl, csrf_token } = usePage().props;
    const [copied, setCopied] = useState(false);

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
                <Link href="/settings" className="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('API Tokens')}</h1>
                    <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{__('Manage personal access tokens for external integrations')}</p>
                </div>
            </div>

            {/* New token one-time reveal */}
            {newToken && (
                <div className="mb-6 p-4 bg-yellow-50 dark:bg-amber-900/20 border border-yellow-300 rounded-lg">
                    <div className="flex items-start gap-3">
                        <svg className="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <div className="flex-1">
                            <p className="font-semibold text-yellow-800 mb-1">{__('Token created: :name', { name: newTokenName })}</p>
                            <p className="text-yellow-700 text-sm mb-3">{__('Copy this token now — it will not be shown again.')}</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 bg-white dark:bg-gray-800 border border-yellow-300 rounded px-3 py-2 text-sm font-mono break-all text-gray-800 dark:text-gray-100">
                                    {newToken}
                                </code>
                                <button
                                    type="button"
                                    onClick={handleCopy}
                                    className="btn-secondary text-sm flex-shrink-0 flex items-center gap-1"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {copied ? __('Copied!') : __('Copy')}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Create Token */}
            <div className="card mb-6">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{__('Generate New Token')}</h2>
                <form onSubmit={handleCreate} className="flex items-end gap-3">
                    <div className="flex-1">
                        <label className="form-label" htmlFor="token_name">{__('Token Name')}</label>
                        <input
                            type="text"
                            id="token_name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={`form-input${errors.name ? ' border-red-500' : ''}`}
                            placeholder={__('e.g. PrestaShop Integration')}
                            required
                        />
                        {errors.name && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.name}</p>}
                    </div>
                    <button type="submit" disabled={processing} className="btn-touch btn-primary">
                        {__('Generate Token')}
                    </button>
                </form>
            </div>

            {/* Token List */}
            <div className="card">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{__('Active Tokens')}</h2>
                {!tokens || tokens.length === 0 ? (
                    <p className="text-gray-500 dark:text-gray-400 text-sm py-4 text-center">{__('No tokens generated yet.')}</p>
                ) : (
                    <div className="space-y-3">
                        {tokens.map((token) => (
                            <div key={token.id} className="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <div className="flex-1">
                                    <p className="font-medium text-gray-800 dark:text-gray-100">{token.name}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
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
                                        if (!window.confirm(__("Revoke token ':name'? This cannot be undone.", { name: token.name }))) {
                                            e.preventDefault();
                                        }
                                    }}
                                >
                                    <input type="hidden" name="_token" value={csrf_token} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button type="submit" className="text-red-600 dark:text-red-300 hover:text-red-800 text-sm font-medium">
                                        {__('Revoke')}
                                    </button>
                                </form>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Usage Info */}
            <div className="card mt-6">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-3">{__('How to use')}</h2>
                <p className="text-sm text-gray-600 dark:text-gray-300 mb-3">
                    {__('Include the token in the')} <code className="bg-gray-100 dark:bg-gray-700 px-1 rounded">Authorization</code> {__('header for all API requests:')}
                </p>
                <pre className="bg-gray-800 text-green-400 text-sm rounded-lg p-4 overflow-x-auto">{`Authorization: Bearer <your-token>

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
