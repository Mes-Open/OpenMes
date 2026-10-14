import { useContext } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout, { PageTitleContext } from '../../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../../components/ResourceFormDrawer';
import { changeoverRuleFields, changeoverRuleInitial, changeoverTrail } from './fields';
import { __ } from '../../../../lib/i18n';

export default function ChangeoverRulesIndex() {
    const drawer = useResourceDrawer();
    // The sidebar knows this only as "Schedule"; the list adds where it really sits.
    const { navTrail = [] } = useContext(PageTitleContext);
    const props = usePage().props;
    const { attributes = [], workstationTypes = [], workstations = [] } = props;

    const attributeLabels = Object.fromEntries(attributes.map((a) => [a.value, a.label]));
    const typeNames = Object.fromEntries(workstationTypes.map((t) => [t.id, t.name]));
    const stationNames = Object.fromEntries(workstations.map((w) => [w.id, w.name]));
    const any = __('Any');

    const scope = (r) => {
        if (r.workstation_id) return stationNames[r.workstation_id] ?? `#${r.workstation_id}`;
        if (r.workstation_type_id) return typeNames[r.workstation_type_id] ?? `#${r.workstation_type_id}`;
        return __('All resources');
    };

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'attribute', label: __('Compare by'), className: 'text-om-muted', value: (r) => attributeLabels[r.attribute] ?? r.attribute, render: (r) => attributeLabels[r.attribute] ?? r.attribute },
        {
            key: 'transition',
            label: __('Transition'),
            value: (r) => `${r.from_value || any} → ${r.to_value || any}`,
            render: (r) => (
                <span className="font-mono text-xs">
                    {r.from_value || <em className="text-om-muted not-italic">{any}</em>}
                    {' → '}
                    {r.to_value || <em className="text-om-muted not-italic">{any}</em>}
                </span>
            ),
        },
        { key: 'setup_minutes', label: __('Setup time (min)'), align: 'right' },
        { key: 'scope', label: __('Applies to'), className: 'text-om-muted', value: scope, render: scope },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete changeover rule ":name"?', { name: r.name }),
                body: __('Schedules generated from now on fall back to the standard setup time for this transition.'),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/schedule/changeover-rules/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Changeover Rules')} />
            <ResourceTable
                shape="changeover_rules"
                title={__('Changeover Rules')}
                titleIcon="repeat"
                breadcrumbs={[...navTrail, ...changeoverTrail({ withList: false })]}
                createHref="/admin/schedule/changeover-rules/create"
                onCreate={drawer.create}
                createLabel={__('New Rule')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No changeover rules — every job uses its routing’s standard setup time.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/schedule/changeover-rules"
                fields={changeoverRuleFields(props)}
                initial={changeoverRuleInitial}
                title={{ create: __('New Changeover Rule'), edit: __('Edit Changeover Rule') }}
            />
        </>
    );
}

ChangeoverRulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
