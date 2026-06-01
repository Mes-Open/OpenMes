import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import OpcuaConnectionForm from './OpcuaConnectionForm';
import { __ } from '../../../../lib/i18n';

export default function OpcuaEdit() {
    const { connection } = usePage().props;

    const handleDelete = () => {
        if (confirm(__('Delete this connection and all its tags?'))) {
            router.delete(`/admin/connectivity/opcua/${connection.id}`);
        }
    };

    return (
        <>
            <Head title={__('Edit: :name', { name: connection.name })} />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <Link
                        href={`/admin/connectivity/opcua/${connection.id}`}
                        className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to :name', { name: connection.name })}
                    </Link>
                    <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{__('Edit: :name', { name: connection.name })}</h1>
                </div>

                <OpcuaConnectionForm
                    action={`/admin/connectivity/opcua/${connection.id}`}
                    method="put"
                    submitLabel={__('Save Changes')}
                    cancelHref={`/admin/connectivity/opcua/${connection.id}`}
                    connection={connection}
                    onDelete={handleDelete}
                />
            </div>
        </>
    );
}

OpcuaEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
