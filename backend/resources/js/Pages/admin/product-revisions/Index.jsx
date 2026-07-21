import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { LIFECYCLE_BADGE_STYLES, lifecycleLabel } from './fields';
import { __ } from '../../../lib/i18n';

export default function ProductRevisionsIndex() {
    const { productTypes = [], processTemplates = [], counts = {} } = usePage().props;

    const typeById = Object.fromEntries(productTypes.map((p) => [String(p.id), p]));
    const templateById = Object.fromEntries(processTemplates.map((t) => [String(t.id), t]));

    const columns = [
        {
            key: 'product_type_id', label: __('Product Type'), className: 'text-om-ink',
            render: (r) => {
                const p = typeById[String(r.product_type_id)];
                return p ? (p.code ? `${p.code} — ${p.name}` : p.name) : '—';
            },
        },
        { key: 'revision_code', label: __('Revision'), className: 'font-mono font-medium text-om-ink' },
        {
            key: 'lifecycle_status', label: __('Lifecycle'),
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${LIFECYCLE_BADGE_STYLES[r.lifecycle_status] ?? 'bg-om-chip text-om-muted'}`}>
                    {lifecycleLabel(r.lifecycle_status)}
                </span>
            ),
        },
        {
            key: 'process_template_id', label: __('Process Template'), className: 'text-om-muted',
            render: (r) => {
                const t = templateById[String(r.process_template_id)];
                return t ? `${t.name} v${t.version}` : '—';
            },
        },
        { key: 'description', label: __('Description'), className: 'text-om-muted', render: (r) => r.description ?? '—' },
        { key: 'work_orders', label: __('Work orders'), align: 'right', render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => {
        const items = [];
        if (r.lifecycle_status === 'draft') {
            items.push({ label: __('Edit'), href: `/admin/product-revisions/${r.id}/edit` });
            items.push({
                label: __('Release'),
                onClick: () => {
                    if (confirm(__('Release revision ":code"? It becomes immutable and selectable for production.', { code: r.revision_code }))) {
                        router.post(`/admin/product-revisions/${r.id}/release`, {}, { preserveScroll: true });
                    }
                },
            });
        }
        if (r.lifecycle_status === 'released') {
            items.push({
                label: __('Mark obsolete'),
                onClick: () => {
                    if (confirm(__('Mark revision ":code" obsolete? It stays for history but is no longer selectable.', { code: r.revision_code }))) {
                        router.post(`/admin/product-revisions/${r.id}/obsolete`, {}, { preserveScroll: true });
                    }
                },
            });
        }
        items.push({
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            onClick: () => {
                if (confirm(__('Delete revision ":code"?', { code: r.revision_code }))) {
                    router.delete(`/admin/product-revisions/${r.id}`, { preserveScroll: true });
                }
            },
        });
        return items;
    };

    return (
        <>
            <Head title={__('Product Revisions')} />
            <ResourceTable
                shape="product_revisions"
                title={__('Product Revisions')}
                createHref="/admin/product-revisions/create"
                createLabel={__('+ New Revision')}
                columns={columns}
                orderBy="revision_code"
                actions={actions}
                emptyText={__('No product revisions yet.')}
            />
        </>
    );
}

ProductRevisionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
