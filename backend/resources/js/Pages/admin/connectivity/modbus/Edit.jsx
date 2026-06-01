import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import ModbusConnectionForm from './ModbusConnectionForm';

export default function ModbusEdit() {
    const { connection } = usePage().props;

    const handleDelete = () => {
        if (confirm('Delete this connection and all its tags?')) {
            router.delete(`/admin/connectivity/modbus/${connection.id}`);
        }
    };

    return (
        <>
            <Head title={`Edit ${connection.name}`} />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <Link
                        href={`/admin/connectivity/modbus/${connection.id}`}
                        className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to {connection.name}
                    </Link>
                    <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">Edit: {connection.name}</h1>
                </div>

                <ModbusConnectionForm
                    action={`/admin/connectivity/modbus/${connection.id}`}
                    method="put"
                    submitLabel="Save Changes"
                    cancelHref={`/admin/connectivity/modbus/${connection.id}`}
                    connection={connection}
                    onDelete={handleDelete}
                />
            </div>
        </>
    );
}

ModbusEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
