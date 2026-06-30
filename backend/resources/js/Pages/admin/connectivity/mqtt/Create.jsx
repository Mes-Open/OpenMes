import { Head } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import MqttConnectionForm from './MqttConnectionForm';

export default function MqttCreate() {
    return (
        <>
            <Head title="New MQTT Connection" />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <a
                        href="/admin/connectivity/mqtt"
                        className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to MQTT Connections
                    </a>
                    <h1 className="mt-3 text-2xl font-bold text-om-ink">New MQTT Connection</h1>
                </div>

                <MqttConnectionForm
                    action="/admin/connectivity/mqtt"
                    method="post"
                    submitLabel="Create Connection"
                    cancelHref="/admin/connectivity/mqtt"
                />
            </div>
        </>
    );
}

MqttCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
