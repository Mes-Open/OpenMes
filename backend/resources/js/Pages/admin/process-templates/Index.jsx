import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, IconButton, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function ProcessTemplatesIndex() {
    const { productType, templates = [] } = usePage().props;

    const [toDelete, setToDelete] = useState(null);

    const handleToggleActive = (template) => {
        router.post(
            `/admin/product-types/${productType.id}/process-templates/${template.id}/toggle-active`,
            {},
            { preserveScroll: true },
        );
    };

    const confirmDestroy = () => {
        if (toDelete) {
            router.delete(
                `/admin/product-types/${productType.id}/process-templates/${toDelete.id}`,
                { preserveScroll: true },
            );
        }
        setToDelete(null);
    };

    return (
        <>
            <Head title={__('Process Templates - :name', { name: productType.name })} />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <Link
                        href={`/admin/product-types/${productType.id}`}
                        className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to :name', { name: productType.name })}
                    </Link>

                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">
                                {__('Process Templates')}
                            </h1>
                            <p className="text-sm text-om-muted mt-1">{productType.name}</p>
                        </div>
                        <Button
                            variant="accent"
                            onClick={() =>
                                router.visit(`/admin/product-types/${productType.id}/process-templates/create`)
                            }
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            {__('Create Template')}
                        </Button>
                    </div>
                </div>

                {templates.length > 0 ? (
                    <div className="space-y-4">
                        {templates.map((template) => (
                            <div
                                key={template.id}
                                className="bg-om-card border border-om-line rounded-om p-5"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <h3 className="text-[15px] font-semibold text-om-ink">{template.name}</h3>
                                            {template.is_active ? (
                                                <StatusPill status="running" label={__('Active')} />
                                            ) : (
                                                <StatusPill status="pending" label={__('Inactive')} />
                                            )}
                                            <span className="px-3 py-1 bg-om-chip text-om-accent rounded-full text-sm font-medium">
                                                v{template.version}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm text-om-muted">
                                            <span className="flex items-center gap-1">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                {__(':count steps', { count: template.steps_count })}
                                            </span>
                                            <span>{__('Created:')} {template.created_at}</span>
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                router.visit(
                                                    `/admin/product-types/${productType.id}/process-templates/${template.id}`,
                                                )
                                            }
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {__('View Steps')}
                                        </Button>

                                        <IconButton
                                            onClick={() =>
                                                router.visit(
                                                    `/admin/product-types/${productType.id}/process-templates/${template.id}/edit`,
                                                )
                                            }
                                            title={__('Edit')}
                                            aria-label={__('Edit')}
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </IconButton>

                                        <IconButton
                                            onClick={() => handleToggleActive(template)}
                                            title={template.is_active ? __('Deactivate') : __('Activate')}
                                            aria-label={template.is_active ? __('Deactivate') : __('Activate')}
                                        >
                                            {template.is_active ? (
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            ) : (
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            )}
                                        </IconButton>

                                        {template.steps_count === 0 ? (
                                            <IconButton
                                                variant="danger"
                                                onClick={() => setToDelete(template)}
                                                title={__('Delete')}
                                                aria-label={__('Delete')}
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </IconButton>
                                        ) : (
                                            <span
                                                className="inline-flex size-[38px] items-center justify-center text-om-faintest"
                                                title={__('Cannot delete - has steps')}
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-om-card border border-om-line rounded-om text-center py-12">
                        <svg className="mx-auto h-16 w-16 text-om-faintest mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p className="text-[15px] font-semibold text-om-ink">{__('No process templates yet')}</p>
                        <p className="text-sm text-om-muted mt-1 mb-4">
                            {__('Create a template to define how this product is manufactured.')}
                        </p>
                        <Button
                            variant="accent"
                            onClick={() =>
                                router.visit(`/admin/product-types/${productType.id}/process-templates/create`)
                            }
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            {__('Create Template')}
                        </Button>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={toDelete !== null}
                onClose={() => setToDelete(null)}
                onConfirm={confirmDestroy}
                title={__('Are you sure you want to delete this template?')}
                confirmLabel={__('Delete')}
                cancelLabel={__('Cancel')}
                destructive
            />
        </>
    );
}

ProcessTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
