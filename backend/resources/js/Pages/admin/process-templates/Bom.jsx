import { useState, useMemo } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const TYPE_COLORS = {
    raw_material:  'bg-om-downtime-bg text-om-downtime',
    semi_finished: 'bg-om-chip text-om-accent',
    packaging:     'bg-om-chip text-om-ink',
};

function typeColorClass(code) {
    return TYPE_COLORS[code] ?? 'bg-om-chip text-om-ink';
}

function AddMaterialForm({ productType, processTemplate, materials, steps, onCancel }) {
    const form = useForm({
        material_id: '',
        quantity_per_unit: '',
        template_step_id: '',
        scrap_percentage: '0',
        consumed_at: 'start',
        notes: '',
    });

    const { data, setData, errors, processing } = form;

    const selectedMaterial = materials.find((m) => String(m.id) === String(data.material_id));
    const unit = selectedMaterial?.unit_of_measure;

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
        form.post(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom`,
            { onSuccess: onCancel },
        );
    };

    return (
        <div className="card mb-6" style={{ borderLeft: '4px solid #3b82f6' }}>
            <h3 className="text-lg font-semibold mb-4">Add Material to BOM</h3>
            <form onSubmit={submit}>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            Material <span className="text-om-blocked">*</span>
                        </label>
                        <Dropdown
                            value={data.material_id == null ? '' : String(data.material_id)}
                            onChange={(v) => onMaterialChange(v)}
                            placeholder="Select material..."
                            options={materials.map((m) => ({
                                value: String(m.id),
                                label: `${m.code} - ${m.name} (${m.unit_of_measure ? `${m.unit_of_measure}, ` : ''}${m.material_type_name})`,
                            }))}
                            className="w-full"
                        />
                        {errors.material_id && (
                            <p className="mt-1 text-sm text-om-blocked">{errors.material_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            Quantity per Unit{unit ? ` (${unit})` : ''} <span className="text-om-blocked">*</span>
                        </label>
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
                            How much of this material is needed per one finished product unit.
                        </p>
                        {errors.quantity_per_unit && (
                            <p className="mt-1 text-sm text-om-blocked">{errors.quantity_per_unit}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            Step (optional)
                        </label>
                        <Dropdown
                            value={data.template_step_id == null ? '' : String(data.template_step_id)}
                            onChange={(v) => setData('template_step_id', v)}
                            options={[
                                { value: '', label: 'All steps / general' },
                                ...steps.map((s) => ({
                                    value: String(s.id),
                                    label: `#${s.step_number} - ${s.name}`,
                                })),
                            ]}
                            className="w-full"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">Scrap %</label>
                        <input
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
                                Pre-filled from the material default ({selectedMaterial.default_scrap_percentage}%); adjust if needed.
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">Consumed At</label>
                        <Dropdown
                            value={data.consumed_at == null ? '' : String(data.consumed_at)}
                            onChange={(v) => setData('consumed_at', v)}
                            options={[
                                { value: 'start', label: 'Start of step' },
                                { value: 'during', label: 'During step' },
                                { value: 'end', label: 'End of step' },
                            ]}
                            className="w-full"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">Notes</label>
                        <input
                            type="text"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Optional notes"
                            className="form-input w-full"
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-4">
                    <button type="button" onClick={onCancel} className="btn-touch btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" disabled={processing} className="btn-touch btn-primary">
                        {processing ? 'Adding…' : 'Add to BOM'}
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function ProcessTemplatesBom() {
    const { productType, processTemplate, bomItems = [], materials = [], steps = [] } = usePage().props;

    const [showAddForm, setShowAddForm] = useState(false);

    const handleRemove = (item) => {
        if (!confirm('Remove this material from BOM?')) return;
        router.delete(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom/${item.id}`,
            { preserveScroll: true },
        );
    };

    const columns = useMemo(() => [
        {
            id: 'material',
            accessorKey: 'material_name',
            header: 'Material',
            cell: ({ row }) => (
                <>
                    <div className="text-sm font-medium text-om-ink">
                        {row.original.material_name}
                    </div>
                    <div className="text-xs text-om-muted font-mono">{row.original.material_code}</div>
                </>
            ),
        },
        {
            id: 'type',
            accessorKey: 'material_type_name',
            header: 'Type',
            cell: ({ row }) => (
                <span
                    className={`px-2 py-1 rounded-full text-xs font-medium ${typeColorClass(
                        row.original.material_type_code,
                    )}`}
                >
                    {row.original.material_type_name}
                </span>
            ),
        },
        {
            id: 'step',
            accessorFn: (r) => (r.step_number != null ? `#${r.step_number} ${r.step_name}` : ''),
            header: 'Step',
            cell: ({ row }) => (
                <span className="text-sm text-om-muted">
                    {row.original.step_number != null ? (
                        `#${row.original.step_number} ${row.original.step_name}`
                    ) : (
                        <span className="text-om-faint">General</span>
                    )}
                </span>
            ),
        },
        {
            id: 'qty_per_unit',
            accessorKey: 'quantity_per_unit',
            header: 'Qty/Unit',
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="text-sm font-mono">
                    {row.original.quantity_per_unit} {row.original.unit_of_measure}
                </span>
            ),
        },
        {
            id: 'scrap',
            accessorKey: 'scrap_percentage',
            header: 'Scrap %',
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="text-sm">{row.original.scrap_percentage}%</span>
            ),
        },
        {
            id: 'consumed',
            accessorKey: 'consumed_at',
            header: 'Consumed',
            cell: ({ row }) => (
                <span className="text-sm text-om-muted capitalize">{row.original.consumed_at}</span>
            ),
        },
        {
            id: 'tracking',
            accessorKey: 'tracking_type',
            header: 'Tracking',
            cell: ({ row }) => (
                <span className="text-sm text-om-muted capitalize">{row.original.tracking_type}</span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => (
                <button
                    type="button"
                    onClick={() => handleRemove(row.original)}
                    className="text-om-blocked hover:text-om-blocked text-sm"
                >
                    Remove
                </button>
            ),
        },
    ], [productType.id, processTemplate.id]);

    return (
        <>
            <Head title={`BOM - ${processTemplate.name}`} />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`}
                        className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Template
                    </a>

                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-om-ink">Bill of Materials</h1>
                            <p className="text-sm text-om-muted mt-1">
                                {processTemplate.name} (v{processTemplate.version}) &bull; {productType.name}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowAddForm((v) => !v)}
                            className="btn-touch btn-primary"
                        >
                            <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Material
                        </button>
                    </div>
                </div>

                {showAddForm && (
                    <AddMaterialForm
                        productType={productType}
                        processTemplate={processTemplate}
                        materials={materials}
                        steps={steps}
                        onCancel={() => setShowAddForm(false)}
                    />
                )}

                {bomItems.length > 0 ? (
                    <DataTable
                        data={bomItems}
                        columns={columns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                    />
                ) : (
                    <div className="card text-center py-12">
                        <p className="text-om-muted text-lg mb-4">No materials in BOM yet.</p>
                        <button
                            type="button"
                            onClick={() => setShowAddForm(true)}
                            className="btn-touch btn-primary"
                        >
                            Add First Material
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}

ProcessTemplatesBom.layout = (page) => <AppLayout>{page}</AppLayout>;
