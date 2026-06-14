import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * Admin Trash — soft-deleted rows across every domain entity, with who
 * deleted them and a one-click restore (restore cascades to the children
 * deleted together with the row).
 */
export default function TrashIndex() {
    const { items = [], counts = {}, selectedType = null } = usePage().props;

    const typeLabel = (type) =>
        type.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());

    const restore = (item) => {
        if (!window.confirm(__('Restore this item (and records deleted with it)?'))) return;
        router.post(`/admin/trash/${item.type}/${item.id}/restore`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={__('Trash')} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{__('Trash')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {__('Deleted items are kept here and can be restored. Restoring also brings back records deleted together with the item.')}
                        </p>
                    </div>
                    <select
                        value={selectedType ?? ''}
                        onChange={(e) => router.get('/admin/trash', e.target.value ? { type: e.target.value } : {}, { preserveState: true })}
                        className="form-input w-full sm:w-72"
                    >
                        <option value="">{__('All types')}</option>
                        {Object.entries(counts).map(([type, count]) => (
                            <option key={type} value={type}>
                                {typeLabel(type)} ({count})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {items.length === 0 ? (
                        <div className="p-12 text-center text-gray-400 dark:text-gray-500 text-sm">
                            {__('Trash is empty.')}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-900/40">
                                    <tr className="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        <th className="px-4 py-3">{__('Type')}</th>
                                        <th className="px-4 py-3">{__('Item')}</th>
                                        <th className="px-4 py-3">{__('Deleted by')}</th>
                                        <th className="px-4 py-3">{__('Deleted at')}</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {items.map((item) => (
                                        <tr key={`${item.type}-${item.id}`}>
                                            <td className="px-4 py-2.5 text-gray-500 dark:text-gray-400">{typeLabel(item.type)}</td>
                                            <td className="px-4 py-2.5 font-mono font-medium text-gray-800 dark:text-gray-100">{item.label}</td>
                                            <td className="px-4 py-2.5 text-gray-600 dark:text-gray-300">{item.deleted_by ?? '—'}</td>
                                            <td className="px-4 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">{formatDateTime(item.deleted_at)}</td>
                                            <td className="px-4 py-2.5 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => restore(item)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700"
                                                >
                                                    {__('Restore')}
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

TrashIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
