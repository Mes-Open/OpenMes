import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { ConnectionCard, ConnectionGrid, ConnectionsEmpty, ConnectivityHeader } from '../ui';
import { __ } from '../../../../lib/i18n';

export default function MqttIndex() {
    const { connections = [], counts = {} } = usePage().props;

    return (
        <>
            <Head title={__('MQTT Connections')} />

            <div className="p-6 space-y-6">
                <ConnectivityHeader
                    active="mqtt"
                    counts={counts}
                    description={__('Define and manage MQTT broker connections and topic subscriptions.')}
                    createHref="/admin/connectivity/mqtt/create"
                    createLabel={__('New MQTT Connection')}
                />

                {connections.length === 0 ? (
                    <ConnectionsEmpty
                        message={__('No MQTT connections defined.')}
                        createHref="/admin/connectivity/mqtt/create"
                        createLabel={__('New MQTT Connection')}
                    />
                ) : (
                    <ConnectionGrid>
                        {connections.map((conn) => (
                            <ConnectionCard
                                key={conn.id}
                                conn={conn}
                                protocol="mqtt"
                                endpoint={conn.mqtt_host ? `${conn.mqtt_host}:${conn.mqtt_port}${conn.mqtt_use_tls ? ' · TLS' : ''}` : __('Not configured')}
                            />
                        ))}
                    </ConnectionGrid>
                )}
            </div>
        </>
    );
}

MqttIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
