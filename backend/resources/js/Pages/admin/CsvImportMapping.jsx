import { useState, useEffect, useRef } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const AUTO_DETECT_MAP = {
    order_no:          ['order_no', 'order no', 'orderno', 'order number', 'order_number', 'wo_no', 'work_order', 'wo no'],
    product_name:      ['product_name', 'product name', 'productname', 'product', 'item', 'item name', 'description product'],
    quantity:          ['quantity', 'qty', 'planned_qty', 'planned qty', 'amount'],
    line_code:         ['line_code', 'line code', 'linecode', 'line', 'production_line'],
    product_type_code: ['product_type_code', 'product type code', 'product_type', 'product type', 'type code', 'type'],
    priority:          ['priority', 'prio'],
    due_date:          ['due_date', 'due date', 'duedate', 'deadline', 'target date', 'delivery_date'],
    description:       ['description', 'desc', 'notes', 'comment', 'remarks'],
};

function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

// Per-header mapping row state: { select: string, customKey: string }
function buildInitialMappings(headers, prevMapping) {
    const result = {};
    for (const h of headers) {
        const raw = prevMapping?.[h] ?? '_ignore';
        const isCustom = raw.startsWith('custom:');
        result[h] = {
            select: isCustom ? '__custom__' : raw,
            customKey: isCustom ? raw.slice(7) : '',
        };
    }
    return result;
}

