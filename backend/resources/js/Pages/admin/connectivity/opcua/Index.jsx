import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { ConnectionCard, ConnectionGrid, ConnectionsEmpty, ConnectivityHeader } from '../ui';
import GatewaySetupGuide from './GatewaySetupGuide';
import { __ } from '../../../../lib/i18n';

export default function OpcuaIndex() {
    const { connections = [], counts = {} } = usePage().props;

    return (
        <>
            <Head title={__('OPC UA Connections')} />

            <div className="p-6 space-y-6">
                <ConnectivityHeader
                    active="opcua"
                    counts={counts}
                    description={__('Subscribe to OPC UA nodes via the gateway sidecar and map them to machine signals.')}
                    createHref="/admin/connectivity/opcua/create"
                    createLabel={__('New Connection')}
                />

                <GatewaySetupGuide open={connections.length === 0} />

                {connections.length === 0 ? (
                    <ConnectionsEmpty
                        message={__('No OPC UA connections defined yet.')}
                        createHref="/admin/connectivity/opcua/create"
                        createLabel={__('Create your first OPC UA connection →')}
                    />
                ) : (
                    <ConnectionGrid>
                        {connections.map((conn) => (
                            <ConnectionCard key={conn.id} conn={conn} protocol="opcua" endpoint={conn.endpoint_url} />
                        ))}
                    </ConnectionGrid>
                )}
            </div>
        </>
    );
}

OpcuaIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
