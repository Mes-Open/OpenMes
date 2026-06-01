import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import OpcuaConnectionForm from './OpcuaConnectionForm';

export default function OpcuaCreate() {
    return (
        <>
            <Head title="New OPC UA Connection" />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <Link
                        href="/admin/connectivity/opcua"
                        className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to OPC UA Connections
                    </Link>
                    <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">New OPC UA Connection</h1>
                </div>

                <OpcuaConnectionForm
                    action="/admin/connectivity/opcua"
                    method="post"
                    submitLabel="Create Connection"
                    cancelHref="/admin/connectivity/opcua"
                />
            </div>
        </>
    );
}

OpcuaCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
