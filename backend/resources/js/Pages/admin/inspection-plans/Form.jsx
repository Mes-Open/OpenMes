import { Link } from '@inertiajs/react';
import RepeatableRows from '../../../components/RepeatableRows';

const CRITERIA_FIELDS = [
    { name: 'name', label: 'Characteristic', placeholder: 'Surface finish' },
    {
        name: 'type', label: 'Type', type: 'select', width: 'w-36',
        options: [
            { value: 'visual', label: 'Visual' },
            { value: 'measurement', label: 'Measurement' },
            { value: 'functional', label: 'Functional' },
            { value: 'pass_fail', label: 'Pass/Fail' },
        ],
    },
    { name: 'required', label: 'Req.', type: 'checkbox', width: 'w-12' },
    { name: 'unit', label: 'Unit', width: 'w-20' },
    { name: 'spec_min', label: 'Min', type: 'number', width: 'w-20' },
    { name: 'spec_max', label: 'Max', type: 'number', width: 'w-20' },
];

/**
 * Inspection plan form. `scope` (material / material_type / generic) is a
 * form-only control that decides which target FK is sent; criteria is a
 * repeatable list of characteristics. Non-optimistic write-through.
 */
export default function InspectionPlanForm({ form, materials, materialTypes, submitLabel, onSubmit }) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-4xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Name <span className="text-om-blocked">*</span></label>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" autoFocus />
                {errors.name && <p className="mt-1 text-xs text-om-blocked">{errors.name}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Description</label>
                <textarea value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} rows={2} className="form-input w-full" />
            </div>

            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Scope <span className="text-om-blocked">*</span></label>
                <div className="flex gap-4">
                    {[['material', 'Specific material'], ['material_type', 'Material type'], ['generic', 'Generic']].map(([v, lbl]) => (
                        <label key={v} className="flex items-center gap-2 text-sm">
                            <input type="radio" checked={data.scope === v} onChange={() => setData('scope', v)} />
                            {lbl}
                        </label>
                    ))}
                </div>
            </div>

            {data.scope === 'material' && (
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">Material <span className="text-om-blocked">*</span></label>
                    <select value={data.material_id ?? ''} onChange={(e) => setData('material_id', e.target.value)} className="form-input w-full">
                        <option value="">— Select material —</option>
                        {materials.map((m) => <option key={m.id} value={String(m.id)}>{m.name}</option>)}
                    </select>
                    {errors.material_id && <p className="mt-1 text-xs text-om-blocked">{errors.material_id}</p>}
                </div>
            )}
            {data.scope === 'material_type' && (
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">Material Type <span className="text-om-blocked">*</span></label>
                    <select value={data.material_type_id ?? ''} onChange={(e) => setData('material_type_id', e.target.value)} className="form-input w-full">
                        <option value="">— Select type —</option>
                        {materialTypes.map((t) => <option key={t.id} value={String(t.id)}>{t.name}</option>)}
                    </select>
                    {errors.material_type_id && <p className="mt-1 text-xs text-om-blocked">{errors.material_type_id}</p>}
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-om-muted mb-2">Criteria <span className="text-om-blocked">*</span></label>
                <RepeatableRows
                    value={data.criteria}
                    onChange={(rows) => setData('criteria', rows)}
                    fields={CRITERIA_FIELDS}
                    addLabel="+ Add criterion"
                    newRow={() => ({ name: '', type: 'visual', required: true, unit: '', spec_min: '', spec_max: '' })}
                />
                {errors.criteria && <p className="mt-1 text-xs text-om-blocked">{errors.criteria}</p>}
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/inspection-plans" className="text-om-muted hover:text-om-ink text-sm">Cancel</Link>
            </div>
        </form>
    );
}
