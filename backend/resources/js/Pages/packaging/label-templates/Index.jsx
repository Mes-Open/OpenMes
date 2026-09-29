import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Badge } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import LabelTemplateForm, { labelTemplateInitial } from './Form';
import LabelPreviewModal from '../../../components/LabelPreviewModal';
import { __ } from '../../../lib/i18n';

export default function LabelTemplatesIndex() {
    const drawer = useResourceDrawer();
    const [preview, setPreview] = useState(null); // template whose sample label is shown
    const { typeLabels = {}, editTemplate = null, openCreate = false, types, sizes, barcodeFormats, availableFields, defaultFieldsByType = {} } = usePage().props;
    const formReady = types !== undefined && sizes !== undefined && barcodeFormats !== undefined && availableFields !== undefined;

    // Deep links (/create, /{id}/edit) arrive as ?create=1 / ?edit={id} with the
    // record attached: open the drawer on it, the same way a row's Edit does.
    useEffect(() => {
        if (editTemplate) drawer.edit(editTemplate);
        else if (openCreate) drawer.create();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editTemplate?.id, openCreate]);

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'type', label: __('Type'), className: 'text-om-muted', value: (r) => __(typeLabels[r.type] ?? r.type), render: (r) => __(typeLabels[r.type] ?? r.type) },
        { key: 'size', label: __('Size'), className: 'text-om-muted' },
        { key: 'barcode_format', label: __('Barcode'), className: 'font-mono text-om-muted' },
        {
            key: 'is_default',
            label: __('Default'),
            // One template per type prints when nothing names a template; say so in words.
            value: (r) => (r.is_default ? 'yes' : 'no'),
            filter: 'select',
            options: [{ value: 'yes', label: __('Default for its type') }, { value: 'no', label: __('Not default') }],
            render: (r) => (r.is_default
                ? <Badge variant="outline" title={__('Printed when nothing names a template of this type')}>{__('Default for its type')}</Badge>
                : <span className="text-om-faint">—</span>),
        },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        { label: __('Preview'), icon: 'open', onClick: () => setPreview(r) },
        ...(r.is_default ? [] : [{ label: __('Make default'), onClick: () => router.post(`/packaging/label-templates/${r.id}/set-default`, {}, { preserveScroll: true }) }]),
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: { title: __('Delete label template ":name"?', { name: r.name }), confirmLabel: __('Delete') },
            onClick: () => router.delete(`/packaging/label-templates/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Label Templates')} />
            <ResourceTable
                shape="label_templates"
                title={__('Label Templates')}
                createHref="/packaging/label-templates/create"
                onCreate={drawer.create}
                createLabel={__('New Template')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No label templates yet.')}
            />

            <LabelPreviewModal
                open={preview != null}
                onClose={() => setPreview(null)}
                title={__('Preview')}
                subtitle={preview ? `${preview.name} · ${preview.size} mm · ${String(preview.barcode_format ?? '').toUpperCase()}` : ''}
                pdfUrl={preview ? `/packaging/label-templates/${preview.id}/preview` : null}
            />

            <ResourceFormDrawer
                {...drawer.props}
                ensure={['types', 'sizes', 'barcodeFormats', 'availableFields', 'defaultFieldsByType', 'fieldsByType']}
                ready={formReady}
                title={{ create: __('New Label Template'), edit: __('Edit Label Template') }}
                render={({ editing, record, finish, dismiss }) => (
                    <LabelTemplateForm
                        action={editing ? `/packaging/label-templates/${record.id}` : '/packaging/label-templates'}
                        method={editing ? 'put' : 'post'}
                        initial={{ ...labelTemplateInitial(editing ? record : null, defaultFieldsByType), stay: 1 }}
                        submitLabel={editing ? __('Save Changes') : __('Create')}
                        onSuccess={finish}
                        onCancel={dismiss}
                    />
                )}
            />
        </>
    );
}

LabelTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
