import { Link } from '@inertiajs/react';
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
                <label className="block text-sm font-medium text-om-muted mb-1">Account Type</label>
                <div className="flex gap-4">
                    {['user', 'workstation'].map((t) => (
                        <label key={t} className="flex items-center gap-2 text-sm">
                            <input type="radio" name="account_type" checked={data.account_type === t} onChange={() => setData('account_type', t)} />
                            {t === 'user' ? 'Personal user' : 'Workstation'}
                        </label>
                    ))}
                </div>
            </div>

            <Field label="Name" error={errors.name} required>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" />
            </Field>
            <Field label="Username" error={errors.username} required>
                <input type="text" value={data.username} onChange={(e) => setData('username', e.target.value)} className="form-input w-full" />
            </Field>
            <Field label="Email" error={errors.email} required>
                <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="form-input w-full" />
            </Field>

            <div className="grid grid-cols-2 gap-4">
                <Field label={isEdit ? 'Password (blank = keep)' : 'Password'} error={errors.password} required={!isEdit}>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="form-input w-full" autoComplete="new-password" />
                </Field>
                <Field label="Confirm Password">
                    <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="form-input w-full" autoComplete="new-password" />
                </Field>
            </div>

            <label className="flex items-center gap-2 text-sm text-om-muted">
                <input type="checkbox" checked={!!data.force_password_change} onChange={(e) => setData('force_password_change', e.target.checked)} />
                Require password change at next login
            </label>

            {isUser ? (
                <Field label="Role" error={errors.role} required>
                    <select value={data.role ?? ''} onChange={(e) => setData('role', e.target.value)} className="form-input w-full">
                        <option value="">— Select role —</option>
                        {roles.map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                </Field>
            ) : (
                <Field label="Workstation" error={errors.workstation_id} required>
                    <select value={data.workstation_id ?? ''} onChange={(e) => setData('workstation_id', e.target.value)} className="form-input w-full">
                        <option value="">— Select workstation —</option>
                        {workstations.map((w) => <option key={w.id} value={String(w.id)}>{w.name}</option>)}
                    </select>
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
                            Worker profile{' '}
                            <span className="font-normal text-om-faint">— optional, only for shop-floor staff</span>
                        </span>
                        <span className="text-om-faint">{showWorker ? '▲' : '▼'}</span>
                    </button>
                    {showWorker && (
                <div className="border-t border-om-line2 p-4 space-y-4">
                    <p className="text-xs text-om-faint">Fill in a worker code to link/create a shop-floor worker profile for this account. Leave this collapsed for office/admin accounts.</p>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Worker Code" error={errors.worker_code}>
                            <input type="text" value={data.worker_code} onChange={(e) => setData('worker_code', e.target.value)} className="form-input w-full" />
                        </Field>
                        <Field label="Phone" error={errors.worker_phone}>
                            <input type="text" value={data.worker_phone} onChange={(e) => setData('worker_phone', e.target.value)} className="form-input w-full" />
                        </Field>
                        <Field label="Crew">
                            <select value={data.worker_crew_id ?? ''} onChange={(e) => setData('worker_crew_id', e.target.value)} className="form-input w-full">
                                <option value="">— None —</option>
                                {crews.map((c) => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Wage Group">
                            <select value={data.worker_wage_group_id ?? ''} onChange={(e) => setData('worker_wage_group_id', e.target.value)} className="form-input w-full">
                                <option value="">— None —</option>
                                {wageGroups.map((g) => <option key={g.id} value={String(g.id)}>{g.name}</option>)}
                            </select>
                        </Field>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-2">Skills &amp; level (1–5)</label>
                        <div className="border border-om-line2 rounded divide-y">
                            {skills.length === 0 && <p className="px-3 py-2 text-sm text-om-faint">No skills defined.</p>}
                            {skills.map((skill) => {
                                const id = String(skill.id);
                                const on = selectedSkills.has(id);
                                return (
                                    <div key={skill.id} className="flex items-center gap-3 px-3 py-2">
                                        <label className="flex items-center gap-2 flex-1 text-sm text-om-muted">
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
                </div>
                    )}
                </div>
            )}

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50">
                    {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Account'}
                </button>
                <Link href="/admin/users" className="text-om-muted hover:text-om-ink text-sm">Cancel</Link>
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
