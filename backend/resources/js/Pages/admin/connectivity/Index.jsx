import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { ConnectionCard, ConnectionGrid, ConnectionsEmpty, ConnectivityHeader } from './ui';
import { __ } from '../../../lib/i18n';

export default function ConnectivityIndex() {
    const { connections = [], counts = {} } = usePage().props;

    return (
        <>
            <Head title={__('Machine Connectivity')} />

            <div className="p-6 space-y-6">
                <ConnectivityHeader
                    active="all"
                    counts={counts}
                    description={__('Overview of all machine communication channels.')}
                />

                {connections.length === 0 ? (
                    <ConnectionsEmpty message={__('No connections defined yet.') + ' ' + __('Pick a protocol tab above to add one.')} />
                ) : (
                    <ConnectionGrid>
                        {connections.map((conn) => (
                            <ConnectionCard key={conn.id} conn={conn} protocol={conn.protocol} endpoint={conn.endpoint} showProtocol />
                        ))}
                    </ConnectionGrid>
                )}
            </div>
        </>
    );
}

ConnectivityIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
