import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const TYPE_COLORS = {
    raw_material:  'bg-amber-100 text-amber-800',
    semi_finished: 'bg-blue-100 text-blue-800',
    packaging:     'bg-purple-100 text-purple-800',
};

function typeColorClass(code) {
    return TYPE_COLORS[code] ?? 'bg-gray-100 text-gray-800';
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
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Material <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.material_id}
                            onChange={(e) => setData('material_id', e.target.value)}
                            required
                            className={`form-input w-full${errors.material_id ? ' border-red-500' : ''}`}
                        >
                            <option value="">Select material...</option>
                            {materials.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.code} - {m.name} ({m.material_type_name})
                                </option>
                            ))}
                        </select>
                        {errors.material_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.material_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Quantity per Unit <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            required
                            value={data.quantity_per_unit}
                            onChange={(e) => setData('quantity_per_unit', e.target.value)}
                            className={`form-input w-full${errors.quantity_per_unit ? ' border-red-500' : ''}`}
                        />
                        {errors.quantity_per_unit && (
                            <p className="mt-1 text-sm text-red-600">{errors.quantity_per_unit}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Step (optional)
                        </label>
                        <select
                            value={data.template_step_id}
                            onChange={(e) => setData('template_step_id', e.target.value)}
                            className="form-input w-full"
                        >
                            <option value="">All steps / general</option>
                            {steps.map((s) => (
                                <option key={s.id} value={s.id}>
                                    #{s.step_number} - {s.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Scrap %</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            value={data.scrap_percentage}
                            onChange={(e) => setData('scrap_percentage', e.target.value)}
                            className="form-input w-full"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Consumed At</label>
                        <select
                            value={data.consumed_at}
                            onChange={(e) => setData('consumed_at', e.target.value)}
                            className="form-input w-full"
                        >
                            <option value="start">Start of step</option>
                            <option value="during">During step</option>
                            <option value="end">End of step</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
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

    return (
        <>
            <Head title={`BOM - ${processTemplate.name}`} />

            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`}
                        className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Template
                    </a>

                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-800">Bill of Materials</h1>
                            <p className="text-sm text-gray-600 mt-1">
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
                    <div className="card overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Material
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Step
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Qty/Unit
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Scrap %
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Consumed
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Tracking
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {bomItems.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3">
                                            <div className="text-sm font-medium text-gray-900">
                                                {item.material_name}
                                            </div>
                                            <div className="text-xs text-gray-500 font-mono">{item.material_code}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`px-2 py-1 rounded-full text-xs font-medium ${typeColorClass(
                                                    item.material_type_code,
                                                )}`}
                                            >
                                                {item.material_type_name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">
                                            {item.step_number != null ? (
                                                `#${item.step_number} ${item.step_name}`
                                            ) : (
                                                <span className="text-gray-400">General</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-right font-mono">
                                            {item.quantity_per_unit} {item.unit_of_measure}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-right">
                                            {item.scrap_percentage}%
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 capitalize">
                                            {item.consumed_at}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 capitalize">
                                            {item.tracking_type}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleRemove(item)}
                                                className="text-red-600 hover:text-red-800 text-sm"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="card text-center py-12">
                        <p className="text-gray-500 text-lg mb-4">No materials in BOM yet.</p>
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
