import { Link } from '@inertiajs/react';
import { CommandBlock } from '../RuntimePanel';
import { __ } from '../../../../lib/i18n';

/** Address of an OPC UA server running on the same computer as OpenMES (Docker). */
export const LOCAL_ENDPOINT = 'opc.tcp://host.docker.internal:4840';

/**
 * Step-by-step setup for the OPC UA gateway, shown on the OPC UA list before a
 * connection exists — the gateway is a separate container that needs a token,
 * which is easy to miss when the only instructions live on a connection page.
 *
 * Open by default while there are no connections; collapsible afterwards.
 */
export default function GatewaySetupGuide({ open = true }) {
    return (
        <details open={open} className="group bg-om-card rounded-om border border-om-line2">
            <summary className="cursor-pointer select-none list-none flex items-center justify-between gap-3 p-5">
                <div>
                    <h2 className="text-[15px] font-semibold text-om-ink">{__('How to connect OPC UA - a machine on the shop floor or a local server')}</h2>
                    <p className="text-xs text-om-muted mt-0.5">
                        {__('Data is read by a gateway running next to OpenMES in Docker. Three steps: connection, token, starting the gateway.')}
                    </p>
                </div>
                <svg className="w-4 h-4 text-om-muted shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </summary>

            <ol className="px-5 pb-5 space-y-5">
                <Step n={1} title={__('Add a connection')}>
                    <p>{__('Enter the address of the OPC UA server and add the nodes (tags) to read.')}</p>
                    <div className="grid gap-3 sm:grid-cols-2 mt-3">
                        <AddressCard
                            title={__('Local server (on this computer)')}
                            hint={__('runs locally where OpenMES runs, e.g. a simulator for testing')}
                            value={LOCAL_ENDPOINT}
                        />
                        <AddressCard
                            title={__('Machine or PLC on the shop floor')}
                            hint={__('use the IP address of that device')}
                            value="opc.tcp://192.168.1.50:4840"
                        />
                    </div>
                    <p className="mt-2 text-xs text-om-muted">
                        {__('Do not use localhost or 127.0.0.1 - inside Docker they point to the gateway itself, not to this computer.')}
                    </p>
                    <Link href="/admin/connectivity/opcua/create" className="mt-2 inline-block text-om-accent hover:underline text-xs font-medium">
                        {__('New Connection')} →
                    </Link>
                </Step>

                <Step n={2} title={__('Create a gateway token')}>
                    <p>{__('The gateway signs in to OpenMES with its own token, which can only read the connection settings and send machine data.')}</p>
                    <Link
                        href="/settings/api-tokens?scope=machine-gateway"
                        className="mt-2 inline-block text-om-accent hover:underline text-xs font-medium"
                    >
                        {__('Create one in Settings → API Tokens')} →
                    </Link>
                </Step>

                <Step n={3} title={__('Start the gateway')}>
                    <p>{__('On the server, in the folder with docker-compose.yml, run the command below with the connection number and the token.')}</p>
                    <div className="mt-2">
                        <CommandBlock
                            title={__('Docker (background)')}
                            value="OPCUA_CONNECTION_ID=<id> OPENMES_API_TOKEN=<token> docker compose --profile connectivity up -d opcua-gateway"
                        />
                    </div>
                    <p className="mt-2 text-xs text-om-muted">
                        {__('The connection page shows the exact command and whether the gateway is running.')}
                    </p>
                </Step>
            </ol>
        </details>
    );
}

function Step({ n, title, children }) {
    return (
        <li className="flex gap-3">
            <span className="w-6 h-6 shrink-0 rounded-full bg-om-ink text-om-on-ink text-xs font-semibold flex items-center justify-center">
                {n}
            </span>
            <div className="min-w-0 flex-1 text-sm text-om-muted">
                <h3 className="text-sm font-semibold text-om-ink mb-1">{title}</h3>
                {children}
            </div>
        </li>
    );
}

function AddressCard({ title, hint, value }) {
    return (
        <div className="rounded-om-sm border border-om-line2 bg-om-panel p-3">
            <p className="text-xs font-semibold text-om-ink">{title}</p>
            <p className="text-[11px] text-om-faint mb-1.5">{hint}</p>
            <code className="block font-mono text-xs text-om-ink break-all select-all">{value}</code>
        </div>
    );
}
