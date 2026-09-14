import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import FileDropZone from '../../../components/import/FileDropZone';
import { __ } from '../../../lib/i18n';

export default function ModulesInstall() {
    // Posted through Inertia so a validation error lands under the drop zone
    // and the controller's flash (installed / install failed) shows as usual.
    const { data, setData, post, processing, errors, progress } = useForm({ module_zip: null });

    const submit = (e) => {
        e.preventDefault();
        if (!data.module_zip) return;
        post('/admin/modules/upload', { forceFormData: true });
    };

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

                    <form onSubmit={submit} className="space-y-4">
                        <FileDropZone
                            file={data.module_zip}
                            onChange={(file) => setData('module_zip', file)}
                            accept=".zip"
                            label={__('Choose a module ZIP file')}
                            hint={__('Max 20 MB')}
                            error={errors.module_zip}
                        />

                        {processing && progress && (
                            <p className="text-xs text-om-muted">{__('Uploading…')} {progress.percentage}%</p>
                        )}

                        <button
                            type="submit"
                            disabled={!data.module_zip || processing}
                            className={`btn-touch btn-accent${!data.module_zip || processing ? ' opacity-50 cursor-not-allowed' : ''}`}
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
