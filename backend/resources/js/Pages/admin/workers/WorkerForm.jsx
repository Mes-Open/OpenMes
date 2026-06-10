import { Link } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import CustomFields from '../../../components/CustomFields';
import { customFieldProps } from '../../../lib/customFieldForm';

/**
 * Bespoke create/edit form for shop-floor workers.
 *
 * `form` is an Inertia useForm() instance (non-optimistic write-through).
 * The skills matrix mirrors UserForm: a checkbox per skill plus a 1–5 level
 * select, writing form.data.skills = [{ id, level }].
 */
export default function WorkerForm({ form, crews, wageGroups, personnelClasses, skills, customFields = [], isEdit, onSubmit }) {
    const { data, setData, errors, processing } = form;

    const selectedSkills = new Map((data.skills ?? []).map((s) => [String(s.id), s.level ?? 1]));

    const toggleSkill = (id, on) => {
        const next = new Map(selectedSkills);
        if (on) next.set(String(id), 1);
        else next.delete(String(id));
        setData('skills', [...next].map(([sid, level]) => ({ id: Number(sid), level })));
    };
    const setSkillLevel = (id, level) => {
        const next = new Map(selectedSkills);
        next.set(String(id), Number(level));
        setData('skills', [...next].map(([sid, lvl]) => ({ id: Number(sid), level: lvl })));
    };

    return (
        <form onSubmit={onSubmit} className="bg-white rounded-lg shadow-sm p-6 max-w-3xl space-y-5">
            <div className="grid grid-cols-2 gap-4">
                <Field label={__('Code')} error={errors.code} required>
                    <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} className="form-input w-full" />
                </Field>
                <Field label={__('Name')} error={errors.name} required>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" />
                </Field>
                <Field label={__('Email')} error={errors.email}>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="form-input w-full" />
                </Field>
                <Field label={__('Phone')} error={errors.phone}>
                    <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="form-input w-full" />
                </Field>
                <Field label={__('Crew')} error={errors.crew_id}>
                    <select value={data.crew_id ?? ''} onChange={(e) => setData('crew_id', e.target.value)} className="form-input w-full">
                        <option value="">{__('— None —')}</option>
                        {crews.map((c) => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
                    </select>
                </Field>
                <Field label={__('Wage Group')} error={errors.wage_group_id}>
                    <select value={data.wage_group_id ?? ''} onChange={(e) => setData('wage_group_id', e.target.value)} className="form-input w-full">
                        <option value="">{__('— None —')}</option>
                        {wageGroups.map((g) => <option key={g.id} value={String(g.id)}>{g.name}</option>)}
                    </select>
                </Field>
                <Field label={__('Personnel Class')} error={errors.personnel_class_id}>
                    <select value={data.personnel_class_id ?? ''} onChange={(e) => setData('personnel_class_id', e.target.value)} className="form-input w-full">
                        <option value="">{__('— None —')}</option>
                        {personnelClasses.map((p) => <option key={p.id} value={String(p.id)}>{p.name}</option>)}
                    </select>
                </Field>
            </div>

            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-2">{__('Compensation')}</h3>
                <div className="grid grid-cols-2 gap-4">
                    <Field label={__('Pay type')} error={errors.pay_type}>
                        <select value={data.pay_type ?? ''} onChange={(e) => setData('pay_type', e.target.value)} className="form-input w-full">
                            <option value="">{__('Use system default')}</option>
                            <option value="hourly">{__('Hourly')}</option>
                            <option value="weekly">{__('Weekly')}</option>
                            <option value="piece_rate">{__('Piece rate')}</option>
                        </select>
                    </Field>
                    <Field label={__('Pay rate')} error={errors.pay_rate}>
                        <input type="number" step="0.0001" min="0" value={data.pay_rate ?? ''} onChange={(e) => setData('pay_rate', e.target.value)} className="form-input w-full" />
                    </Field>
                </div>
                <p className="mt-1 text-xs text-gray-400">{__('Hourly/weekly: rate per hour/week. Piece rate: amount per produced piece. Currency is set system-wide in Settings.')}</p>
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" checked={!!data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                {__('Active')}
            </label>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">{__('Skills & level (1-5)')}</label>
                <div className="border border-gray-200 rounded divide-y">
                    {skills.length === 0 && <p className="px-3 py-2 text-sm text-gray-400">{__('No skills defined.')}</p>}
                    {skills.map((skill) => {
                        const id = String(skill.id);
                        const on = selectedSkills.has(id);
                        return (
                            <div key={skill.id} className="flex items-center gap-3 px-3 py-2">
                                <label className="flex items-center gap-2 flex-1 text-sm text-gray-700">
                                    <input type="checkbox" checked={on} onChange={(e) => toggleSkill(skill.id, e.target.checked)} />
                                    {skill.name}
                                </label>
                                {on && (
                                    <select value={selectedSkills.get(id)} onChange={(e) => setSkillLevel(skill.id, e.target.value)} className="form-input text-sm py-1">
                                        {[1, 2, 3, 4, 5].map((l) => <option key={l} value={l}>{l}</option>)}
                                    </select>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    {processing ? __('Saving…') : isEdit ? __('Save Changes') : __('Create Worker')}
                </button>
                <Link href="/admin/workers" className="text-gray-500 hover:text-gray-800 text-sm">{__('Cancel')}</Link>
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
