import { Link } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';
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
        <form onSubmit={onSubmit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5">
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
                    <Dropdown
                        options={[{ value: '', label: __('— None —') }, ...crews.map((c) => ({ value: String(c.id), label: c.name }))]}
                        value={data.crew_id == null ? '' : String(data.crew_id)}
                        onChange={(v) => setData('crew_id', v)}
                        className="w-full"
                    />
                </Field>
                <Field label={__('Wage Group')} error={errors.wage_group_id}>
                    <Dropdown
                        options={[{ value: '', label: __('— None —') }, ...wageGroups.map((g) => ({ value: String(g.id), label: g.name }))]}
                        value={data.wage_group_id == null ? '' : String(data.wage_group_id)}
                        onChange={(v) => setData('wage_group_id', v)}
                        className="w-full"
                    />
                </Field>
                <Field label={__('Personnel Class')} error={errors.personnel_class_id}>
                    <Dropdown
                        options={[{ value: '', label: __('— None —') }, ...personnelClasses.map((p) => ({ value: String(p.id), label: p.name }))]}
                        value={data.personnel_class_id == null ? '' : String(data.personnel_class_id)}
                        onChange={(v) => setData('personnel_class_id', v)}
                        className="w-full"
                    />
                </Field>
            </div>

            <div>
                <h3 className="text-sm font-semibold text-om-muted mb-2">{__('Compensation')}</h3>
                <div className="grid grid-cols-2 gap-4">
                    <Field label={__('Pay type')} error={errors.pay_type}>
                        <Dropdown
                            options={[
                                { value: '', label: __('Use system default') },
                                { value: 'hourly', label: __('Hourly') },
                                { value: 'weekly', label: __('Weekly') },
                                { value: 'piece_rate', label: __('Piece rate') },
                            ]}
                            value={data.pay_type == null ? '' : String(data.pay_type)}
                            onChange={(v) => setData('pay_type', v)}
                            className="w-full"
                        />
                    </Field>
                    <Field label={__('Pay rate')} error={errors.pay_rate}>
                        <input type="number" step="0.0001" min="0" value={data.pay_rate ?? ''} onChange={(e) => setData('pay_rate', e.target.value)} className="form-input w-full" />
                    </Field>
                </div>
                <p className="mt-1 text-xs text-om-faint">{__('Hourly/weekly: rate per hour/week. Piece rate: amount per produced piece. Currency is set system-wide in Settings.')}</p>
            </div>

            <Checkbox checked={!!data.is_active} onChange={(next) => setData('is_active', next)} label={__('Active')} />

            <div>
                <label className="block text-sm font-medium text-om-muted mb-2">{__('Skills & level (1-5)')}</label>
                <div className="border border-om-line2 rounded divide-y">
                    {skills.length === 0 && <p className="px-3 py-2 text-sm text-om-faint">{__('No skills defined.')}</p>}
                    {skills.map((skill) => {
                        const id = String(skill.id);
                        const on = selectedSkills.has(id);
                        return (
                            <div key={skill.id} className="flex items-center gap-3 px-3 py-2">
                                <Checkbox className="flex-1" checked={on} onChange={(next) => toggleSkill(skill.id, next)} label={skill.name} />
                                {on && (
                                    <Dropdown
                                        options={[1, 2, 3, 4, 5].map((l) => ({ value: String(l), label: String(l) }))}
                                        value={String(selectedSkills.get(id))}
                                        onChange={(v) => setSkillLevel(skill.id, v)}
                                        className="min-w-[64px]"
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {processing ? __('Saving…') : isEdit ? __('Save Changes') : __('Create Worker')}
                </Button>
                <Link href="/admin/workers" className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</Link>
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
