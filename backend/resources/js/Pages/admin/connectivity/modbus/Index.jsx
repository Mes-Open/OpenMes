import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { ConnectionCard, ConnectionGrid, ConnectionsEmpty, ConnectivityHeader } from '../ui';
import { __ } from '../../../../lib/i18n';

export default function ModbusIndex() {
    const { connections = [], counts = {} } = usePage().props;

    return (
        <>
            <Head title={__('Modbus Connections')} />

            <div className="p-6 space-y-6">
                <ConnectivityHeader
                    active="modbus"
                    counts={counts}
                    description={__('Poll registers from Modbus TCP devices and map them to machine signals.')}
                    createHref="/admin/connectivity/modbus/create"
                    createLabel={__('New Connection')}
                />

                {connections.length === 0 ? (
                    <ConnectionsEmpty
                        message={__('No Modbus connections defined yet.')}
                        createHref="/admin/connectivity/modbus/create"
                        createLabel={__('Create your first Modbus connection →')}
                    />
                ) : (
                    <ConnectionGrid>
                        {connections.map((conn) => (
                            <ConnectionCard
                                key={conn.id}
                                conn={conn}
                                protocol="modbus"
                                endpoint={conn.host ? `${conn.host}:${conn.port} · ${__('unit')} ${conn.unit_id}` : null}
                            />
                        ))}
                    </ConnectionGrid>
                )}
            </div>
        </>
    );
}

ModbusIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
