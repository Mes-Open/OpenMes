import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import MqttConnectionForm from './MqttConnectionForm';

export default function MqttEdit() {
    const { connection } = usePage().props;

    const handleDelete = () => {
        if (confirm('Delete this connection and all topics?')) {
            router.delete(`/admin/connectivity/mqtt/${connection.id}`);
        }
    };

    return (
        <>
            <Head title={`Edit ${connection.name}`} />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <a
                        href={`/admin/connectivity/mqtt/${connection.id}`}
                        className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to {connection.name}
                    </a>
                    <h1 className="mt-3 text-2xl font-bold text-om-ink">
                        Edit: {connection.name}
                    </h1>
                </div>

                <MqttConnectionForm
                    action={`/admin/connectivity/mqtt/${connection.id}`}
                    method="put"
                    submitLabel="Save Changes"
                    cancelHref={`/admin/connectivity/mqtt/${connection.id}`}
                    connection={connection}
                    onDelete={handleDelete}
                />
            </div>
        </>
    );
}

MqttEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
