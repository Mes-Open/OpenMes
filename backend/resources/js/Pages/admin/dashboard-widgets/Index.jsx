// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (reorder/toggle/save logic untouched).
import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Badge, Button } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function DashboardWidgetsIndex() {
    const { widgets: initialWidgets = [] } = usePage().props;
    const { csrf_token } = usePage().props;

    const [widgets, setWidgets] = useState(initialWidgets);
    const [dirty, setDirty] = useState(false);
    const [saved, setSaved] = useState(false);

    function moveUp(index) {
        if (index === 0) return;
        setWidgets((prev) => {
            const next = [...prev];
            [next[index - 1], next[index]] = [next[index], next[index - 1]];
            return next;
        });
        setDirty(true);
    }

    function moveDown(index) {
        setWidgets((prev) => {
            if (index >= prev.length - 1) return prev;
            const next = [...prev];
            [next[index], next[index + 1]] = [next[index + 1], next[index]];
            return next;
        });
        setDirty(true);
    }

    function toggleEnabled(index) {
        setWidgets((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], enabled: !next[index].enabled };
            return next;
        });
        setDirty(true);
    }

    async function saveAll() {
        await fetch('/admin/dashboard-widgets/save-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf_token,
            },
            body: JSON.stringify({
                widgets: widgets.map((w) => ({ id: w.id, enabled: w.enabled })),
            }),
        });
        setDirty(false);
        setSaved(true);
        setTimeout(() => {
            window.location.href = '/admin/dashboard';
        }, 1200);
    }

    return (
        <>
            <Head title={__('Dashboard Setup')} />
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/settings" className="inline-flex size-[38px] items-center justify-center rounded-om-sm bg-om-chip text-om-ink transition-colors hover:bg-om-line2">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Dashboard Setup')}</h1>
                        <p className="text-om-muted text-sm mt-0.5">{__('Enable, disable, and reorder dashboard widgets')}</p>
                    </div>
                </div>

                <div className="space-y-2">
                    {widgets.map((widget, index) => (
                        <div key={widget.id} className="bg-om-card border border-om-line rounded-om p-4 flex items-center gap-3">
                            {/* Move buttons */}
                            <div className="flex flex-col shrink-0">
                                <button
                                    onClick={() => moveUp(index)}
                                    disabled={index === 0}
                                    className="p-1 text-om-faint hover:text-om-ink cursor-pointer disabled:opacity-20 disabled:cursor-not-allowed"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <button
                                    onClick={() => moveDown(index)}
                                    disabled={index === widgets.length - 1}
                                    className="p-1 text-om-faint hover:text-om-ink cursor-pointer disabled:opacity-20 disabled:cursor-not-allowed"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            {/* Position number */}
                            <span className="font-mono text-[12px] text-om-faint w-6 text-center shrink-0">
                                {index + 1}
                            </span>

                            {/* Info */}
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h3 className="text-[13.5px] font-semibold text-om-ink">{widget.name}</h3>
                                    <Badge variant={widget.source === 'builtin' ? 'neutral' : 'outline'}>
                                        {widget.source === 'builtin' ? __('Built-in') : widget.module_name}
                                    </Badge>
                                    <span className="rounded-[20px] bg-om-chip px-[9px] py-[2px] font-mono text-[9.5px] uppercase tracking-[0.06em] text-om-muted">
                                        {widget.zone}
                                    </span>
                                </div>
                                {widget.description && (
                                    <p className="text-xs text-om-muted mt-0.5">{widget.description}</p>
                                )}
                            </div>

                            {/* Toggle */}
                            <button
                                onClick={() => toggleEnabled(index)}
                                className={`px-3 py-1.5 rounded-om-sm text-[12px] font-semibold transition-colors cursor-pointer shrink-0 ${widget.enabled
                                    ? 'text-om-running bg-om-running-bg hover:brightness-95'
                                    : 'text-om-blocked bg-om-blocked-bg hover:brightness-95'}`}
                            >
                                {widget.enabled ? __('Enabled') : __('Disabled')}
                            </button>
                        </div>
                    ))}

                    <div className="flex justify-between items-center mt-6">
                        <div>
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Use arrows to reorder. Modules can register additional widgets.')}</p>
                            {dirty && (
                                <p className="text-xs text-om-accent font-medium mt-1">{__('You have unsaved changes!')}</p>
                            )}
                        </div>
                        <Button
                            variant="primary"
                            onClick={saveAll}
                            className={dirty ? 'animate-pulse ring-2 ring-om-accent' : ''}
                        >
                            {__('Save')}
                        </Button>
                    </div>
                </div>

                {/* Toast */}
                {saved && (
                    <div className="fixed bottom-6 right-6 z-50 bg-om-ink text-white px-5 py-3 rounded-om shadow-xl flex items-center gap-2">
                        <svg className="w-5 h-5 text-om-running" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span className="text-[13px]">{__('Saved! Redirecting...')}</span>
                    </div>
                )}
            </div>
        </>
    );
}

DashboardWidgetsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
