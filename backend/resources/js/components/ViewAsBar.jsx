import { router, usePage } from '@inertiajs/react';
import { __ } from '../lib/i18n';

/**
 * The administrator's view switcher: a slim strip on top of every screen that
 * redraws the interface as a supervisor or an operator sees it.
 *
 * Presentation only — the account, its roles and every permission stay the
 * administrator's. The server sends `viewAs` to Admins only (null for anyone
 * else), so the bar never reaches another role; the switch itself is a POST the
 * server refuses for non-admins too. The choice lives in the session.
 *
 * While another role's view is on, the strip changes colour and says so plainly
 * — that sentence is the point: nobody should mistake the preview for having
 * fewer rights, or a missing button for a missing permission.
 */
const VIEWS = [
    { key: 'admin', label: 'Administrator' },
    { key: 'supervisor', label: 'Supervisor' },
    { key: 'operator', label: 'Operator' },
];

export default function ViewAsBar() {
    const { viewAs } = usePage().props;
    if (!viewAs) return null;

    const previewing = viewAs !== 'admin';
    const current = VIEWS.find((v) => v.key === viewAs) ?? VIEWS[0];
    const switchTo = (view) => {
        if (view !== viewAs) router.post('/view-as', { view });
    };

    return (
        <div
            role="region"
            aria-label={__('View switcher')}
            className={`shrink-0 flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-1.5 border-b text-[12.5px] ${
                previewing
                    ? 'bg-om-downtime-bg border-om-downtime text-om-ink'
                    : 'bg-om-card border-om-line text-om-muted'
            }`}
        >
            <div className="flex items-center gap-2">
                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('View')}</span>
                <div className="inline-flex rounded-om-sm border border-om-line bg-om-bg p-0.5" role="group" aria-label={__('View as')}>
                    {VIEWS.map((v) => (
                        <button
                            key={v.key}
                            type="button"
                            aria-pressed={v.key === viewAs}
                            onClick={() => switchTo(v.key)}
                            className={`px-2.5 py-1 rounded-om-sm font-medium transition-colors ${
                                v.key === viewAs
                                    ? 'bg-om-ink text-om-on-ink'
                                    : 'text-om-muted hover:text-om-ink hover:bg-om-chip'
                            }`}
                        >
                            {__(v.label)}
                        </button>
                    ))}
                </div>
            </div>

            {previewing && (
                <p className="flex-1 min-w-[16rem] text-om-ink" role="status">
                    <strong className="font-semibold">{__('View: :role', { role: __(current.label) })}</strong>
                    {' — '}
                    {__('your permissions have not changed.')}{' '}
                    <button
                        type="button"
                        onClick={() => switchTo('admin')}
                        className="font-semibold underline underline-offset-2 hover:text-om-accent"
                    >
                        {__('Back to the administrator view')}
                    </button>
                </p>
            )}
        </div>
    );
}
