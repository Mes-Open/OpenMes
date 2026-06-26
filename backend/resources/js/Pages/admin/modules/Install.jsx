import { useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function ModulesInstall() {
    const { csrf_token } = usePage().props;
    const fileRef = useRef(null);
    const [filename, setFilename] = useState('');

    return (
        <>
            <Head title={__('Install Module')} />
            <div className="max-w-3xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">{__('Install Module')}</h1>
                    <p className="text-om-muted mt-1">{__('Upload a module from a ZIP file or place the folder manually')}</p>
                </div>

                {/* Upload ZIP */}
                <div className="card mb-6">
                    <h2 className="text-base font-bold text-om-ink mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-om-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {__('Upload ZIP file')}
                    </h2>

                    <form method="POST" action="/admin/modules/upload" encType="multipart/form-data">
                        <input type="hidden" name="_token" value={csrf_token} />

                        <div
                            className="border-2 border-dashed border-om-line hover:border-blue-400 rounded-om p-8 cursor-pointer text-center transition-colors mb-4"
                            onClick={() => fileRef.current?.click()}
                        >
                            <svg className="mx-auto h-10 w-10 text-om-faint mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p className="text-sm text-om-muted">
                                {filename || __('Click to select a .zip file')}
                            </p>
                            <p className="text-xs text-om-faint mt-1">{__('Max 20 MB')}</p>
                            <input
                                type="file"
                                name="module_zip"
                                ref={fileRef}
                                accept=".zip"
                                className="hidden"
                                onChange={(e) => setFilename(e.target.files?.[0]?.name ?? '')}
                                required
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={!filename}
                            className={`btn-touch btn-primary${!filename ? ' opacity-50 cursor-not-allowed' : ''}`}
                        >
                            {__('Install Module')}
                        </button>
                    </form>

                    <p className="text-xs text-om-faint mt-4">
                        {__('The ZIP must contain a')}{' '}
                        <code className="bg-om-chip px-1 rounded">module.json</code>
                        {' '}{__('file in the root directory or inside a single subfolder.')}
                    </p>
                </div>

                {/* Manual install guide */}
                <div className="card bg-om-panel border border-om-line2">
                    <h3 className="font-bold text-om-muted mb-2">{__('Manual Installation')}</h3>
                    <p className="text-sm text-om-muted mb-3">
                        {__('Place the module folder directly in')}{' '}
                        <code className="bg-om-card border rounded px-1 text-xs">modules/</code>,
                        {' '}{__('then go to')}{' '}
                        <Link href="/admin/modules" className="text-om-accent hover:underline">{__('Installed Modules')}</Link>
                        {' '}{__('and enable it.')}
                    </p>
                    <div className="text-xs font-mono bg-om-card border rounded p-3 text-om-muted space-y-0.5 mb-4">
                        <p>modules/YourModule/</p>
                        <p className="pl-4">├── module.json</p>
                        <p className="pl-4">├── Providers/</p>
                        <p className="pl-8">│   └── YourModuleServiceProvider.php</p>
                        <p className="pl-4">├── Controllers/</p>
                        <p className="pl-4">├── Models/</p>
                        <p className="pl-4">├── migrations/</p>
                        <p className="pl-4">├── views/</p>
                        <p className="pl-4">└── README.md</p>
                    </div>
                    <a
                        href="https://github.com/Mes-Open/OpenMes/blob/main/HOOKS.md"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-om-accent hover:underline"
                    >
                        {__('Available hooks and events (HOOKS.md) ↗')}
                    </a>
                </div>
            </div>
        </>
    );
}

ModulesInstall.layout = (page) => <AppLayout>{page}</AppLayout>;
