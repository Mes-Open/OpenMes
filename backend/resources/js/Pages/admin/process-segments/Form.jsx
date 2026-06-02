import { Link } from '@inertiajs/react';

const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

/**
 * Bespoke create/edit form for process segments. Beyond scalar fields it
 * manages `required_skill_ids` — a flat array of numeric skill ids (no level) —
 * and `parameters_raw`, a JSON object entered as text.
 *
 * `form` is an Inertia useForm() instance. Write-through (non-optimistic):
 * parent submits, Laravel validates + redirects.
 */
export default function ProcessSegmentForm({ form, workstationTypes, skills, segmentTypes, submitLabel, onSubmit }) {
    const { data, setData, errors, processing } = form;

    const selected = new Set((data.required_skill_ids ?? []).map((id) => Number(id)));

    const toggleSkill = (skillId, checked) => {
        const id = Number(skillId);
        const next = new Set(selected);
        if (checked) next.add(id);
        else next.delete(id);
        setData('required_skill_ids', [...next]);
    };

    return (
        <form onSubmit={onSubmit} className="bg-white rounded-lg shadow-sm p-6 max-w-3xl space-y-5">
            <Field label="Code" error={errors.code} required>
                <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} className="form-input w-full" autoFocus />
            </Field>
            <Field label="Name" error={errors.name} required>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" />
            </Field>
            <Field label="Description" error={errors.description}>
                <textarea value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} rows={3} className="form-input w-full" />
            </Field>

            <Field label="Segment Type" error={errors.segment_type} required>
                <select value={data.segment_type} onChange={(e) => setData('segment_type', e.target.value)} className="form-input w-full">
                    {segmentTypes.map((t) => (
                        <option key={t} value={t}>{cap(t)}</option>
                    ))}
                </select>
            </Field>

            <Field label="Workstation Type" error={errors.workstation_type_id}>
                <select value={data.workstation_type_id ?? ''} onChange={(e) => setData('workstation_type_id', e.target.value)} className="form-input w-full">
                    <option value="">— None —</option>
                    {workstationTypes.map((w) => (
                        <option key={w.id} value={String(w.id)}>{w.name}</option>
                    ))}
                </select>
            </Field>

            <Field label="Estimated Duration (minutes)" error={errors.estimated_duration_minutes}>
                <input type="number" value={data.estimated_duration_minutes ?? ''} onChange={(e) => setData('estimated_duration_minutes', e.target.value)} className="form-input w-full" />
            </Field>

            <Field label="Required Operators" error={errors.required_operators} required>
                <input type="number" value={data.required_operators ?? ''} onChange={(e) => setData('required_operators', e.target.value)} className="form-input w-full" />
            </Field>

            <Field label="Standard Instruction" error={errors.standard_instruction}>
                <textarea value={data.standard_instruction ?? ''} onChange={(e) => setData('standard_instruction', e.target.value)} rows={3} className="form-input w-full" />
            </Field>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Required skills</label>
                <div className="border border-gray-200 rounded-lg divide-y">
                    {skills.length === 0 && <p className="px-3 py-3 text-sm text-gray-400">No skills defined.</p>}
                    {skills.map((skill) => (
                        <label key={skill.id} className="flex items-center gap-2 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" checked={selected.has(Number(skill.id))} onChange={(e) => toggleSkill(skill.id, e.target.checked)} />
                            {skill.name}
                        </label>
                    ))}
                </div>
                {errors.required_skill_ids && <p className="mt-1 text-xs text-red-600">{errors.required_skill_ids}</p>}
            </div>

            <Field label="Parameters (JSON)" error={errors.parameters_raw}>
                <textarea
                    value={data.parameters_raw ?? ''}
                    onChange={(e) => setData('parameters_raw', e.target.value)}
                    rows={6}
                    className="form-input w-full font-mono text-sm"
                />
            </Field>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/process-segments" className="text-gray-500 hover:text-gray-800 text-sm">Cancel</Link>
            </div>
        </form>
    );
}

function Field({ label, error, required, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
