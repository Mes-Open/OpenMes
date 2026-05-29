import { Head, usePage } from '@inertiajs/react';
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
            <nav className="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <a href="/admin/dashboard" className="hover:text-gray-700">Dashboard</a>
                <span>/</span>
                <a href="/admin/materials" className="hover:text-gray-700">Materials</a>
                <span>/</span>
                <span className="text-gray-800 font-medium">Import</span>
            </nav>

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">Import Materials</h1>
                    <p className="text-gray-600 dark:text-gray-400 mt-1">
                        Import materials from CSV, XLS or XLSX file (e.g. Subiekt GT export)
                    </p>
                </div>
                <a href="/admin/materials" className="btn-touch btn-secondary">
                    Back to Materials
                </a>
            </div>

            {/* Import result banner */}
            {importResult && (
                <div className={`card mb-6 border-l-4 ${!importResult.errors?.length ? 'border-green-500' : 'border-yellow-500'}`}>
                    <div className="flex items-start gap-4">
                        <div className={`${!importResult.errors?.length ? 'bg-green-100' : 'bg-yellow-100'} rounded-full p-3 flex-shrink-0`}>
                            {!importResult.errors?.length ? (
                                <Icon d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" className="w-6 h-6 text-green-600" />
                            ) : (
                                <Icon d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z" className="w-6 h-6 text-yellow-600" />
                            )}
                        </div>
                        <div className="flex-1">
                            <p className="font-bold text-gray-800 dark:text-gray-100 mb-1">
                                Import {!importResult.errors?.length ? 'Completed' : 'Completed with errors'}
                            </p>
                            <div className="flex gap-6 text-sm">
                                <span className="text-green-700 font-medium">{importResult.created} created</span>
                                <span className="text-blue-700 font-medium">{importResult.updated} updated</span>
                                {importResult.skipped > 0 && (
                                    <span className="text-gray-600 font-medium">{importResult.skipped} skipped</span>
                                )}
                                <span className="text-gray-600">{importResult.total} total rows</span>
                            </div>
                            {importResult.errors && importResult.errors.length > 0 && (
                                <details className="mt-3">
                                    <summary className="text-sm text-red-600 cursor-pointer">
                                        Show errors ({importResult.errors.length})
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

            {flash.error && (
                <div className="card mb-6 border-l-4 border-red-500">
                    <p className="text-red-700 font-medium">{flash.error}</p>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Upload form */}
                <div className="lg:col-span-2">
                    <div className="card">
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Upload File</h2>

                        <form
                            method="POST"
                            action="/admin/materials/import/upload"
                            encType="multipart/form-data"
                            className="space-y-4"
                        >
                            <input type="hidden" name="_token" value={csrfToken} />

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    File (CSV, XLS, XLSX)
                                </label>
                                <input
                                    type="file"
                                    name="import_file"
                                    accept=".csv,.xls,.xlsx,.txt"
                                    required
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Import Strategy
                                </label>
                                <select
                                    name="import_strategy"
                                    required
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                >
                                    <option value="update_or_create">Create new &amp; update existing</option>
                                    <option value="create_only">Create new only (skip existing)</option>
                                    <option value="skip_existing">Update existing only (skip new)</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Source System <span className="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <select
                                    name="external_system"
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
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
                        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">Supported Formats</h3>
                        <ul className="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                CSV (comma or semicolon separated)
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                XLS (Excel 97-2003)
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                XLSX (Excel 2007+)
                            </li>
                        </ul>
                    </div>

                    <div className="card">
                        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">Subiekt GT Export</h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            To export materials from Subiekt GT:
                        </p>
                        <ol className="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
                            <li>Go to Towary &gt; Lista towarow</li>
                            <li>Select all or filter</li>
                            <li>Click Export &gt; Excel/CSV</li>
                            <li>Include columns: Symbol, Nazwa, JM, Cena, EAN, Stan</li>
                        </ol>
                    </div>

                    <div className="card">
                        <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">Matching Logic</h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Existing materials are matched by:
                        </p>
                        <ol className="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside mt-1">
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
