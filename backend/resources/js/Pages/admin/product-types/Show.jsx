import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

const WO_STATUS_LABELS = {
    PENDING:     'Pending',
    ACCEPTED:    'Accepted',
    IN_PROGRESS: 'In Progress',
    BLOCKED:     'Blocked',
    PAUSED:      'Paused',
    DONE:        'Done',
    REJECTED:    'Rejected',
    CANCELLED:   'Cancelled',
};

const WO_STATUS_STYLES = {
    PENDING:     'bg-yellow-100 text-yellow-800',
    IN_PROGRESS: 'bg-blue-100 text-blue-800',
    COMPLETED:   'bg-green-100 text-green-800',
    BLOCKED:     'bg-red-100 text-red-800',
    DONE:        'bg-green-100 text-green-800',
    REJECTED:    'bg-red-200 text-red-800',
    CANCELLED:   'bg-gray-200 text-gray-600',
    ACCEPTED:    'bg-blue-100 text-blue-800',
    PAUSED:      'bg-orange-100 text-orange-800',
};

export default function ProductTypeShow({ productType, recentWorkOrders = [], customFields = [] }) {
    const templateCount = productType.process_templates?.length ?? 0;
    const workOrderCount = productType.work_order_count ?? recentWorkOrders.length;
    const totalWorkOrders = productType.total_work_order_count ?? workOrderCount;

    const handleToggleActive = () => {
        router.post(`/admin/product-types/${productType.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Product Type Details" />

            {/* Breadcrumbs */}
            <nav className="text-sm text-gray-500 mb-4 flex items-center gap-1">
                <Link href="/admin/dashboard" className="hover:underline">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/product-types" className="hover:underline">Product Types</Link>
                <span>/</span>
                <span className="text-gray-800">{productType.name}</span>
            </nav>

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <Link href="/admin/product-types" className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <h1 className="text-3xl font-bold text-gray-800">{productType.name}</h1>
                            {productType.is_active ? (
                                <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                            ) : (
                                <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Link
                                href={`/admin/product-types/${productType.id}/edit`}
                                className="btn-touch btn-secondary"
                            >
                                <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Product Type
                            </Link>
                            <button
                                type="button"
                                onClick={handleToggleActive}
                                className={`btn-touch ${productType.is_active ? 'btn-secondary' : 'btn-primary'}`}
                            >
                                {productType.is_active ? (
                                    <>
                                        <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Deactivate
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Activate
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                    <p className="text-sm text-gray-500 font-mono mt-1">{productType.code}</p>
                    {productType.description && (
                        <p className="text-gray-600 mt-2">{productType.description}</p>
                    )}
                    {productType.unit_of_measure && (
                        <p className="text-sm text-gray-600 mt-1">
                            Unit: <span className="font-medium">{productType.unit_of_measure}</span>
                        </p>
                    )}
                </div>

                {/* Stats cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div className="card">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm text-gray-600">Process Templates</p>
                                <p className="text-3xl font-bold text-blue-600">{templateCount}</p>
                            </div>
                            <div className="bg-blue-100 rounded-full p-3">
                                <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div className="card">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm text-gray-600">Work Orders</p>
                                <p className="text-3xl font-bold text-purple-600">{totalWorkOrders}</p>
                            </div>
                            <div className="bg-purple-100 rounded-full p-3">
                                <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={productType.custom_fields ?? {}} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Process Templates */}
                    <div className="card">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-xl font-bold text-gray-800">Process Templates</h2>
                            <div className="flex gap-2">
                                <Link
                                    href={`/admin/product-types/${productType.id}/process-templates`}
                                    className="btn-touch btn-secondary text-sm"
                                >
                                    View All
                                </Link>
                                <Link
                                    href={`/admin/product-types/${productType.id}/process-templates/create`}
                                    className="btn-touch btn-primary text-sm"
                                >
                                    <svg className="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    Create
                                </Link>
                            </div>
                        </div>

                        {productType.process_templates && productType.process_templates.length > 0 ? (
                            <div className="space-y-2">
                                {productType.process_templates.map((template) => (
                                    <Link
                                        key={template.id}
                                        href={`/admin/product-types/${productType.id}/process-templates/${template.id}`}
                                        className="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <p className="font-medium text-gray-800">{template.name}</p>
                                                    {template.is_active ? (
                                                        <span className="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                                    ) : (
                                                        <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                                    )}
                                                </div>
                                                <p className="text-xs text-gray-600">
                                                    Version {template.version} &bull; {template.steps?.length ?? 0} steps
                                                </p>
                                            </div>
                                            <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 bg-gray-50 rounded-lg">
                                <svg className="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p className="text-gray-600 mb-2">No process templates yet</p>
                                <p className="text-sm text-gray-500">Process templates define how this product is manufactured.</p>
                            </div>
                        )}
                    </div>

                    {/* Recent Work Orders */}
                    <div className="card">
                        <h2 className="text-xl font-bold text-gray-800 mb-4">Recent Work Orders</h2>
                        {recentWorkOrders.length > 0 ? (
                            <>
                                <div className="space-y-2">
                                    {recentWorkOrders.map((wo) => (
                                        <div key={wo.id} className="p-3 bg-gray-50 rounded-lg">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-gray-800">{wo.work_order_number}</p>
                                                    <p className="text-sm text-gray-600">{wo.product_name}</p>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Quantity: {wo.planned_qty} | {wo.created_at ? wo.created_at.substring(0, 16).replace('T', ' ') : '—'}
                                                    </p>
                                                </div>
                                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${WO_STATUS_STYLES[wo.status] ?? 'bg-gray-100 text-gray-800'}`}>
                                                    {WO_STATUS_LABELS[wo.status] ?? wo.status}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {totalWorkOrders > 10 && (
                                    <p className="text-sm text-gray-500 text-center mt-4">
                                        Showing 10 most recent of {totalWorkOrders} total work orders
                                    </p>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-8 bg-gray-50 rounded-lg">
                                <svg className="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <p className="text-gray-600">No work orders yet</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ProductTypeShow.layout = (page) => <AppLayout>{page}</AppLayout>;
