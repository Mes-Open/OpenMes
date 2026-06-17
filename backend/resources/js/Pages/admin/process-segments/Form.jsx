import { Link } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';

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
        <form onSubmit={onSubmit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5">
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
                <Dropdown
                    options={segmentTypes.map((t) => ({ value: String(t), label: cap(t) }))}
                    value={data.segment_type == null ? '' : String(data.segment_type)}
                    onChange={(v) => setData('segment_type', v)}
                    className="w-full"
                />
            </Field>

            <Field label="Workstation Type" error={errors.workstation_type_id}>
                <Dropdown
                    options={workstationTypes.map((w) => ({ value: String(w.id), label: w.name }))}
                    value={data.workstation_type_id == null ? '' : String(data.workstation_type_id)}
                    onChange={(v) => setData('workstation_type_id', v)}
                    placeholder="— None —"
                    className="w-full"
                />
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
                <label className="block text-sm font-medium text-om-muted mb-2">Required skills</label>
                <div className="border border-om-line2 rounded-om-sm divide-y">
                    {skills.length === 0 && <p className="px-3 py-3 text-sm text-om-faint">No skills defined.</p>}
                    {skills.map((skill) => (
                        <div key={skill.id} className="flex items-center gap-2 px-3 py-2 text-sm text-om-muted">
                            <Checkbox checked={selected.has(Number(skill.id))} onChange={(next) => toggleSkill(skill.id, next)} label={skill.name} />
                        </div>
                    ))}
                </div>
                {errors.required_skill_ids && <p className="mt-1 text-xs text-om-blocked">{errors.required_skill_ids}</p>}
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
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {processing ? 'Saving…' : submitLabel}
                </Button>
                <Link href="/admin/process-segments" className="text-om-muted hover:text-om-ink text-sm">Cancel</Link>
            </div>
        </form>
    );
}

function Field({ label, error, required, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
