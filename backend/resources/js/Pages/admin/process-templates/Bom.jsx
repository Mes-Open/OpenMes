import { useMemo, useState } from 'react';
import { __ } from '../../../lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, Modal, SegmentedControl } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

const TYPE_COLORS = {
    raw_material:  'bg-om-downtime-bg text-om-downtime',
    semi_finished: 'bg-om-chip text-om-accent',
    packaging:     'bg-om-chip text-om-ink',
};

function typeColorClass(code) {
    return TYPE_COLORS[code] ?? 'bg-om-chip text-om-ink';
}

/**
 * Add/edit a BOM line. Rendered inside the page's drawer rather than as a card
 * above the table: the list is the page, and a form that pushes it down moves
 * the row you were looking at every time you open one.
 */
function MaterialForm({ productType, processTemplate, materials, productTypes = [], steps, item, onCancel, onSaved }) {
    const isEdit = !!item;
    const form = useForm({
        component_kind: item ? (item.component_kind ?? 'material') : 'material',
        material_id: item ? String(item.material_id ?? '') : '',
        product_type_id: item ? String(item.product_type_id ?? '') : '',
        quantity_per_unit: item ? String(item.quantity_per_unit ?? '') : '',
        template_step_id: item && item.template_step_id != null ? String(item.template_step_id) : '',
        scrap_percentage: item ? String(item.scrap_percentage ?? '0') : '0',
        consumed_at: item ? (item.consumed_at ?? 'start') : 'start',
        notes: item ? (item.notes ?? '') : '',
    });

    const { data, setData, errors, processing } = form;

    const isProductType = data.component_kind === 'product_type';
    const selectedMaterial = isEdit
        ? null
        : materials.find((m) => String(m.id) === String(data.material_id));
    const selectedProductType = isEdit
        ? null
        : productTypes.find((p) => String(p.id) === String(data.product_type_id));
    const unit = isEdit
        ? item.unit_of_measure
        : (isProductType ? selectedProductType?.unit_of_measure : selectedMaterial?.unit_of_measure);

    // When a material with a default scrap % is picked, pre-fill it (only while
    // the field still holds the untouched default) and surface that it was auto-set.
    const onMaterialChange = (id) => {
        setData('material_id', id);
        const m = materials.find((x) => String(x.id) === String(id));
        if (m && m.default_scrap_percentage != null && (data.scrap_percentage === '' || data.scrap_percentage === '0')) {
            setData('scrap_percentage', String(m.default_scrap_percentage));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        const base = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom`;
        if (isEdit) {
            form.put(`${base}/${item.id}`, { onSuccess: onSaved });
        } else {
            form.post(base, { onSuccess: onSaved });
        }
    };

    return (
        // Fills the drawer's scroller so the sticky footer below sits on the
        // panel's bottom edge rather than floating under a short form.
        <form onSubmit={submit} className="flex min-h-full flex-col">
            {!isEdit && (
                // Switching kind clears the other side's id, so a half-picked
                // material can't be submitted as a product-type line.
                <SegmentedControl
                    className="mb-4"
                    label={__('Component kind')}
                    value={data.component_kind}
                    onChange={(kind) => setData((d) => ({
                        ...d,
                        component_kind: kind,
                        material_id: kind === 'material' ? d.material_id : '',
                        product_type_id: kind === 'product_type' ? d.product_type_id : '',
                    }))}
                    options={[
                        { value: 'material', label: __('Material') },
                        { value: 'product_type', label: __('Product type') },
                    ]}
                />
            )}
            {/* One column: the drawer is ~560px, and a two-up grid there leaves
                every dropdown too narrow to read a "CODE - Name (unit, type)"
                option in. */}
            <div className="grid flex-1 auto-rows-min grid-cols-1 gap-4">
                {isEdit ? (
                    <div>
                        <div className="block text-sm font-medium text-om-muted mb-1">
                            {item.component_kind === 'product_type' ? __("Product type") : __("Material")}
                        </div>
                        <div className="form-input w-full bg-om-panel text-om-muted">
                            {item.component_code ? `${item.component_code} - ` : ''}{item.component_name}
                        </div>
                    </div>
                ) : isProductType ? (
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            {__("Product type")} <span className="text-om-blocked">*</span>
                        </label>
                        <Dropdown
                            value={data.product_type_id == null ? '' : String(data.product_type_id)}
                            onChange={(v) => setData('product_type_id', v)}
                            placeholder={__("Select product type…")}
                            options={productTypes.map((p) => ({
                                value: String(p.id),
                                label: `${p.code} - ${p.name}`,
                            }))}
                            className="w-full"
                        />
                        <p className="mt-1 text-xs text-om-faint">
                            {__("Add a manufactured product type as a sub-assembly component.")}
                        </p>
                        {errors.product_type_id && (
                            <p className="mt-1 text-sm text-om-blocked">{errors.product_type_id}</p>
                        )}
                    </div>
                ) : (
                    <div>
                        <div className="block text-sm font-medium text-om-muted mb-1">
                            {__("Material")} <span className="text-om-blocked">*</span>
                        </div>
                        <Dropdown
                            aria-label={__("Material")}
                            value={data.material_id == null ? '' : String(data.material_id)}
                            onChange={(v) => onMaterialChange(v)}
                            placeholder="Select material..."
                            options={materials.map((m) => ({
                                value: String(m.id),
                                label: `${m.code} - ${m.name} (${m.unit_of_measure ? `${m.unit_of_measure}, ` : ''}${m.material_type_name ?? __('No type')})`,
                            }))}
                            className="w-full"
                        />
                        {errors.material_id && (
                            <p className="mt-1 text-sm text-om-blocked">{errors.material_id}</p>
                        )}
                    </div>
                )}

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__("Quantity per Unit")}{unit ? ` (${unit})` : ''} <span className="text-om-blocked">*</span>
                    </div>
                    <input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        required
                        value={data.quantity_per_unit}
                        onChange={(e) => setData('quantity_per_unit', e.target.value)}
                        className={`form-input w-full${errors.quantity_per_unit ? ' border-om-blocked' : ''}`}
                    />
                    <p className="mt-1 text-xs text-om-faint">
                        {__("How much of this material is needed per one finished product unit.")}
                    </p>
                    {errors.quantity_per_unit && (
                        <p className="mt-1 text-sm text-om-blocked">{errors.quantity_per_unit}</p>
                    )}
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__("Step (optional)")}
                    </div>
                    <Dropdown
                        aria-label={__("Step (optional)")}
                        value={data.template_step_id == null ? '' : String(data.template_step_id)}
                        onChange={(v) => setData('template_step_id', v)}
                        options={[
                            { value: '', label: __('All steps / general') },
                            ...steps.map((s) => ({
                                value: String(s.id),
                                label: `#${s.step_number} - ${s.name}`,
                            })),
                        ]}
                        className="w-full"
                    />
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__("Scrap %")}</div>
                    <input
                        aria-label={__("Scrap %")}
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={data.scrap_percentage}
                        onChange={(e) => setData('scrap_percentage', e.target.value)}
                        className="form-input w-full"
                    />
                    {selectedMaterial?.default_scrap_percentage != null && (
                        <p className="mt-1 text-xs text-om-faint">
                            {__("Pre-filled from the material default (:percentage%); adjust if needed.", { percentage: selectedMaterial.default_scrap_percentage })}
                        </p>
                    )}
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__("Consumed At")}</div>
                    <Dropdown
                        aria-label={__("Consumed At")}
                        value={data.consumed_at == null ? '' : String(data.consumed_at)}
                        onChange={(v) => setData('consumed_at', v)}
                        options={[
                            { value: 'start', label: __('Start of step') },
                            { value: 'during', label: __('During step') },
                            { value: 'end', label: __('End of step') },
                        ]}
                        className="w-full"
                    />
                </div>

                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__("Notes")}</div>
                    <input
                        aria-label={__("Notes")}
                        type="text"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder={__("Optional notes")}
                        className="form-input w-full"
                    />
                </div>
            </div>

            {/* Sticks to the bottom of the drawer's own scroller and bleeds to
                its padding edges — the hairline-over-panel footer ResourceForm
                draws in the work-order drawer, so both read the same. */}
            <div className="sticky bottom-0 -mx-[18px] -mb-4 mt-6 flex items-center gap-3 border-t border-om-line2 bg-om-panel px-[18px] py-[14px]">
                <Button type="submit" variant="primary" loading={processing}>
                    {isEdit
                        ? (processing ? __("Saving…") : __("Save Changes"))
                        : (processing ? __("Adding…") : __("Add to BOM"))}
                </Button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                >
                    {__("Cancel")}
                </button>
            </div>
        </form>
    );
}

