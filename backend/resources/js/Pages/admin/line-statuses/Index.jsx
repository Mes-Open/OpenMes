import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import AppLayout from '../../../layouts/AppLayout';
import { realtimeCollection } from '../../../lib/realtimeCollection';
import { __ } from '../../../lib/i18n';

/**
 * Global line statuses — inline-managed (no separate create/edit pages; the
 * controller exposes index/store/update/destroy). Rows live-sync via the
 * `line_statuses_global` collection; each row edits in place and writes through
 * to Laravel (non-optimistic — the committed change re-renders via the sync).
 */
export default function LineStatusesIndex() {
    return (
        <>
            <Head title={__('Line Statuses')} />
            <div className="max-w-4xl mx-auto">
                <h1 className="text-3xl font-bold text-gray-800 mb-2">{__('Line Statuses')}</h1>
                <p className="text-gray-500 text-sm mb-6">
                    {__('Global kanban statuses available to every production line.')}
                </p>
                <Editor />
            </div>
        </>
    );
}

LineStatusesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

function Editor() {
    const collection = useMemo(() => realtimeCollection('line_statuses_global'), []);
    const { data: rows } = useLiveQuery((q) =>
        q.from({ s: collection }).orderBy(({ s }) => s.sort_order, 'asc'),
    );

    return (
        <div className="bg-white rounded-lg shadow-sm overflow-hidden">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-gray-500 border-b bg-gray-50">
                        <th className="px-4 py-3">{__('Color')}</th>
                        <th className="px-4 py-3">{__('Name')}</th>
                        <th className="px-4 py-3 w-24">{__('Order')}</th>
                        <th className="px-4 py-3 w-24">{__('Default')}</th>
                        <th className="px-4 py-3 text-right">{__('Actions')}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((s) => (
                        <StatusRow key={s.id} status={s} />
                    ))}
                    <AddRow nextOrder={rows.length} />
                </tbody>
            </table>
        </div>
    );
}

function StatusRow({ status }) {
    const [name, setName] = useState(status.name);
    const [color, setColor] = useState(status.color);
    const [sortOrder, setSortOrder] = useState(status.sort_order ?? 0);
    const [isDefault, setIsDefault] = useState(!!status.is_default);
    const [saving, setSaving] = useState(false);

    const dirty =
        name !== status.name ||
        color !== status.color ||
        Number(sortOrder) !== (status.sort_order ?? 0) ||
        isDefault !== !!status.is_default;

    const save = () => {
        setSaving(true);
        router.put(
            `/admin/line-statuses/${status.id}`,
            { name, color, sort_order: sortOrder, is_default: isDefault },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const destroy = () => {
        if (confirm(__('Delete status ":name"?', { name: status.name }))) {
            router.delete(`/admin/line-statuses/${status.id}`, { preserveScroll: true });
        }
    };

    return (
        <tr className="border-b last:border-0">
            <td className="px-4 py-2">
                <input type="color" value={color} onChange={(e) => setColor(e.target.value)} className="h-8 w-12 rounded border border-gray-300 p-0.5" />
            </td>
            <td className="px-4 py-2">
                <input type="text" value={name} onChange={(e) => setName(e.target.value)} className="form-input w-full" />
            </td>
            <td className="px-4 py-2">
                <input type="number" value={sortOrder} onChange={(e) => setSortOrder(e.target.value)} className="form-input w-full" />
            </td>
            <td className="px-4 py-2 text-center">
                <input type="checkbox" checked={isDefault} onChange={(e) => setIsDefault(e.target.checked)} />
            </td>
            <td className="px-4 py-2">
                <div className="flex items-center justify-end gap-2">
                    <button
                        onClick={save}
                        disabled={!dirty || saving}
                        className="btn-touch btn-primary text-sm disabled:opacity-50"
                    >
                        {saving ? __('Saving…') : __('Save')}
                    </button>
                    <button
                        onClick={destroy}
                        className="p-1.5 rounded-md transition-colors text-red-600 hover:text-red-800"
                        title={__('Delete')}
                        aria-label={__('Delete')}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    );
}

function AddRow({ nextOrder }) {
    const [name, setName] = useState('');
    const [color, setColor] = useState('#3b82f6');
    const [adding, setAdding] = useState(false);

    const add = () => {
        if (!name.trim()) return;
        setAdding(true);
        router.post(
            '/admin/line-statuses',
            { name, color, sort_order: nextOrder, is_default: false },
            {
                preserveScroll: true,
                onSuccess: () => { setName(''); setColor('#3b82f6'); },
                onFinish: () => setAdding(false),
            },
        );
    };

    return (
        <tr className="bg-gray-50/50">
            <td className="px-4 py-2">
                <input type="color" value={color} onChange={(e) => setColor(e.target.value)} className="h-8 w-12 rounded border border-gray-300 p-0.5" />
            </td>
            <td className="px-4 py-2">
                <input type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder={__('New status name…')} className="form-input w-full" />
            </td>
            <td className="px-4 py-2 text-gray-400">{nextOrder}</td>
            <td />
            <td className="px-4 py-2 text-right">
                <button
                    onClick={add}
                    disabled={!name.trim() || adding}
                    className="bg-blue-600 text-white px-3 py-1.5 rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                    {adding ? __('Adding…') : __('Add')}
                </button>
            </td>
        </tr>
    );
}
