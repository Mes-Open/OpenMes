import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

export default function MaterialsImport() {
    const {
        import_result: importResult = null,
        flash = {},
        csrf_token: csrfToken,
    } = usePage().props;

    return (
        <div className="max-w-5xl mx-auto">
            <Head title="Import Materials" />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-om-muted mb-6">
                <Link href="/admin/dashboard" className="hover:text-om-ink">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/materials" className="hover:text-om-ink">Materials</Link>
                <span>/</span>
                <span className="text-om-ink font-medium">Import</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-om-ink">Import Materials</h1>
                    <p className="text-om-muted mt-1">
                        Import materials from CSV, XLS or XLSX file (e.g. Subiekt GT export)
                    </p>
                </div>
                <Link href="/admin/materials" className="btn-touch btn-secondary">
                    Back to Materials
                </Link>
            </div>

            {/* Import result banner */}
            {importResult && (
                <div className={`card mb-6 border-l-4 ${!importResult.errors?.length ? 'border-om-running' : 'border-yellow-500'}`}>
                    <div className="flex items-start gap-4">
                        <div className={`${!importResult.errors?.length ? 'bg-om-running-bg' : 'bg-om-downtime-bg'} rounded-full p-3 flex-shrink-0`}>
                            {!importResult.errors?.length ? (
                                <Icon d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" className="w-6 h-6 text-om-running" />
                            ) : (
                                <Icon d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z" className="w-6 h-6 text-om-downtime" />
                            )}
                        </div>
                        <div className="flex-1">
                            <p className="font-bold text-om-ink mb-1">
                                Import {!importResult.errors?.length ? 'Completed' : 'Completed with errors'}
                            </p>
                            <div className="flex gap-6 text-sm">
                                <span className="text-om-running font-medium">{importResult.created} created</span>
                                <span className="text-om-accent font-medium">{importResult.updated} updated</span>
                                {importResult.skipped > 0 && (
                                    <span className="text-om-muted font-medium">{importResult.skipped} skipped</span>
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

            {flash.error && (
                <div className="card mb-6 border-l-4 border-om-blocked">
                    <p className="text-om-blocked font-medium">{flash.error}</p>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Upload form */}
                <div className="lg:col-span-2">
                    <div className="card">
                        <h2 className="text-lg font-semibold text-om-ink mb-4">Upload File</h2>

                        <form
                            method="POST"
                            action="/admin/materials/import/upload"
                            encType="multipart/form-data"
                            className="space-y-4"
                        >
                            <input type="hidden" name="_token" value={csrfToken} />

                            <div>
                                <label className="block text-sm font-medium text-om-muted mb-1">
                                    File (CSV, XLS, XLSX)
                                </label>
                                <input
                                    type="file"
                                    name="import_file"
                                    accept=".csv,.xls,.xlsx,.txt"
                                    required
                                    className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-om-ink file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-om-chip file:text-om-accent hover:file:bg-om-chip"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-om-muted mb-1">
                                    Import Strategy
                                </label>
                                <select
                                    name="import_strategy"
                                    required
                                    className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-om-ink focus:border-om-accent focus:ring-2 focus:ring-blue-200 outline-none transition"
                                >
                                    <option value="update_or_create">Create new &amp; update existing</option>
                                    <option value="create_only">Create new only (skip existing)</option>
                                    <option value="skip_existing">Update existing only (skip new)</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-om-muted mb-1">
                                    Source System <span className="text-om-faint font-normal">(optional)</span>
                                </label>
                                <select
                                    name="external_system"
                                    className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-om-ink focus:border-om-accent focus:ring-2 focus:ring-blue-200 outline-none transition"
                                >
                                    <option value="">-- None --</option>
                                    <option value="subiekt_gt">Subiekt GT</option>
                                    <option value="subiekt_nexo">Subiekt nexo</option>
                                    <option value="optima">Comarch Optima</option>
                                    <option value="wf_mag">WF-Mag</option>
                                    <option value="enova">Enova365</option>
                                    <option value="sap">SAP</option>
                                    <option value="custom">Other (custom)</option>
                                </select>
                            </div>

                            <div className="pt-2">
                                <button type="submit" className="btn-touch btn-primary w-full sm:w-auto">
                                    <Icon d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" className="w-5 h-5 inline-block mr-2" />
                                    Upload &amp; Map Columns
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Help sidebar */}
                <div className="lg:col-span-1 space-y-4">
                    <div className="card">
                        <h3 className="text-sm font-semibold text-om-ink mb-3">Supported Formats</h3>
                        <ul className="text-sm text-om-muted space-y-2">
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-om-running rounded-full"></span>
                                CSV (comma or semicolon separated)
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-om-running rounded-full"></span>
                                XLS (Excel 97-2003)
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-om-running rounded-full"></span>
                                XLSX (Excel 2007+)
                            </li>
                        </ul>
                    </div>

                    <div className="card">
                        <h3 className="text-sm font-semibold text-om-ink mb-3">Subiekt GT Export</h3>
                        <p className="text-sm text-om-muted mb-2">
                            To export materials from Subiekt GT:
                        </p>
                        <ol className="text-sm text-om-muted space-y-1 list-decimal list-inside">
                            <li>Go to Towary &gt; Lista towarow</li>
                            <li>Select all or filter</li>
                            <li>Click Export &gt; Excel/CSV</li>
                            <li>Include columns: Symbol, Nazwa, JM, Cena, EAN, Stan</li>
                        </ol>
                    </div>

                    <div className="card">
                        <h3 className="text-sm font-semibold text-om-ink mb-3">Matching Logic</h3>
                        <p className="text-sm text-om-muted">
                            Existing materials are matched by:
                        </p>
                        <ol className="text-sm text-om-muted space-y-1 list-decimal list-inside mt-1">
                            <li>External Code + Source System</li>
                            <li>EAN / Barcode</li>
                            <li>Internal Code</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    );
}

MaterialsImport.layout = (page) => <AppLayout>{page}</AppLayout>;