export default function CsvImportMapping() {
    const {
        headers = [],
        previewRows = [],
        totalRows = 0,
        path = '',
        savedMappings = [],
        systemFields = {},
        existingMapping = null,
        importStrategy = 'update_or_create',
        targetLineId = '',
        importWeek = '',
        importMonth = '',
        productionYear = new Date().getFullYear(),
        prevMapping = null,
        mappingError = null,
        csrf_token: csrfToken,
    } = usePage().props;

    const initialPrev = prevMapping ?? existingMapping?.mapping_config?.column_mappings ?? null;
    const [mappings, setMappings] = useState(() => buildInitialMappings(headers, initialPrev));
    const [saveMappingEnabled, setSaveMappingEnabled] = useState(false);
    const formRef = useRef(null);

    // Count of non-ignored mappings
    const mappedCount = Object.values(mappings).filter(
        (m) => m.select && m.select !== '_ignore'
    ).length;

    const setField = (header, field, value) => {
        setMappings((prev) => ({
            ...prev,
            [header]: { ...prev[header], [field]: value },
        }));
    };

    const autoMap = () => {
        setMappings((prev) => {
            const next = { ...prev };
            for (const h of headers) {
                const norm = h.toLowerCase().trim();
                for (const [field, aliases] of Object.entries(AUTO_DETECT_MAP)) {
                    if (aliases.includes(norm)) {
                        next[h] = { select: field, customKey: '' };
                        break;
                    }
                }
            }
            return next;
        });
    };

    const clearAll = () => {
        setMappings(() => buildInitialMappings(headers, null));
    };

    const loadProfile = (columnMappings) => {
        setMappings(() => buildInitialMappings(headers, columnMappings));
    };

    // On submit resolve __custom__ → custom:key hidden inputs
    const handleSubmit = (e) => {
        const form = e.target;
        // Remove previously injected hidden inputs
        form.querySelectorAll('input[data-custom-resolved]').forEach((el) => el.remove());

        for (const h of headers) {
            const m = mappings[h];
            if (m.select === '__custom__') {
                const key = (m.customKey || '').trim();
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `mapping[${h}]`;
                hidden.value = key ? `custom:${key}` : '_ignore';
                hidden.dataset.customResolved = '1';
                form.appendChild(hidden);
            }
        }
        // Let the form submit naturally
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Map Columns')} />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link href="/admin/dashboard" className="hover:text-gray-700">{__('Dashboard')}</Link>
                <span>/</span>
                <Link href="/admin/csv-import" className="hover:text-gray-700">{__('CSV Import')}</Link>
                <span>/</span>
                <span className="text-gray-800 font-medium">{__('Map Columns')}</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800">{__('Map Columns')}</h1>
                    <p className="text-gray-600 mt-1">
                        {__('Assign each CSV column to a system field or a custom key.')}{' '}
                        <span className="font-medium text-blue-700">{__(':count rows to import', { count: totalRows })}</span> &middot;
                        {__('Strategy')}: <span className="font-medium">{__(importStrategy.replace(/_/g, ' '))}</span>
                    </p>
                </div>
                <Link href="/admin/csv-import" className="btn-touch btn-secondary text-sm">
                    &larr; {__('Back')}
                </Link>
            </div>

            {/* Server-side mapping validation error */}
            {mappingError && (
                <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                    <Icon d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z" className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                    <p className="text-sm text-red-700">{mappingError}</p>
                </div>
            )}

            <form
                method="POST"
                action="/admin/csv-import/process"
                ref={formRef}
                onSubmit={handleSubmit}
            >
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="file_path" value={path} />
                <input type="hidden" name="import_strategy" value={importStrategy} />
                <input type="hidden" name="target_line_id" value={targetLineId ?? ''} />
                <input type="hidden" name="import_week" value={importWeek ?? ''} />
                <input type="hidden" name="import_month" value={importMonth ?? ''} />
                <input type="hidden" name="production_year" value={productionYear ?? new Date().getFullYear()} />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Column Mapping */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="card">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-xl font-bold text-gray-800">{__('Column Mapping')}</h2>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-500">{__('Quick-fill:')}</span>
                                    <button type="button" onClick={autoMap} className="text-xs text-blue-600 hover:text-blue-800 underline">
                                        {__('Auto-detect')}
                                    </button>
                                    <span className="text-gray-300">|</span>
                                    <button type="button" onClick={clearAll} className="text-xs text-red-500 hover:text-red-700 underline">
                                        {__('Clear all')}
                                    </button>
                                </div>
                            </div>

                            <div className="space-y-3">
                                {headers.map((h) => {
                                    const m = mappings[h] || { select: '_ignore', customKey: '' };
                                    const sampleVal = previewRows[0]?.[h] ?? '—';
                                    const isRequired = m.select === 'order_no' || m.select === 'quantity';
                                    return (
                                        <div key={h} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                            {/* CSV column name */}
                                            <div className="flex-shrink-0 w-40">
                                                <p className="text-sm font-mono font-medium text-gray-800 truncate" title={h}>{h}</p>
                                                <p className="text-xs text-gray-400">{__('CSV column')}</p>
                                            </div>

                                            {/* Arrow */}
                                            <div className="flex-shrink-0 pt-2 text-gray-400">&rarr;</div>

                                            {/* Target field selector */}
                                            <div className="flex-1 min-w-0">
                                                <select
                                                    name={m.select !== '__custom__' ? `mapping[${h}]` : undefined}
                                                    className="form-input w-full text-sm"
                                                    value={m.select}
                                                    onChange={(e) => setField(h, 'select', e.target.value)}
                                                >
                                                    <option value="_ignore">{__('— Ignore this column —')}</option>
                                                    <optgroup label={__('System Fields')}>
                                                        {Object.entries(systemFields).map(([key, label]) => (
                                                            <option key={key} value={key}>
                                                                {__(label)}{(key === 'order_no' || key === 'quantity') ? ` (${__('required')})` : ''}
                                                            </option>
                                                        ))}
                                                    </optgroup>
                                                    <optgroup label={__('Custom Field')}>
                                                        <option value="__custom__">{__('Custom key')}&hellip;</option>
                                                    </optgroup>
                                                </select>

                                                {/* Custom key input */}
                                                {m.select === '__custom__' && (
                                                    <div className="mt-2">
                                                        <input
                                                            type="text"
                                                            className="form-input w-full text-sm"
                                                            placeholder={__('e.g. batch_code, color, weight_kg')}
                                                            value={m.customKey}
                                                            onChange={(e) => setField(h, 'customKey', e.target.value)}
                                                        />
                                                        <p className="text-xs text-gray-400 mt-1">
                                                            {__('Stored as')} <code className="text-purple-700">custom:your_key</code>
                                                        </p>
                                                    </div>
                                                )}

                                                {isRequired && (
                                                    <div className="mt-1">
                                                        <span className="text-xs text-red-600 font-medium">{__('required field')}</span>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Sample value */}
                                            <div className="flex-shrink-0 w-32 hidden md:block">
                                                <p className="text-xs text-gray-400 mb-1">{__('Sample')}</p>
                                                <p className="text-xs text-gray-600 font-mono truncate" title={sampleVal}>{sampleVal}</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Data Preview */}
                        <div className="card overflow-hidden">
                            <h2 className="text-lg font-bold text-gray-800 mb-3">
                                {__('Data Preview')}{' '}
                                <span className="text-sm font-normal text-gray-500">({__('first :count rows', { count: previewRows.length })})</span>
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead>
                                        <tr className="bg-gray-100">
                                            {headers.map((h) => (
                                                <th key={h} className="px-3 py-2 text-left font-mono text-gray-700 whitespace-nowrap">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {previewRows.map((row, ri) => (
                                            <tr key={ri} className="hover:bg-gray-50">
                                                {headers.map((h) => (
                                                    <td key={h} className="px-3 py-2 text-gray-600 whitespace-nowrap max-w-[140px] truncate" title={row[h] ?? ''}>
                                                        {row[h] ?? ''}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">

                        {/* Load Mapping */}
                        {savedMappings.length > 0 && (
                            <div className="card">
                                <h3 className="text-base font-bold text-gray-800 mb-3">{__('Load Saved Profile')}</h3>
                                <div className="space-y-2">
                                    {savedMappings.map((m) => {
                                        const cols = Object.keys(m.mapping_config?.column_mappings ?? {}).length;
                                        return (
                                            <button
                                                key={m.id}
                                                type="button"
                                                onClick={() => loadProfile(m.mapping_config?.column_mappings ?? {})}
                                                className="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:border-blue-400 hover:bg-blue-50 transition-colors"
                                            >
                                                <p className="text-sm font-medium text-gray-800">
                                                    {m.name}{m.is_default ? ' ✓' : ''}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {cols === 1 ? __(':count column mapped', { count: cols }) : __(':count columns mapped', { count: cols })}
                                                </p>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Save Mapping */}
                        <div className="card">
                            <h3 className="text-base font-bold text-gray-800 mb-3">{__('Save Mapping Profile')}</h3>
                            <label className="flex items-center gap-2 cursor-pointer mb-3">
                                <input
                                    type="checkbox"
                                    className="rounded border-gray-300"
                                    checked={saveMappingEnabled}
                                    onChange={(e) => setSaveMappingEnabled(e.target.checked)}
                                />
                                <span className="text-sm text-gray-700">{__('Save this mapping for later')}</span>
                            </label>
                            {saveMappingEnabled && (
                                <input
                                    type="text"
                                    name="save_mapping_name"
                                    className="form-input w-full text-sm"
                                    placeholder={__('Profile name (e.g. ERP Export)')}
                                    maxLength={100}
                                />
                            )}
                        </div>

                        {/* Import Summary */}
                        <div className="card">
                            <h3 className="text-base font-bold text-gray-800 mb-3">{__('Import Summary')}</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">{__('Total rows:')}</span>
                                    <span className="font-medium">{totalRows}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">{__('Strategy:')}</span>
                                    <span className="font-medium capitalize">{__(importStrategy.replace(/_/g, ' '))}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">{__('Columns:')}</span>
                                    <span className="font-medium">{headers.length}</span>
                                </div>
                                <div className="border-t pt-2 flex justify-between">
                                    <span className="text-gray-600">{__('Mapped:')}</span>
                                    <span className="font-medium">{mappedCount}</span>
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <button type="submit" className="btn-touch btn-primary w-full">
                            <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" className="w-5 h-5 inline-block mr-2" />
                            {__('Run Import (:count rows)', { count: totalRows })}
                        </button>
                    </div>

                </div>
            </form>
        </div>
    );
}

CsvImportMapping.layout = (page) => <AppLayout>{page}</AppLayout>;