export default function ProcessTemplatesBom() {
    const { productType, processTemplate, bomItems = [], materials = [], productTypes = [], steps = [] } = usePage().props;

    // `null` = closed, `'new'` = the add drawer, a row = editing that line.
    const [editing, setEditing] = useState(null);
    // Bumped on a finished save, so the retained form (see keepMounted below)
    // starts empty next time rather than showing what was just submitted.
    const [formRun, setFormRun] = useState(0);
    const isOpen = editing !== null;
    const item = editing === 'new' ? null : editing;

    const templateHref = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`;

    const remove = (row) => router.delete(`${templateHref}/bom/${row.id}`, { preserveScroll: true });

    const columns = useMemo(() => [
        {
            key: 'component_name',
            label: 'Component',
            flex: true,
            render: (row) => (
                <>
                    <div className="text-sm font-medium text-om-ink">{row.component_name}</div>
                    <div className="text-xs text-om-muted font-mono">{row.component_code}</div>
                </>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            // The cell shows one of two things — a material's type, or the fact
            // that the line is a sub-assembly — so search and the filter dropdown
            // need that same string, not the null `material_type_name` a
            // product-type line carries.
            value: (row) => (row.component_kind === 'product_type' ? __('Product type') : row.material_type_name),
            render: (row) => (
                row.component_kind === 'product_type' ? (
                    <span className="px-2 py-1 rounded-full text-xs font-medium bg-om-accent/10 text-om-accent">
                        {__("Product type")}
                    </span>
                ) : (
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${typeColorClass(row.material_type_code)}`}>
                        {row.material_type_name}
                    </span>
                )
            ),
        },
        {
            key: 'step',
            label: 'Step',
            value: (row) => (row.step_number != null ? `#${row.step_number} ${row.step_name}` : ''),
            render: (row) => (
                <span className="text-sm text-om-muted">
                    {row.step_number != null
                        ? `#${row.step_number} ${row.step_name}`
                        : <span className="text-om-faint">{__("General")}</span>}
                </span>
            ),
        },
        {
            key: 'quantity_per_unit',
            label: 'Qty/Unit',
            align: 'right',
            render: (row) => (
                <span className="text-sm font-mono">{row.quantity_per_unit} {row.unit_of_measure}</span>
            ),
        },
        {
            key: 'scrap_percentage',
            label: 'Scrap %',
            align: 'right',
            render: (row) => <span className="text-sm">{row.scrap_percentage}%</span>,
        },
        {
            key: 'consumed_at',
            label: 'Consumed At',
            render: (row) => <span className="text-sm text-om-muted capitalize">{row.consumed_at}</span>,
        },
        {
            key: 'tracking_type',
            label: 'Tracking',
            render: (row) => <span className="text-sm text-om-muted capitalize">{row.tracking_type}</span>,
        },
    ], []);

    // The work-order list's two-slot rail: the row's obvious next step, then
    // everything else behind the menu. Editing a line is that step here; Remove
    // sits in the menu behind a divider so it isn't one misclick from Edit.
    const actionSlots = [
        {
            key: 'primary',
            width: 96,
            resolve: (row) => ({
                label: __('Edit'),
                icon: 'edit',
                variant: 'secondary',
                onClick: () => setEditing(row),
            }),
        },
        {
            key: 'more',
            width: 34,
            label: __('More actions'),
            resolve: (row) => ({
                label: __('More actions'),
                menu: [
                    {
                        key: 'delete',
                        label: __('Remove'),
                        icon: 'delete',
                        destructive: true,
                        confirm: {
                            title: __('Remove this component from BOM?'),
                            confirmLabel: __('Remove'),
                        },
                        onSelect: () => remove(row),
                    },
                ],
            }),
        },
    ];

    return (
        <>
            <Head title={`BOM - ${processTemplate.name}`} />

            <ResourceTable
                // Not a synced shape: a BOM belongs to one process template and
                // nothing broadcasts it, so the rows come from this page's props.
                rows={bomItems}
                title={__('Bill of Materials')}
                titleIcon="layers"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/admin/dashboard', icon: 'layout-dashboard' },
                    { label: 'Product Types', href: '/admin/product-types', icon: 'package' },
                    // Replaces the old "Back to Template" link above the heading.
                    { label: processTemplate.name, href: templateHref, icon: 'workflow' },
                ]}
                subtitle={(
                    <span className="text-[13px] text-om-muted">
                        v{processTemplate.version} &bull; {productType.name}
                    </span>
                )}
                columns={columns}
                orderBy="component_name"
                actionSlots={actionSlots}
                onCreate={() => setEditing('new')}
                createLabel={__('Add Component')}
                emptyText={__('No materials in BOM yet.')}
            />

            <Modal
                open={isOpen}
                onClose={() => setEditing(null)}
                title={item ? `${__('Edit BOM Item')} - ${item.component_name}` : __('Add Component to BOM')}
                closeLabel={__('Close')}
                side="right"
                width={560}
                // Closing without saving keeps what was typed — the panel is
                // hidden, not unmounted. Safe with the key below: what's retained
                // belongs to the row it was typed for.
                keepMounted
            >
                {/* Keyed per line: one Modal serves both add and edit, and without
                    this the previous row's typed values would carry into the next.
                    The run counter is the other half — a finished save is the only
                    thing that clears the retained values. */}
                <MaterialForm
                    key={`${item ? item.id : 'new'}:${formRun}`}
                    productType={productType}
                    processTemplate={processTemplate}
                    materials={materials}
                    productTypes={productTypes}
                    steps={steps}
                    item={item}
                    onCancel={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        setFormRun((n) => n + 1);
                    }}
                />
            </Modal>
        </>
    );
}

ProcessTemplatesBom.layout = (page) => <AppLayout>{page}</AppLayout>;
