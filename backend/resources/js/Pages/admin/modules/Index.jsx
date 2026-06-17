// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (enable/disable/uninstall logic + all __() translations untouched).
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function ModulesIndex() {
    const { modules = [], csrf_token } = usePage().props;

    const [toUninstall, setToUninstall] = useState(null);

    function postForm(action) {
        router.post(action, {}, { preserveScroll: true });
    }

    function confirmDestroy() {
        if (toUninstall) {
            router.delete(`/admin/modules/${toUninstall.name}`);
        }
        setToUninstall(null);
    }

    return (
        <>
            <Head title={__('Installed Modules')} />
            <div className="max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Installed Modules')}</h1>
                        <p className="text-om-muted text-sm mt-0.5">{__('Enable and disable installed OpenMES extensions')}</p>
                    </div>
                    <Link href="/admin/modules/install">
                        <Button variant="primary">{__('+ Install Module')}</Button>
                    </Link>
                </div>

                {modules.length === 0 ? (
                    <div className="bg-om-card border border-om-line rounded-om text-center py-16">
                        <svg className="mx-auto h-16 w-16 text-om-faint mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p className="text-om-muted text-lg font-medium">{__('No modules installed')}</p>
                        <p className="text-om-faint text-sm mt-1">
                            <Link href="/admin/modules/install" className="text-om-accent hover:underline">
                                {__('Install a module from a ZIP file')}
                            </Link>
                            {' '}{__('or place the module folder in')}{' '}
                            <code className="bg-om-chip text-om-ink px-1 rounded-om-sm font-mono">modules/</code>
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {modules.map((module) => (
                            <div
                                key={module.name}
                                className={`bg-om-card border rounded-om p-4 flex flex-col gap-4 ${module.enabled ? 'border-l-[3px] border-l-om-accent border-y-om-line border-r-om-line' : 'border-om-line'}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <h3 className="text-[14px] font-semibold text-om-ink">
                                                {module.display_name ?? module.name}
                                            </h3>
                                            <span className="font-mono text-[11px] text-om-faint">
                                                v{module.version ?? '?'}
                                            </span>
                                            {module.enabled ? (
                                                <StatusPill status="running" label={__('Enabled')} pulse={false} />
                                            ) : (
                                                <StatusPill status="pending" label={__('Disabled')} />
                                            )}
                                            {module.has_error && (
                                                <StatusPill status="blocked" label={__('Provider Error')} />
                                            )}
                                        </div>
                                        {module.author && (
                                            <p className="text-[11px] text-om-faint mt-0.5">
                                                {__('by')}{' '}
                                                {module.homepage ? (
                                                    <a href={module.homepage} target="_blank" rel="noopener noreferrer" className="hover:underline">
                                                        {module.author}
                                                    </a>
                                                ) : module.author}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <p className="text-sm text-om-muted flex-1">
                                    {module.description ?? __('No description.')}
                                </p>

                                {module.hooks && module.hooks.length > 0 && (
                                    <div>
                                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5">{__('Used hooks')}</p>
                                        <div className="flex flex-wrap gap-1">
                                            {module.hooks.map((hook) => (
                                                <span
                                                    key={hook}
                                                    className="font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-om-sm"
                                                >
                                                    {hook}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex gap-2 pt-3 border-t border-om-line2">
                                    {module.enabled ? (
                                        <Button
                                            variant="secondary"
                                            onClick={() => postForm(`/admin/modules/${module.name}/disable`)}
                                        >
                                            {__('Disable')}
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="primary"
                                            onClick={() => postForm(`/admin/modules/${module.name}/enable`)}
                                        >
                                            {__('Enable')}
                                        </Button>
                                    )}

                                    {module.name !== 'ExampleHooks' && (
                                        <Button
                                            variant="secondary"
                                            onClick={() => setToUninstall({ name: module.name, displayName: module.display_name ?? module.name })}
                                            className="text-om-blocked hover:text-om-blocked"
                                        >
                                            {__('Uninstall')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={toUninstall !== null}
                onClose={() => setToUninstall(null)}
                onConfirm={confirmDestroy}
                title={toUninstall ? __('Uninstall module :name? Files will be removed.', { name: toUninstall.displayName }) : ''}
                confirmLabel={__('Uninstall')}
                cancelLabel={__('Cancel')}
                destructive
            />
        </>
    );
}

ModulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
