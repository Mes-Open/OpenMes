import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function ModulesIndex() {
    const { modules = [], csrf_token } = usePage().props;

    function postForm(action) {
        router.post(action, {}, { preserveScroll: true });
    }

    function handleDestroy(name, displayName) {
        if (confirm(`Uninstall module ${displayName}? Files will be removed.`)) {
            router.delete(`/admin/modules/${name}`);
        }
    }

    return (
        <>
            <Head title="Installed Modules" />
            <div className="max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-white">Installed Modules</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-1">Enable and disable installed OpenMES extensions</p>
                    </div>
                    <Link href="/admin/modules/install" className="btn-touch btn-primary text-sm">
                        + Install Module
                    </Link>
                </div>

                {modules.length === 0 ? (
                    <div className="card text-center py-16">
                        <svg className="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p className="text-gray-500 text-lg font-medium">No modules installed</p>
                        <p className="text-gray-400 text-sm mt-1">
                            <Link href="/admin/modules/install" className="text-blue-600 hover:underline">
                                Install a module from a ZIP file
                            </Link>
                            {' '}or place the module folder in{' '}
                            <code className="bg-gray-100 dark:bg-gray-700 px-1 rounded">modules/</code>
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {modules.map((module) => (
                            <div
                                key={module.name}
                                className={`card flex flex-col gap-4${module.enabled ? ' border-l-4 border-blue-400' : ''}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <h3 className="font-bold text-gray-800 dark:text-white">
                                                {module.display_name ?? module.name}
                                            </h3>
                                            <span className="text-xs text-gray-400 font-mono">
                                                v{module.version ?? '?'}
                                            </span>
                                            {module.enabled ? (
                                                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                    Enabled
                                                </span>
                                            ) : (
                                                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                    Disabled
                                                </span>
                                            )}
                                            {module.has_error && (
                                                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                    Provider Error
                                                </span>
                                            )}
                                        </div>
                                        {module.author && (
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                by{' '}
                                                {module.homepage ? (
                                                    <a href={module.homepage} target="_blank" rel="noopener noreferrer" className="hover:underline">
                                                        {module.author}
                                                    </a>
                                                ) : module.author}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <p className="text-sm text-gray-600 dark:text-gray-400 flex-1">
                                    {module.description ?? 'No description.'}
                                </p>

                                {module.hooks && module.hooks.length > 0 && (
                                    <div>
                                        <p className="text-xs text-gray-400 mb-1">Used hooks</p>
                                        <div className="flex flex-wrap gap-1">
                                            {module.hooks.map((hook) => (
                                                <span
                                                    key={hook}
                                                    className="text-xs bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded font-mono"
                                                >
                                                    {hook}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="flex gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                    {module.enabled ? (
                                        <button
                                            onClick={() => postForm(`/admin/modules/${module.name}/disable`)}
                                            className="btn-touch btn-secondary text-sm"
                                        >
                                            Disable
                                        </button>
                                    ) : (
                                        <button
                                            onClick={() => postForm(`/admin/modules/${module.name}/enable`)}
                                            className="btn-touch btn-primary text-sm"
                                        >
                                            Enable
                                        </button>
                                    )}

                                    {module.name !== 'ExampleHooks' && (
                                        <button
                                            onClick={() => handleDestroy(module.name, module.display_name ?? module.name)}
                                            className="btn-touch btn-secondary text-sm text-red-500 hover:text-red-700"
                                        >
                                            Uninstall
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ModulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
