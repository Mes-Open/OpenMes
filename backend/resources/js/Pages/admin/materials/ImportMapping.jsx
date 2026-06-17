import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

const AUTO_MAP_RULES = {
    symbol:           'external_code',
    kod:              'external_code',
    code:             'code',
    indeks:           'external_code',
    nazwa:            'name',
    name:             'name',
    opis:             'description',
    description:      'description',
    jm:               'unit_of_measure',
    'j.m.':           'unit_of_measure',
    unit:             'unit_of_measure',
    jednostka:        'unit_of_measure',
    ean:              'ean',
    'kod kreskowy':   'ean',
    barcode:          'ean',
    stan:             'stock_quantity',
    ilosc:            'stock_quantity',
    'ilość':          'stock_quantity',
    stock:            'stock_quantity',
    quantity:         'stock_quantity',
    'stan magazynowy': 'stock_quantity',
    cena:             'unit_price',
    'cena netto':     'unit_price',
    price:            'unit_price',
    dostawca:         'supplier_name',
    supplier:         'supplier_name',
    kontrahent:       'supplier_name',
    typ:              'material_type',
    type:             'material_type',
    kategoria:        'material_type',
    category:         'material_type',
    grupa:            'material_type',
    waluta:           'price_currency',
    currency:         'price_currency',
    min:              'min_stock_level',
    minimum:          'min_stock_level',
    'min stock':      'min_stock_level',
};

function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

function autoDetect(headers) {
    const result = {};
    for (const h of headers) {
        const norm = h.toLowerCase().trim();
        for (const [pattern, target] of Object.entries(AUTO_MAP_RULES)) {
            if (norm === pattern || norm.includes(pattern)) {
                result[h] = target;
                break;
            }
        }
        if (!result[h]) result[h] = '_ignore';
    }
    return result;
}

export default function MaterialsImportMapping() {
    const {
        headers = [],
        previewRows = [],
        totalRows = 0,
        path = '',
        systemFields = {},
        importStrategy = 'update_or_create',
        externalSystem = '',
        csrf_token: csrfToken,
    } = usePage().props;

    const [mappings, setMappings] = useState(() => autoDetect(headers));

    // Re-run auto-detect if headers change (shouldn't happen, but defensive)
    useEffect(() => {
        setMappings(autoDetect(headers));
    }, [headers.join(',')]);

    const setMapping = (header, value) => {
        setMappings((prev) => ({ ...prev, [header]: value }));
    };

    const handleAutoMap = () => setMappings(autoDetect(headers));
    const handleClearAll = () => {
        const cleared = {};
        for (const h of headers) cleared[h] = '_ignore';
        setMappings(cleared);
    };

    const strategyDescription = {
        update_or_create: 'Create & Update — new materials will be created, existing ones updated with new data.',
        create_only:      'Create Only — existing materials will be skipped.',
        skip_existing:    'Update Only — only existing materials will be updated, new ones skipped.',
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Map Material Columns" />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-om-muted mb-6">
                <Link href="/admin/dashboard" className="hover:text-om-ink">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/materials" className="hover:text-om-ink">Materials</Link>
                <span>/</span>
                <Link href="/admin/materials/import" className="hover:text-om-ink">Import</Link>
                <span>/</span>
                <span className="text-om-ink font-medium">Map Columns</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-om-ink">Map Columns</h1>
                    <p className="text-om-muted mt-1">
                        Assign each column to a material field.{' '}
                        <span className="font-medium text-om-accent">{totalRows} rows</span> to import
                        {externalSystem && (
                            <> &middot; Source: <span className="font-medium">{externalSystem}</span></>
                        )}
                    </p>
                </div>
                <Link href="/admin/materials/import" className="btn-touch btn-secondary text-sm">Back</Link>
            </div>

            <form method="POST" action="/admin/materials/import/process">
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="file_path" value={path} />
                <input type="hidden" name="import_strategy" value={importStrategy} />
                <input type="hidden" name="external_system" value={externalSystem} />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Column Mapping */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="card">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-xl font-bold text-om-ink">Column Mapping</h2>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={handleAutoMap}
                                        className="text-xs text-om-accent hover:text-om-accent underline"
                                    >
                                        Auto-detect
                                    </button>
                                    <span className="text-om-faintest">|</span>
                                    <button
                                        type="button"
                                        onClick={handleClearAll}
                                        className="text-xs text-om-blocked hover:text-om-blocked underline"
                                    >
                                        Clear all
                                    </button>
                                </div>
                            </div>

                            <div className="space-y-3">
                                {headers.map((h) => {
                                    const sampleVal = previewRows[0]?.[h] ?? '';
                                    return (
                                        <div key={h} className="flex items-center gap-3 p-3 bg-om-panel rounded-om-sm">
                                            <div className="w-1/3 min-w-0">
                                                <span
                                                    className="text-sm font-mono font-medium text-om-ink truncate block"
                                                    title={h}
                                                >
                                                    {h}
                                                </span>
                                                {sampleVal && (
                                                    <span className="text-xs text-om-faint truncate block">
                                                        e.g. {sampleVal.length > 40 ? sampleVal.slice(0, 40) + '…' : sampleVal}
                                                    </span>
                                                )}
                                            </div>
                                            <Icon
                                                d="M14 5l7 7m0 0l-7 7m7-7H3"
                                                className="w-5 h-5 text-om-faint shrink-0"
                                            />
                                            <div className="flex-1">
                                                <input
                                                    type="hidden"
                                                    name={`mapping[${h}]`}
                                                    value={mappings[h] ?? '_ignore'}
                                                />
                                                <Dropdown
                                                    className="w-full"
                                                    options={Object.entries(systemFields).map(([val, label]) => ({ value: String(val), label }))}
                                                    value={mappings[h] == null ? '_ignore' : String(mappings[h])}
                                                    onChange={(v) => setMapping(h, v)}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Data preview */}
                        <div className="card">
                            <h2 className="text-lg font-semibold text-om-ink mb-3">
                                Data Preview (first {previewRows.length} rows)
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr>
                                            {headers.map((h) => (
                                                <th
                                                    key={h}
                                                    className="px-3 py-2 text-left text-xs font-medium text-om-muted uppercase bg-om-panel whitespace-nowrap"
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-om-line2">
                                        {previewRows.map((row, ri) => (
                                            <tr key={ri}>
                                                {headers.map((h) => (
                                                    <td
                                                        key={h}
                                                        className="px-3 py-2 whitespace-nowrap text-om-muted max-w-[200px] truncate"
                                                    >
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
                    <div className="lg:col-span-1 space-y-4">
                        <div className="card">
                            <h3 className="text-sm font-semibold text-om-ink mb-3">Required Fields</h3>
                            <ul className="text-sm text-om-muted space-y-1">
                                <li><span className="text-om-blocked">*</span> <strong>Name</strong> — material name</li>
                                <li><span className="text-om-blocked">*</span> <strong>Code</strong> or <strong>External Code</strong> — for identification</li>
                            </ul>
                        </div>

                        <div className="card">
                            <h3 className="text-sm font-semibold text-om-ink mb-3">Strategy</h3>
                            <p className="text-sm text-om-muted">
                                {strategyDescription[importStrategy] ?? importStrategy}
                            </p>
                        </div>

                        <div className="sticky top-4">
                            <button type="submit" className="btn-touch btn-primary w-full text-center">
                                <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" className="w-5 h-5 inline-block mr-2" />
                                Import {totalRows} Materials
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
}

MaterialsImportMapping.layout = (page) => <AppLayout>{page}</AppLayout>;
