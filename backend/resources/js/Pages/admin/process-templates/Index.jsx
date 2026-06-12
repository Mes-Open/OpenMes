import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function ProcessTemplatesIndex() {
    const { productType, templates = [] } = usePage().props;

    const handleToggleActive = (template) => {
        router.post(
            `/admin/product-types/${productType.id}/process-templates/${template.id}/toggle-active`,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (template) => {
        if (!confirm(__('Are you sure you want to delete this template?'))) return;
        router.delete(
            `/admin/product-types/${productType.id}/process-templates/${template.id}`,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={__('Process Templates - :name', { name: productType.name })} />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}`}
                        className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to :name', { name: productType.name })}
                    </a>

                    <div className="flex items-center justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-3xl font-bold text-gray-800">{__('Process Templates')}</h1>
                            </div>
                            <p className="text-sm text-gray-600 mt-1">{productType.name}</p>
                        </div>
                        <a
                            href={`/admin/product-types/${productType.id}/process-templates/create`}
                            className="btn-touch btn-primary"
                        >
                            <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {__('Create Template')}
                        </a>
                    </div>
                </div>

                {templates.length > 0 ? (
                    <div className="space-y-4">
                        {templates.map((template) => (
                            <div key={template.id} className="card hover:shadow-lg transition-shadow">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <h3 className="text-xl font-bold text-gray-800">{template.name}</h3>
                                            {template.is_active ? (
                                                <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                                    {__('Active')}
                                                </span>
                                            ) : (
                                                <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">
                                                    {__('Inactive')}
                                                </span>
                                            )}
                                            <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                                v{template.version}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm text-gray-600">
                                            <span className="flex items-center gap-1">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                {__(':count steps', { count: template.steps_count })}
                                            </span>
                                            <span>{__('Created:')} {template.created_at}</span>
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <a
                                            href={`/admin/product-types/${productType.id}/process-templates/${template.id}`}
                                            className="btn-touch btn-secondary text-sm"
                                        >
                                            <svg className="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {__('View Steps')}
                                        </a>

                                        <a
                                            href={`/admin/product-types/${productType.id}/process-templates/${template.id}/edit`}
                                            className="text-blue-600 hover:text-blue-800 p-2"
                                            title={__('Edit')}
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        <button
                                            type="button"
                                            onClick={() => handleToggleActive(template)}
                                            className="text-gray-600 hover:text-gray-800 p-2"
                                            title={template.is_active ? __('Deactivate') : __('Activate')}
                                        >
                                            {template.is_active ? (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            )}
                                        </button>

                                        {template.steps_count === 0 ? (
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(template)}
                                                className="text-red-600 hover:text-red-800 p-2"
                                                title={__('Delete')}
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        ) : (
                                            <span className="text-gray-300 p-2" title={__('Cannot delete - has steps')}>
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="card text-center py-12">
                        <svg className="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p className="text-lg font-medium text-gray-700">{__('No process templates yet')}</p>
                        <p className="text-sm text-gray-500 mt-1 mb-4">
                            {__('Create a template to define how this product is manufactured.')}
                        </p>
                        <a
                            href={`/admin/product-types/${productType.id}/process-templates/create`}
                            className="inline-block btn-touch btn-primary"
                        >
                            <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {__('Create Template')}
                        </a>
                    </div>
                )}
            </div>
        </>
    );
}

ProcessTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
