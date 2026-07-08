import { Link } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import { Button, Checkbox, Dropdown, RadioGroup } from '@openmes/ui';
import { useState } from 'react';

/**
 * Bespoke create/edit form for user accounts. Conditional on `account_type`:
 *  - 'user'        → role select + optional worker profile (crew/wage-group/skills matrix)
 *  - 'workstation' → workstation select (role auto-assigned Operator server-side)
 *
 * `form` is an Inertia useForm() instance. Non-optimistic write-through; the
 * password is confirmed (password + password_confirmation). On edit, leaving
 * password blank keeps the current one.
 */
export default function UserForm({ form, roles, workstations, crews, wageGroups, skills, isEdit, onSubmit }) {
    const { data, setData, errors, processing } = form;
    const isUser = data.account_type === 'user';

    // Worker profile is optional and only relevant for shop-floor staff, so it
    // starts collapsed to keep the form focused — auto-expanded when editing an
    // account that already has a worker profile.
    const [showWorker, setShowWorker] = useState(() => isEdit && !!data.worker_code);

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
            {/* Account type */}
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">{__("Account Type")}</label>
                <RadioGroup
                    options={[
                        { value: 'user', label: __('Personal user') },
                        { value: 'workstation', label: __('Workstation') },
                    ]}
                    value={data.account_type}
                    onChange={(v) => setData('account_type', v)}
                />
            </div>

            <Field label={__("Name")} error={errors.name} required>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" />
            </Field>
            <Field label={__("Username")} error={errors.username} required>
                <input type="text" value={data.username} onChange={(e) => setData('username', e.target.value)} className="form-input w-full" />
            </Field>
            <Field label={__("Email")} error={errors.email} required>
                <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="form-input w-full" />
            </Field>

            <div className="grid grid-cols-2 gap-4">
                <Field label={isEdit ? __("Password (blank = keep)") : __("Password")} error={errors.password} required={!isEdit}>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="form-input w-full" autoComplete="new-password" />
                </Field>
                <Field label={__("Confirm Password")}>
                    <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="form-input w-full" autoComplete="new-password" />
                </Field>
            </div>

            <Checkbox
                checked={!!data.force_password_change}
                onChange={(next) => setData('force_password_change', next)}
                label={__("Require password change at next login")}
            />

            {isUser ? (
                <Field label={__("Role")} error={errors.role} required>
                    <Dropdown
                        options={roles.map((r) => ({ value: String(r), label: r }))}
                        value={data.role == null ? '' : String(data.role)}
                        onChange={(v) => setData('role', v)}
                        placeholder={__("— Select role —")}
                        className="w-full"
                    />
                </Field>
            ) : (
                <Field label={__("Workstation")} error={errors.workstation_id} required>
                    <Dropdown
                        options={workstations.map((w) => ({ value: String(w.id), label: w.name }))}
                        value={data.workstation_id == null ? '' : String(data.workstation_id)}
                        onChange={(v) => setData('workstation_id', v)}
                        placeholder={__("— Select workstation —")}
                        className="w-full"
                    />
                </Field>
            )}

            {/* Optional worker profile — personal users only, collapsed by default */}
            {isUser && (
                <div className="border border-om-line2 rounded-om-sm">
                    <button
                        type="button"
                        onClick={() => setShowWorker((v) => !v)}
                        className="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-om-muted hover:bg-om-bg rounded-om-sm"
                    >
                        <span>
                            {__('Worker profile')}{' '}
                            <span className="font-normal text-om-faint">— {__('optional, only for shop-floor staff')}</span>
                        </span>
                        <span className="text-om-faint">{showWorker ? '▲' : '▼'}</span>
                    </button>
                    {showWorker && (
                <div className="border-t border-om-line2 p-4 space-y-4">
                    <p className="text-xs text-om-faint">{__('Fill in a worker code to link/create a shop-floor worker profile for this account. Leave this collapsed for office/admin accounts.')}</p>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label={__('Worker Code')} error={errors.worker_code}>
                            <input type="text" value={data.worker_code} onChange={(e) => setData('worker_code', e.target.value)} className="form-input w-full" />
                        </Field>
                        <Field label={__('Phone')} error={errors.worker_phone}>
                            <input type="text" value={data.worker_phone} onChange={(e) => setData('worker_phone', e.target.value)} className="form-input w-full" />
                        </Field>
                        <Field label={__('Crew')}>
                            <Dropdown
                                options={[{ value: '', label: `— ${__('None')} —` }, ...crews.map((c) => ({ value: String(c.id), label: c.name }))]}
                                value={data.worker_crew_id == null ? '' : String(data.worker_crew_id)}
                                onChange={(v) => setData('worker_crew_id', v)}
                                className="w-full"
                            />
                        </Field>
                        <Field label={__('Wage Group')}>
                            <Dropdown
                                options={[{ value: '', label: `— ${__('None')} —` }, ...wageGroups.map((g) => ({ value: String(g.id), label: g.name }))]}
                                value={data.worker_wage_group_id == null ? '' : String(data.worker_wage_group_id)}
                                onChange={(v) => setData('worker_wage_group_id', v)}
                                className="w-full"
                            />
                        </Field>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-2">{__('Skills & level (1–5)')}</label>
                        <div className="border border-om-line2 rounded divide-y">
                            {skills.length === 0 && <p className="px-3 py-2 text-sm text-om-faint">{__('No skills defined.')}</p>}
                            {skills.map((skill) => {
                                const id = String(skill.id);
                                const on = selectedSkills.has(id);
                                return (
                                    <div key={skill.id} className="flex items-center gap-3 px-3 py-2">
                                        <Checkbox
                                            checked={on}
                                            onChange={(next) => toggleSkill(skill.id, next)}
                                            label={skill.name}
                                            className="flex-1"
                                        />
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
                </div>
                    )}
                </div>
            )}

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {processing ? __('Saving…') : isEdit ? __('Save Changes') : __('Create Account')}
                </Button>
                <Link href="/admin/users" className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</Link>
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
