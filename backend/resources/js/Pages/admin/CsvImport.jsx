import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

export default function CsvImport() {
    const {
        recentImports = [],
        savedMappings = [],
        systemFields = {},
        lines = [],
        productionPeriod = 'none',
        import_result: importResult = null,
        csrf_token: csrfToken,
    } = usePage().props;

    const [dragging, setDragging] = useState(false);
    const [filename, setFilename] = useState('');
    const [fileInput, setFileInput] = useState(null);

    const handleDrop = (e) => {
        e.preventDefault();
        setDragging(false);
        if (e.dataTransfer.files.length > 0 && fileInput) {
            // Assign files to input via DataTransfer
            try {
                fileInput.files = e.dataTransfer.files;
            } catch (_) { /* Safari workaround — just show filename */ }
            setFilename(e.dataTransfer.files[0]?.name || '');
        }
    };

    const statusBadge = (status) => {
        if (status === 'COMPLETED') return 'bg-green-100 text-green-800';
        if (status === 'FAILED') return 'bg-red-100 text-red-800';
        return 'bg-yellow-100 text-yellow-800';
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('CSV Import')} />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link href="/admin/dashboard" className="hover:text-gray-700">{__('Dashboard')}</Link>
                <span>/</span>
                <span className="text-gray-800 font-medium">{__('CSV Import')}</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800">{__('Import')}</h1>
                    <p className="text-gray-600 mt-1">{__('Import work orders from a CSV, XLS or XLSX file with custom column mapping')}</p>
                </div>
            </div>

            {/* Import result banner */}
            {importResult && (
                <div className={`card mb-6 border-l-4 ${importResult.failed === 0 ? 'border-green-500' : 'border-yellow-500'}`}>
                    <div className="flex items-start gap-4">
                        <div className={`${importResult.failed === 0 ? 'bg-green-100' : 'bg-yellow-100'} rounded-full p-3 flex-shrink-0`}>
                            {importResult.failed === 0 ? (
                                <Icon d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" className="w-6 h-6 text-green-600" />
                            ) : (
                                <Icon d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z" className="w-6 h-6 text-yellow-600" />
                            )}
                        </div>
                        <div className="flex-1">
                            <p className="font-bold text-gray-800 mb-1">
                                {importResult.failed === 0 ? __('Import Completed') : __('Import Completed with errors')}
                            </p>
                            <div className="flex gap-6 text-sm">
                                <span className="text-green-700 font-medium">&#10003; {__(':count imported', { count: importResult.success })}</span>
                                {importResult.failed > 0 && (
                                    <span className="text-red-700 font-medium">&#10007; {__(':count failed', { count: importResult.failed })}</span>
                                )}
                                <span className="text-gray-600">{__(':count total rows', { count: importResult.total })}</span>
                            </div>
                            {importResult.errors && importResult.errors.length > 0 && (
                                <details className="mt-3">
                                    <summary className="text-sm text-red-600 cursor-pointer">
                                        {__('Show errors (:count)', { count: importResult.errors.length })}
                                    </summary>
                                    <ul className="mt-2 text-xs text-red-700 space-y-1 bg-red-50 rounded p-3">
                                        {importResult.errors.map((err, i) => (
                                            <li key={i}>{err}</li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {/* Upload Form */}
                <div className="lg:col-span-2 card">
                    <h2 className="text-xl font-bold text-gray-800 mb-4">{__('Upload File')}</h2>
                    <form
                        method="POST"
                        action="/admin/csv-import/upload"
                        encType="multipart/form-data"
                    >
                        <input type="hidden" name="_token" value={csrfToken} />

                        {/* Drop zone */}
                        <div
                            className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors mb-6 cursor-pointer
                                ${dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'}`}
                            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={(e) => { e.preventDefault(); setDragging(false); }}
                            onDrop={handleDrop}
                            onClick={() => fileInput && fileInput.click()}
                        >
                            <Icon
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                className="mx-auto h-12 w-12 text-gray-400 mb-3"
                            />
                            <p className="text-gray-600 font-medium">
                                {__('Drop file here or')} <span className="text-blue-600">{__('browse')}</span>
                            </p>
                            <p className="text-sm text-gray-400 mt-1">{__('Max 32 MB')} &middot; .csv, .txt, .xlsx, .xls</p>
                            <div className="mt-3 flex items-center justify-center gap-3 text-xs">
                                <span className="text-gray-400">{__('Sample files:')}</span>
                                <a
                                    href="/samples/zlecenia-import.xlsx"
                                    download
                                    className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium hover:underline"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" className="w-3.5 h-3.5" />
                                    XLSX
                                </a>
                                <a
                                    href="/samples/zlecenia-import.csv"
                                    download
                                    className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium hover:underline"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" className="w-3.5 h-3.5" />
                                    CSV
                                </a>
                            </div>
                            <input
                                type="file"
                                name="csv_file"
                                ref={setFileInput}
                                accept=".csv,.txt,.xlsx,.xls"
                                className="hidden"
                                onChange={(e) => setFilename(e.target.files[0]?.name || '')}
                                required
                            />
                            {filename && (
                                <p className="mt-2 text-sm text-blue-700 font-medium">{filename}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="form-label">{__('Duplicate Strategy')}</label>
                                <select name="import_strategy" className="form-input w-full" required>
                                    <option value="update_or_create">{__('Update if exists, create if new')}</option>
                                    <option value="skip_existing">{__('Skip existing records')}</option>
                                    <option value="error_on_duplicate">{__('Error on duplicates')}</option>
                                </select>
                            </div>
                            <div>
                                <label className="form-label">{__('Load Mapping Profile (optional)')}</label>
                                <select name="mapping_id" className="form-input w-full">
                                    <option value="">{__('— Map columns manually —')}</option>
                                    {savedMappings.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}{m.is_default ? ` (${__('default')})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Target line */}
                        <div className="mb-4">
                            <label className="form-label">{__('Assign all rows to Production Line (optional)')}</label>
                            <select name="target_line_id" className="form-input w-full">
                                <option value="">{__('— Use line_code column from file —')}</option>
                                {lines.map((line) => (
                                    <option key={line.id} value={line.id}>{line.name}</option>
                                ))}
                            </select>
                            <p className="text-xs text-gray-400 mt-1">
                                {__('If selected, every imported work order will be assigned to this line, overriding any line_code column in the file.')}
                            </p>
                        </div>

                        {/* Planning period fields */}
                        {productionPeriod !== 'none' && (
                            <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p className="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-2">
                                    {__('Planning Period')}
                                    <span className="font-normal normal-case">
                                        {' '}— {__('system is configured for :period production split', { period: __(productionPeriod.charAt(0).toUpperCase() + productionPeriod.slice(1)) })}
                                    </span>
                                </p>
                                <div className="grid grid-cols-2 gap-3">
                                    {productionPeriod === 'weekly' ? (
                                        <div>
                                            <label className="form-label text-xs">{__('Week Number (1–53)')}</label>
                                            <input
                                                type="number"
                                                name="import_week"
                                                min="1"
                                                max="53"
                                                className="form-input w-full"
                                                placeholder={__('e.g. current week')}
                                            />
                                        </div>
                                    ) : (
                                        <div>
                                            <label className="form-label text-xs">{__('Month Number (1–12)')}</label>
                                            <input
                                                type="number"
                                                name="import_month"
                                                min="1"
                                                max="12"
                                                className="form-input w-full"
                                                placeholder={__('e.g. current month')}
                                            />
                                        </div>
                                    )}
                                    <div>
                                        <label className="form-label text-xs">{__('Year')}</label>
                                        <input
                                            type="number"
                                            name="production_year"
                                            min="2000"
                                            max="2100"
                                            className="form-input w-full"
                                            defaultValue={new Date().getFullYear()}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        <button type="submit" className="btn-touch btn-primary w-full">
                            <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" className="w-5 h-5 inline-block mr-2" />
                            {__('Upload & Configure Mapping')}
                        </button>
                    </form>

                    {/* Field Reference */}
                    <details className="mt-6">
                        <summary className="text-sm font-medium text-gray-600 cursor-pointer hover:text-gray-800">
                            {__('Available system fields reference')}
                        </summary>
                        <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {Object.entries(systemFields).map(([key, label]) => (
                                <div key={key} className="flex items-center gap-2 text-xs bg-gray-50 rounded p-2">
                                    <code className="text-blue-700 font-mono shrink-0">{key}</code>
                                    <span className="text-gray-600">{__(label)}</span>
                                    {(key === 'order_no' || key === 'quantity') && (
                                        <span className="ml-auto text-red-500 font-bold shrink-0">{__('required')}</span>
                                    )}
                                </div>
                            ))}
                            <div className="flex items-center gap-2 text-xs bg-purple-50 rounded p-2 sm:col-span-2">
                                <code className="text-purple-700 font-mono shrink-0">custom:field_name</code>
                                <span className="text-gray-600">{__('Any extra field — stored as JSON on the work order')}</span>
                            </div>
                        </div>
                    </details>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Saved Mapping Profiles */}
                    <div className="card">
                        <h2 className="text-lg font-bold text-gray-800 mb-3">{__('Saved Mapping Profiles')}</h2>
                        {savedMappings.length === 0 ? (
                            <p className="text-sm text-gray-500">{__('No saved profiles yet. Profiles are saved during import.')}</p>
                        ) : (
                            savedMappings.map((m) => {
                                const colCount = Object.keys(m.mapping_config?.column_mappings ?? {}).length;
                                return (
                                    <div key={m.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                        <div>
                                            <p className="text-sm font-medium text-gray-800">{m.name}</p>
                                            <p className="text-xs text-gray-500">
                                                {colCount === 1 ? __(':count column mapped', { count: colCount }) : __(':count columns mapped', { count: colCount })}
                                            </p>
                                        </div>
                                        {!m.is_default && (
                                            <form
                                                method="POST"
                                                action={`/admin/csv-import/mappings/${m.id}`}
                                                onSubmit={(e) => !window.confirm(__('Delete mapping profile?')) && e.preventDefault()}
                                            >
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-red-400 hover:text-red-600 p-1">
                                                    <Icon d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" className="w-4 h-4" />
                                                </button>
                                            </form>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {/* Recent Imports */}
                    <div className="card">
                        <h2 className="text-lg font-bold text-gray-800 mb-3">{__('Recent Imports')}</h2>
                        {recentImports.length === 0 ? (
                            <p className="text-sm text-gray-500">{__('No imports yet.')}</p>
                        ) : (
                            recentImports.map((imp) => (
                                <div key={imp.id} className="py-2 border-b border-gray-100 last:border-0">
                                    <div className="flex items-center justify-between mb-1">
                                        <p className="text-xs text-gray-600 truncate max-w-[140px]" title={imp.filename}>
                                            {imp.filename}
                                        </p>
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusBadge(imp.status)}`}>
                                            {__(imp.status)}
                                        </span>
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        <span className="text-green-600">&#10003; {imp.successful_rows}</span> /{' '}
                                        {__(':count rows', { count: imp.total_rows })}
                                        {imp.failed_rows > 0 && (
                                            <> &middot; <span className="text-red-600">&#10007; {imp.failed_rows}</span></>
                                        )}
                                        {imp.created_at_human && <> &middot; {imp.created_at_human}</>}
                                    </p>
                                    {imp.error_log && imp.error_log.length > 0 && (
                                        <details className="mt-1">
                                            <summary className="text-xs text-red-600 cursor-pointer hover:text-red-800">
                                                {__('Show errors (:count)', { count: imp.error_log.length })}
                                            </summary>
                                            <ul className="mt-1 space-y-0.5 bg-red-50 rounded p-2 max-h-40 overflow-y-auto">
                                                {imp.error_log.map((err, i) => (
                                                    <li key={i} className="text-xs text-red-700 font-mono break-all">{err}</li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>

            </div>
        </div>
    );
}

CsvImport.layout = (page) => <AppLayout>{page}</AppLayout>;
