import { Link, usePage } from '@inertiajs/react';
import { __, formatTime } from '../../../lib/i18n';
import { shiftTimeLeft } from './timeLeft';

/**
 * "Plant overview" page header: title, live indicator, the plant shift running
 * now (from the `overview.shift` prop, times in the plant timezone) and the
 * "New work order" shortcut for users who can open work orders.
 */
export default function OverviewHeader({ shift, liveOk = true }) {
    const { auth } = usePage().props;
    const canCreateOrder = (auth?.user?.accessibleTabs ?? []).includes('orders');

    // A configured shift running now. The fallback 06–18 / 18–06 window
    // (is_default) is not a shift anybody planned, so it reads as "no shift".
    const running = shift && shift.is_live && !shift.is_default;

    return (
        <header className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex flex-col gap-1.5 min-w-0">
                <h1 className="m-0 text-[28px] font-bold leading-tight tracking-[-0.02em] text-om-ink">
                    {__('Plant overview')}
                </h1>
                <div className="flex flex-wrap items-center gap-2.5 text-[14px] text-om-muted">
                    <span
                        className={`inline-flex items-center gap-1.5 font-semibold ${liveOk ? 'text-om-running' : 'text-om-faint'}`}
                        title={liveOk ? undefined : __('Last refresh failed')}
                        data-testid="overview-live"
                        data-live={liveOk ? 'ok' : 'failed'}
                    >
                        <span
                            aria-hidden="true"
                            className={`h-2 w-2 rounded-full ${liveOk ? 'bg-om-running' : 'bg-om-faint'}`}
                        />
                        {__('Live')}
                        {!liveOk && <span className="sr-only">{__('Last refresh failed')}</span>}
                    </span>
                    {shift && (
                        <span data-testid="overview-shift">
                            {running
                                ? [
                                    shift.name,
                                    `${formatTime(shift.start)}–${formatTime(shift.end)}`,
                                    shiftTimeLeft(shift.minutes_left),
                                ].filter(Boolean).join(' · ')
                                : __('No shift running')}
                        </span>
                    )}
                </div>
            </div>
            {canCreateOrder && (
                <Link
                    href="/admin/work-orders/create"
                    className="inline-flex h-11 items-center gap-2 rounded-[10px] bg-om-ink px-[18px] text-[14px] font-medium text-om-on-ink transition-colors hover:bg-om-ink-hover"
                >
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    {__('New work order')}
                </Link>
            )}
        </header>
    );
}
