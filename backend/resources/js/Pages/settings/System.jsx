import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

function SelectCard({ value, current, onChange, label, desc, disabled }) {
    const isSelected = value === current;
    return (
        <div
            onClick={() => !disabled && onChange(value)}
            className={`flex flex-col gap-1 border rounded-lg p-3 transition-colors
                ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                ${isSelected ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
        >
            <span className="font-medium text-sm text-gray-800 dark:text-gray-100">{label}</span>
            {desc && <span className="text-xs text-gray-500 dark:text-gray-400">{desc}</span>}
        </div>
    );
}

function TabButton({ label, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                ${active
                    ? 'border-blue-500 text-blue-600 dark:text-blue-300'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 hover:border-gray-300'}`}
        >
            {label}
        </button>
    );
}

export default function System() {
    const { settings, availableLocales, appUrl } = usePage().props;

    const [tab, setTab] = useState('general');
    const [sampleConfirm, setSampleConfirm] = useState(false);
    const { csrf_token } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        production_period: settings.production_period ?? 'none',
        allow_overproduction: settings.allow_overproduction ?? false,
        force_sequential_steps: settings.force_sequential_steps ?? true,
        workstation_routing_enabled: settings.workstation_routing_enabled ?? false,
        scanner_mode: settings.scanner_mode ?? 'hid',
        workflow_mode: settings.workflow_mode ?? 'status',
        pin_login_enabled: settings.pin_login_enabled ?? false,
        language: settings.language ?? 'en',
        schedule_view_mode: settings.schedule_view_mode ?? 'weekly',
        schedule_shifts_per_day: settings.schedule_shifts_per_day ?? 1,
        schedule_horizon_weeks: settings.schedule_horizon_weeks ?? 6,
        schedule_show_weekends: settings.schedule_show_weekends ?? true,
        realtime_mode: settings.realtime_mode ?? 'polling',
        production_tracking_mode: settings.production_tracking_mode ?? 'per_operation',
        cors_allowed_origins: settings.cors_allowed_origins ?? '',
        cors_allowed_methods: settings.cors_allowed_methods ?? 'GET, POST',
        cors_max_age: settings.cors_max_age ?? 0,
        production_qty_edit_policy: settings.production_qty_edit_policy ?? 'none',
        production_qty_edit_window_minutes: settings.production_qty_edit_window_minutes ?? 1,
    });

    function handleSubmit(e) {
        e.preventDefault();
        post('/settings/system');
    }

    return (
        <div className="max-w-3xl mx-auto">
            <Head title={__('System Settings')} />

            <div className="flex items-center gap-3 mb-6">
                <Link href="/settings" className="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('System Settings')}</h1>
                    <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{__('Global application configuration')}</p>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav className="flex gap-1 -mb-px overflow-x-auto">
                    {[
                        ['general', __('General')],
                        ['production', __('Production')],
                        ['schedule', __('Schedule')],
                        ['security', __('Security')],
                        ['data', __('Data')],
                    ].map(([t, label]) => (
                        <TabButton
                            key={t}
                            label={label}
                            active={tab === t}
                            onClick={() => setTab(t)}
                        />
                    ))}
                </nav>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* ═══ General ═══ */}
                {tab === 'general' && (
                    <div className="card">
                        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{__('Language')}</h2>
                        <div className="mb-2">
                            <label className="form-label">{__('Select language')}</label>
                            <select
                                value={data.language}
                                onChange={(e) => setData('language', e.target.value)}
                                className="form-input w-full max-w-xs"
                            >
                                {Object.entries(availableLocales ?? { en: 'English' }).map(([code, name]) => (
                                    <option key={code} value={code}>{name}</option>
                                ))}
                            </select>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                {__('Want to add a new language? Create a JSON file in')} <code>lang/</code> {__('directory.')}
                                {' '}{__('See')} <code>lang/en.json</code> {__('as reference.')}
                            </p>
                        </div>
                    </div>
                )}

                {/* ═══ Production ═══ */}
                {tab === 'production' && (
                    <div className="space-y-6">
                        {/* Production Period */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{__('Production Planning')}</h2>
                            <div className="mb-4">
                                <span className="form-label">{__('Production Period Split')}</span>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('Determines how work orders are grouped for planning.')}</p>
                                <div className="grid grid-cols-3 gap-3">
                                    {[
                                        { value: 'none', label: __('None'), desc: __('No period grouping') },
                                        { value: 'weekly', label: __('Weekly'), desc: __('Group by ISO week (1-53)') },
                                        { value: 'monthly', label: __('Monthly'), desc: __('Group by month (1-12)') },
                                    ].map((opt) => (
                                        <SelectCard
                                            key={opt.value}
                                            value={opt.value}
                                            current={data.production_period}
                                            onChange={(v) => setData('production_period', v)}
                                            label={opt.label}
                                            desc={opt.desc}
                                        />
                                    ))}
                                </div>
                                {errors.production_period && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.production_period}</p>}
                            </div>
                        </div>

                        {/* Workflow Mode */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Workflow Mode')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('Defines how work order completion is tracked.')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {[
                                    { value: 'status', label: __('Status'), desc: __('Work order status is changed manually. Board statuses are visual labels.') },
                                    { value: 'board_status', label: __('Board Status'), desc: __('Moving to a Done status automatically closes the work order.') },
                                ].map((opt) => (
                                    <SelectCard
                                        key={opt.value}
                                        value={opt.value}
                                        current={data.workflow_mode}
                                        onChange={(v) => setData('workflow_mode', v)}
                                        label={opt.label}
                                        desc={opt.desc}
                                    />
                                ))}
                            </div>
                            {errors.workflow_mode && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.workflow_mode}</p>}
                        </div>

                        {/* Production Rules */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{__('Production Rules')}</h2>
                            <div className="space-y-4">
                                <label className="flex items-start gap-3 cursor-pointer">
                                    <div className="pt-0.5">
                                        <input
                                            type="checkbox"
                                            checked={data.allow_overproduction}
                                            onChange={(e) => setData('allow_overproduction', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{__('Allow overproduction')}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{__('Allow operators to record more units than the planned quantity.')}</p>
                                    </div>
                                </label>

                                <label className="flex items-start gap-3 cursor-pointer">
                                    <div className="pt-0.5">
                                        <input
                                            type="checkbox"
                                            checked={data.force_sequential_steps}
                                            onChange={(e) => setData('force_sequential_steps', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{__('Force sequential steps')}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{__('Require production steps to be completed in defined order.')}</p>
                                    </div>
                                </label>

                                <label className="flex items-start gap-3 cursor-pointer">
                                    <div className="pt-0.5">
                                        <input
                                            type="checkbox"
                                            checked={data.workstation_routing_enabled}
                                            onChange={(e) => setData('workstation_routing_enabled', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{__('Workstation routing')}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{__('When enabled, an operator assigned to a workstation can only start or complete steps assigned to that workstation.')}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {/* Barcode Scanner */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Barcode Scanner')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('How the workstation receives input from a barcode scanner.')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {[
                                    { value: 'hid', label: __('HID / Keyboard wedge'), desc: __('Scanner acts as a keyboard. Codes are captured automatically on the workstation, no input field required.') },
                                    { value: 'manual', label: __('Manual entry'), desc: __('Operator typed the code into a visible field and confirms with Enter. Use when no scanner is available.') },
                                ].map((opt) => (
                                    <SelectCard
                                        key={opt.value}
                                        value={opt.value}
                                        current={data.scanner_mode}
                                        onChange={(v) => setData('scanner_mode', v)}
                                        label={opt.label}
                                        desc={opt.desc}
                                    />
                                ))}
                            </div>
                            {errors.scanner_mode && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.scanner_mode}</p>}
                        </div>

                        {/* Production Tracking Mode */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Production Tracking Mode')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('How operators register production progress on the shop floor.')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                {[
                                    { value: 'per_operation', label: __('Per Operation'), desc: __('Operator clicks Start/Complete on each step at each workstation. Full traceability.') },
                                    { value: 'cumulative', label: __('Cumulative'), desc: __('Operator enters total produced quantity at the end. No step tracking.') },
                                    { value: 'hybrid', label: __('Hybrid'), desc: __('Key steps tracked per-operation, quantity entry also available. Best of both.') },
                                ].map((opt) => (
                                    <SelectCard
                                        key={opt.value}
                                        value={opt.value}
                                        current={data.production_tracking_mode}
                                        onChange={(v) => setData('production_tracking_mode', v)}
                                        label={opt.label}
                                        desc={opt.desc}
                                    />
                                ))}
                            </div>
                            {errors.production_tracking_mode && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.production_tracking_mode}</p>}
                        </div>

                        {/* Production Quantity Corrections */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Production Quantity Corrections')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('Defines whether and when operators can correct previously reported quantities.')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                {[
                                    { value: 'none', label: __('No corrections'), desc: __('Operators cannot edit reported quantities. All entries are final.') },
                                    { value: 'timed', label: __('Timed window'), desc: __('Operators can correct quantities within a configurable time window after submission.') },
                                    { value: 'full', label: __('Full edit'), desc: __('Operators can edit reported quantities at any time.') },
                                ].map((opt) => (
                                    <SelectCard
                                        key={opt.value}
                                        value={opt.value}
                                        current={data.production_qty_edit_policy}
                                        onChange={(v) => setData('production_qty_edit_policy', v)}
                                        label={opt.label}
                                        desc={opt.desc}
                                    />
                                ))}
                            </div>
                            {errors.production_qty_edit_policy && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.production_qty_edit_policy}</p>}

                            {data.production_qty_edit_policy === 'timed' && (
                                <div className="mt-4">
                                    <label className="form-label" htmlFor="production_qty_edit_window_minutes">{__('Correction time window')}</label>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('How many minutes after submission an operator can still correct the quantity.')}</p>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="number"
                                            id="production_qty_edit_window_minutes"
                                            value={data.production_qty_edit_window_minutes}
                                            onChange={(e) => setData('production_qty_edit_window_minutes', parseInt(e.target.value, 10) || 1)}
                                            className="form-input w-24"
                                            min={1}
                                            max={60}
                                        />
                                        <span className="text-sm text-gray-600 dark:text-gray-300">{__('minutes')}</span>
                                    </div>
                                    {errors.production_qty_edit_window_minutes && (
                                        <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.production_qty_edit_window_minutes}</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* ═══ Schedule ═══ */}
                {tab === 'schedule' && (
                    <div className="card space-y-6">
                        <div>
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Schedule / Planner')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('Configure how the production schedule planner displays data.')}</p>
                        </div>

                        {/* View mode */}
                        <div>
                            <label className="form-label">{__('View mode')}</label>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('Default time scale for the schedule view.')}</p>
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    { value: 'weekly', label: __('Weekly'), desc: __('Plan by week') },
                                    { value: 'daily', label: __('Daily'), desc: __('Plan by day') },
                                    { value: 'monthly', label: __('Monthly'), desc: __('Plan by month') },
                                ].map((opt) => (
                                    <SelectCard
                                        key={opt.value}
                                        value={opt.value}
                                        current={data.schedule_view_mode}
                                        onChange={(v) => setData('schedule_view_mode', v)}
                                        label={opt.label}
                                        desc={opt.desc}
                                    />
                                ))}
                            </div>
                            {errors.schedule_view_mode && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.schedule_view_mode}</p>}
                        </div>

                        {/* Shifts per day */}
                        <div>
                            <label className="form-label">{__('Shifts per day')}</label>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('Number of production shifts in a 24-hour period.')}</p>
                            <div className="grid grid-cols-4 gap-3">
                                {[1, 2, 3, 4].map((n) => (
                                    <div
                                        key={n}
                                        onClick={() => setData('schedule_shifts_per_day', n)}
                                        className={`flex flex-col items-center gap-1 border rounded-lg p-3 cursor-pointer transition-colors
                                            ${data.schedule_shifts_per_day === n
                                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}
                                    >
                                        <span className="font-medium text-sm text-gray-800 dark:text-gray-100">{n}</span>
                                        <span className="text-xs text-gray-500 dark:text-gray-400">{__(':hours h', { hours: Math.floor(24 / n) })}</span>
                                    </div>
                                ))}
                            </div>
                            {errors.schedule_shifts_per_day && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.schedule_shifts_per_day}</p>}
                            <Link href="/admin/shifts" className="inline-flex items-center gap-1.5 mt-3 text-sm text-blue-600 dark:text-blue-300 hover:text-blue-800 font-medium">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {__('Manage Shifts')} &rarr;
                            </Link>
                        </div>

                        {/* Planning horizon */}
                        <div>
                            <label className="form-label" htmlFor="schedule_horizon_weeks">{__('Planning horizon')}</label>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('How many weeks ahead the planner displays.')}</p>
                            <div className="flex items-center gap-2">
                                <input
                                    type="number"
                                    id="schedule_horizon_weeks"
                                    value={data.schedule_horizon_weeks}
                                    onChange={(e) => setData('schedule_horizon_weeks', parseInt(e.target.value, 10) || 1)}
                                    className="form-input w-24"
                                    min={1}
                                    max={52}
                                />
                                <span className="text-sm text-gray-600 dark:text-gray-300">{__('weeks')}</span>
                            </div>
                            {errors.schedule_horizon_weeks && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.schedule_horizon_weeks}</p>}
                        </div>

                        {/* Show weekends */}
                        <div>
                            <label className="flex items-start gap-3 cursor-pointer">
                                <div className="pt-0.5">
                                    <input
                                        type="checkbox"
                                        checked={data.schedule_show_weekends}
                                        onChange={(e) => setData('schedule_show_weekends', e.target.checked)}
                                        className="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                                    />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{__('Show weekends')}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{__('Display Saturday and Sunday columns in the schedule view.')}</p>
                                </div>
                            </label>
                        </div>

                        {/* Realtime updates */}
                        <div>
                            <label className="form-label">{__('Realtime updates')}</label>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{__('How the planner receives live updates from other users.')}</p>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectCard
                                    value="polling"
                                    current={data.realtime_mode}
                                    onChange={(v) => setData('realtime_mode', v)}
                                    label={__('Polling')}
                                    desc={__('Checks for changes every few seconds (default)')}
                                />
                                <SelectCard
                                    value="off"
                                    current={data.realtime_mode}
                                    onChange={(v) => setData('realtime_mode', v)}
                                    label={__('Off')}
                                    desc={__('No automatic refresh — reload the page to see changes')}
                                />
                            </div>
                            {errors.realtime_mode && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.realtime_mode}</p>}
                        </div>
                    </div>
                )}

                {/* ═══ Security ═══ */}
                {tab === 'security' && (
                    <div className="space-y-6">
                        {/* Authentication */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Authentication')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">{__('Additional login methods for operators.')}</p>
                            <div className="space-y-4">
                                <label className="flex items-start gap-3 cursor-pointer">
                                    <div className="pt-0.5">
                                        <input
                                            type="checkbox"
                                            checked={data.pin_login_enabled}
                                            onChange={(e) => setData('pin_login_enabled', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">{__('Enable PIN login')}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {__('Allow users to set a 4–6 digit numeric PIN for quick sign-in. Each user must first configure their PIN in Settings (requires current password). PIN login does not replace password login — it is an alternative method.')}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {/* CORS */}
                        <div className="card">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('CORS (Cross-Origin Requests)')}</h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                {__('Control which external domains can make API requests to this application. Leave empty to block all cross-origin requests (most secure).')}
                            </p>
                            <div className="space-y-4">
                                <div>
                                    <label className="form-label" htmlFor="cors_allowed_origins">{__('Allowed Origins')}</label>
                                    <textarea
                                        id="cors_allowed_origins"
                                        rows={3}
                                        value={data.cors_allowed_origins}
                                        onChange={(e) => setData('cors_allowed_origins', e.target.value)}
                                        className="form-input w-full"
                                        placeholder={__('https://erp.yourcompany.com')}
                                    />
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {__('Comma-separated list of allowed origins. Only HTTPS URLs recommended. Leave empty to block all cross-origin requests.')}
                                    </p>
                                    {errors.cors_allowed_origins && <p className="text-red-600 dark:text-red-300 text-sm mt-1">{errors.cors_allowed_origins}</p>}
                                </div>
                                <div>
                                    <label className="form-label" htmlFor="cors_allowed_methods">{__('Allowed Methods')}</label>
                                    <input
                                        type="text"
                                        id="cors_allowed_methods"
                                        value={data.cors_allowed_methods}
                                        onChange={(e) => setData('cors_allowed_methods', e.target.value)}
                                        className="form-input w-full"
                                        placeholder="GET, POST"
                                    />
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {__('HTTP methods allowed for cross-origin requests. Default: GET, POST (minimal).')}
                                    </p>
                                </div>
                                <div>
                                    <label className="form-label" htmlFor="cors_max_age">{__('Preflight Cache (seconds)')}</label>
                                    <input
                                        type="number"
                                        id="cors_max_age"
                                        value={data.cors_max_age}
                                        onChange={(e) => setData('cors_max_age', parseInt(e.target.value, 10) || 0)}
                                        className="form-input w-32"
                                        min={0}
                                        max={86400}
                                        placeholder="0"
                                    />
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {__('How long browsers cache preflight responses. 0 = no caching (strictest).')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Save button — visible on all tabs except data */}
                {tab !== 'data' && (
                    <div className="flex justify-end">
                        <button type="submit" disabled={processing} className="btn-touch btn-primary">
                            {__('Save')}
                        </button>
                    </div>
                )}
            </form>

            {/* ═══ Data tab (outside form — has its own forms) ═══ */}
            {tab === 'data' && (
                <div className="space-y-8">
                    {/* Sample data */}
                    <div className="card border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
                        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Sample Data')}</h2>
                        <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">
                            {__('Load a pre-built demo dataset: lines, workstations, products, templates and work orders. Safe to run multiple times.')}
                        </p>
                        <form method="POST" action="/settings/sample-data">
                            <input type="hidden" name="_token" value={csrf_token} />
                            <div className="flex items-center gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={sampleConfirm}
                                        onChange={(e) => setSampleConfirm(e.target.checked)}
                                        className="rounded border-gray-300 dark:border-gray-600 text-amber-500"
                                    />
                                    {__('I understand this will add demo data to the system')}
                                </label>
                                <button
                                    type="submit"
                                    disabled={!sampleConfirm}
                                    className="btn-touch px-4 py-2 text-sm font-medium rounded-lg border border-amber-400 bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 hover:bg-amber-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                >
                                    {__('Load Sample Data')}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Export */}
                    <div>
                        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Export Settings')}</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            {__('Download complete system configuration as a JSON file. Includes lines, workstations, product types, templates, materials, shifts, and all settings. No production data or user accounts are exported.')}
                        </p>
                        <a
                            href="/settings/export"
                            className="btn-touch bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 inline-flex items-center gap-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {__('Export Settings (JSON)')}
                        </a>
                    </div>

                    {/* Import */}
                    <div>
                        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Import Settings')}</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            {__('Upload a previously exported configuration file. This will overwrite current configuration including lines, products, templates, materials, and settings. Production data (work orders, batches, issues) is never affected. Database credentials are never imported.')}
                        </p>
                        <form method="POST" action="/settings/import" encType="multipart/form-data" className="flex items-center gap-3">
                            <input type="hidden" name="_token" value={csrf_token} />
                            <input
                                type="file"
                                name="settings_file"
                                accept=".json,.txt"
                                required
                                className="text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
                            />
                            <button
                                type="submit"
                                className="btn-touch bg-blue-600 text-white hover:bg-blue-700 inline-flex items-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                {__('Import Settings')}
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

System.layout = (page) => <AppLayout>{page}</AppLayout>;
