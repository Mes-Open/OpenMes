import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { unitAliasFields, unitAliasInitial } from './fields';
import { __ } from '../../../lib/i18n';

/**
 * Settings → Units: the names ERPs use for a unit and the unit OpenMES stores.
 * ERP imports translate through this list; a stock or lot row whose unit does
 * not match the item's is rejected rather than booked in the wrong unit.
 */
export default function UnitAliasesIndex() {
    const drawer = useResourceDrawer();
    const { units = [] } = usePage().props;

    const columns = [
        { key: 'alias', label: __('Alias'), className: 'font-mono text-om-ink', filter: 'text' },
        { key: 'unit', label: __('Canonical unit'), className: 'font-mono font-medium text-om-ink', filter: 'text' },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete alias ":alias"?', { alias: r.alias }),
                body: __('ERP imports will no longer translate this name.'),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/unit-aliases/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Unit Aliases')} />
            <ResourceTable
                shape="unit_aliases"
                title={__('Unit Aliases')}
                subtitle={(
                    <span className="text-[13px] text-om-muted">
                        {__('ERP imports translate these names to the unit OpenMES uses. Unknown units are saved as sent and reported as warnings.')}
                    </span>
                )}
                createHref="/admin/unit-aliases/create"
                onCreate={drawer.create}
                createLabel={__('New Alias')}
                columns={columns}
                orderBy="alias"
                actions={actions}
                emptyText={__('No unit aliases yet — ERP units are saved exactly as sent.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/unit-aliases"
                fields={unitAliasFields(units)}
                initial={unitAliasInitial}
                title={{ create: __('New Unit Alias'), edit: __('Edit Unit Alias') }}
            />
        </>
    );
}

UnitAliasesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
