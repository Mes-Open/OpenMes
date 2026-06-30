import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
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
    const [importStrategy, setImportStrategy] = useState('update_or_create');
    const [mappingId, setMappingId] = useState('');
    const [targetLineId, setTargetLineId] = useState('');

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
        if (status === 'COMPLETED') return 'bg-om-running-bg text-om-running';
        if (status === 'FAILED') return 'bg-om-blocked-bg text-om-blocked';
        return 'bg-om-downtime-bg text-om-downtime';
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('CSV Import')} />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-om-muted mb-6">
                <Link href="/admin/dashboard" className="hover:text-om-ink">{__('Dashboard')}</Link>
                <span>/</span>
                <span className="text-om-ink font-medium">{__('CSV Import')}</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-om-ink">{__('Import')}</h1>
                    <p className="text-om-muted mt-1">{__('Import work orders from a CSV, XLS or XLSX file with custom column mapping')}</p>
                </div>
            </div>

            {/* Import result banner */}
            {importResult && (
                <div className={`card mb-6 border-l-4 ${importResult.failed === 0 ? 'border-om-running' : 'border-yellow-500'}`}>
                    <div className="flex items-start gap-4">
                        <div className={`${importResult.failed === 0 ? 'bg-om-running-bg' : 'bg-om-downtime-bg'} rounded-full p-3 flex-shrink-0`}>
                            {importResult.failed === 0 ? (
                                <Icon d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" className="w-6 h-6 text-om-running" />
                            ) : (
                                <Icon d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z" className="w-6 h-6 text-om-downtime" />
                            )}
                        </div>
                        <div className="flex-1">
                            <p className="font-bold text-om-ink mb-1">
                                {importResult.failed === 0 ? __('Import Completed') : __('Import Completed with errors')}
                            </p>
                            <div className="flex gap-6 text-sm">
                                <span className="text-om-running font-medium">&#10003; {importResult.success} imported</span>
                                {importResult.failed > 0 && (
                                    <span className="text-om-blocked font-medium">&#10007; {importResult.failed} failed</span>
                                )}
                                <span className="text-om-muted">{importResult.total} total rows</span>
                            </div>
                            {importResult.errors && importResult.errors.length > 0 && (
                                <details className="mt-3">
                                    <summary className="text-sm text-om-blocked cursor-pointer">
                                        Show errors ({importResult.errors.length})
                                    </summary>
                                    <ul className="mt-2 text-xs text-om-blocked space-y-1 bg-om-blocked-bg rounded p-3">
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
                    <h2 className="text-xl font-bold text-om-ink mb-4">{__('Upload File')}</h2>
                    <form
                        method="POST"
                        action="/admin/csv-import/upload"
                        encType="multipart/form-data"
                    >
                        <input type="hidden" name="_token" value={csrfToken} />

                        {/* Drop zone */}
                        <div
                            className={`border-2 border-dashed rounded-om p-8 text-center transition-colors mb-6 cursor-pointer
                                ${dragging ? 'border-om-accent bg-om-chip' : 'border-om-line hover:border-om-faintest'}`}
                            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={(e) => { e.preventDefault(); setDragging(false); }}
                            onDrop={handleDrop}
                            onClick={() => fileInput && fileInput.click()}
                        >
                            <Icon
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                className="mx-auto h-12 w-12 text-om-faint mb-3"
                            />
                            <p className="text-om-muted font-medium">
                                {__('Drop file here or')} <span className="text-om-accent">{__('browse')}</span>
                            </p>
                            <p className="text-sm text-om-faint mt-1">Max 32 MB &middot; .csv, .txt, .xlsx, .xls</p>
                            <div className="mt-3 flex items-center justify-center gap-3 text-xs">
                                <span className="text-om-faint">{__('Sample files:')}</span>
                                <a
                                    href="/samples/zlecenia-import.xlsx"
                                    download
                                    className="inline-flex items-center gap-1 text-om-accent hover:text-om-accent font-medium hover:underline"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <Icon d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" className="w-3.5 h-3.5" />
                                    XLSX
                                </a>
                                <a
                                    href="/samples/zlecenia-import.csv"
                                    download
                                    className="inline-flex items-center gap-1 text-om-accent hover:text-om-accent font-medium hover:underline"
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
                                <p className="mt-2 text-sm text-om-accent font-medium">{filename}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="form-label">{__('Duplicate Strategy')}</label>
                                <input type="hidden" name="import_strategy" value={importStrategy} />
                                <Dropdown
                                    options={[
                                        { value: 'update_or_create', label: __('Update if exists, create if new') },
                                        { value: 'skip_existing', label: __('Skip existing records') },
                                        { value: 'error_on_duplicate', label: __('Error on duplicates') },
                                    ]}
                                    value={importStrategy}
                                    onChange={(v) => setImportStrategy(v)}
                                    className="w-full"
                                />
                            </div>
                            <div>
                                <label className="form-label">{__('Load Mapping Profile (optional)')}</label>
                                <input type="hidden" name="mapping_id" value={mappingId} />
                                <Dropdown
                                    options={[
                                        { value: '', label: __('— Map columns manually —') },
                                        ...savedMappings.map((m) => ({
                                            value: String(m.id),
                                            label: `${m.name}${m.is_default ? ' (default)' : ''}`,
                                        })),
                                    ]}
                                    value={mappingId == null ? '' : String(mappingId)}
                                    onChange={(v) => setMappingId(v)}
                                    className="w-full"
                                />
                            </div>
                        </div>

                        {/* Target line */}
                        <div className="mb-4">
                            <label className="form-label">{__('Assign all rows to Production Line (optional)')}</label>
                            <input type="hidden" name="target_line_id" value={targetLineId} />
                            <Dropdown
                                options={[
                                    { value: '', label: __('— Use line_code column from file —') },
                                    ...lines.map((line) => ({ value: String(line.id), label: line.name })),
                                ]}
                                value={targetLineId == null ? '' : String(targetLineId)}
                                onChange={(v) => setTargetLineId(v)}
                                className="w-full"
                            />
                            <p className="text-xs text-om-faint mt-1">
                                {__('If selected, every imported work order will be assigned to this line, overriding any line_code column in the file.')}
                            </p>
                        </div>

                        {/* Planning period fields */}
                        {productionPeriod !== 'none' && (
                            <div className="mb-4 p-3 bg-om-chip border border-om-line rounded-om-sm">
                                <p className="text-xs font-semibold text-om-accent uppercase tracking-wide mb-2">
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
                        <summary className="text-sm font-medium text-om-muted cursor-pointer hover:text-om-ink">
                            {__('Available system fields reference')}
                        </summary>
                        <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {Object.entries(systemFields).map(([key, label]) => (
                                <div key={key} className="flex items-center gap-2 text-xs bg-om-panel rounded p-2">
                                    <code className="text-om-accent font-mono shrink-0">{key}</code>
                                    <span className="text-om-muted">{label}</span>
                                    {(key === 'order_no' || key === 'quantity') && (
                                        <span className="ml-auto text-om-blocked font-bold shrink-0">{__('required')}</span>
                                    )}
                                </div>
                            ))}
                            <div className="flex items-center gap-2 text-xs bg-om-chip rounded p-2 sm:col-span-2">
                                <code className="text-purple-700 font-mono shrink-0">custom:field_name</code>
                                <span className="text-om-muted">{__('Any extra field — stored as JSON on the work order')}</span>
                            </div>
                        </div>
                    </details>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Saved Mapping Profiles */}
                    <div className="card">
                        <h2 className="text-lg font-bold text-om-ink mb-3">{__('Saved Mapping Profiles')}</h2>
                        {savedMappings.length === 0 ? (
                            <p className="text-sm text-om-muted">{__('No saved profiles yet. Profiles are saved during import.')}</p>
                        ) : (
                            savedMappings.map((m) => {
                                const colCount = Object.keys(m.mapping_config?.column_mappings ?? {}).length;
                                return (
                                    <div key={m.id} className="flex items-center justify-between py-2 border-b border-om-line2 last:border-0">
                                        <div>
                                            <p className="text-sm font-medium text-om-ink">{m.name}</p>
                                            <p className="text-xs text-om-muted">
                                                {colCount} column{colCount !== 1 ? 's' : ''} mapped
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
                                                <button type="submit" className="text-red-400 hover:text-om-blocked p-1">
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
                        <h2 className="text-lg font-bold text-om-ink mb-3">{__('Recent Imports')}</h2>
                        {recentImports.length === 0 ? (
                            <p className="text-sm text-om-muted">{__('No imports yet.')}</p>
                        ) : (
                            recentImports.map((imp) => (
                                <div key={imp.id} className="py-2 border-b border-om-line2 last:border-0">
                                    <div className="flex items-center justify-between mb-1">
                                        <p className="text-xs text-om-muted truncate max-w-[140px]" title={imp.filename}>
                                            {imp.filename}
                                        </p>
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusBadge(imp.status)}`}>
                                            {__(imp.status)}
                                        </span>
                                    </div>
                                    <p className="text-xs text-om-muted">
                                        <span className="text-om-running">&#10003; {imp.successful_rows}</span> /{' '}
                                        {imp.total_rows} rows
                                        {imp.failed_rows > 0 && (
                                            <> &middot; <span className="text-om-blocked">&#10007; {imp.failed_rows}</span></>
                                        )}
                                        {imp.created_at_human && <> &middot; {imp.created_at_human}</>}
                                    </p>
                                    {imp.error_log && imp.error_log.length > 0 && (
                                        <details className="mt-1">
                                            <summary className="text-xs text-om-blocked cursor-pointer hover:text-om-blocked">
                                                Show errors ({imp.error_log.length})
                                            </summary>
                                            <ul className="mt-1 space-y-0.5 bg-om-blocked-bg rounded p-2 max-h-40 overflow-y-auto">
                                                {imp.error_log.map((err, i) => (
                                                    <li key={i} className="text-xs text-om-blocked font-mono break-all">{err}</li>
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
