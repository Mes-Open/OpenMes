# OpenMES OPC UA Gateway

OpenMES does not speak OPC UA natively. This small Node.js sidecar bridges an
OPC UA server to OpenMES: it subscribes to the nodes you configure in
**Admin → Connectivity → OPC UA** and forwards every value change to the
OpenMES machine-signal ingest API, where it flows through the same pipeline as
Modbus and MQTT (state machine, auto-downtime, OEE, live monitor).

## Why a separate service?

OPC UA is a binary protocol with security policies (Basic256Sha256), X.509
certificates and a secure-channel/subscription model that PHP cannot implement
practically. A dedicated gateway is the standard, production-correct approach.

## Configure in OpenMES first

1. **Admin → Connectivity → OPC UA → New Connection** — set the endpoint
   (`opc.tcp://host:4840`), security and auth.
2. Add **nodes** (tags): node id (`ns=2;s=State`), data type, signal type
   (state/good_count/…), and the workstation it belongs to.
3. **Admin → API Tokens** — create a token for the gateway.

## Run

### Docker (recommended)

```bash
OPCUA_CONNECTION_ID=<id> OPENMES_API_TOKEN=<token> \
  docker compose --profile connectivity up -d opcua-gateway
```

### Bare metal

```bash
cd opcua-gateway
npm install
OPENMES_API_URL=http://localhost:8000 \
OPENMES_API_TOKEN=<token> \
OPCUA_CONNECTION_ID=<id> \
  npm start
```

## Environment

| Var | Required | Default | Notes |
|---|---|---|---|
| `OPENMES_API_URL` | yes | `http://localhost:8000` | OpenMES backend base URL |
| `OPENMES_API_TOKEN` | yes | — | Sanctum API token |
| `OPCUA_CONNECTION_ID` | yes | `1` | machine_connection id (protocol = opcua) |
| `OPCUA_PASSWORD` | if username auth | — | OPC UA user password |
| `HEARTBEAT_MS` | no | `10000` | runtime heartbeat interval |
| `RECONNECT_MS` | no | `5000` | reconnect backoff |

The connection page in OpenMES shows whether this gateway is currently running
(it heartbeats every `HEARTBEAT_MS`). If it is stopped, the page tells the user
exactly how to start it.
