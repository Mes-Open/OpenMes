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
3. **Settings → API Tokens** — create a token with access
   **Machine gateway (OPC UA)** (the *Create one* link on the connection page
   opens this with the scope already picked). Such a token can only fetch the
   gateway config and post readings/heartbeats. Put it in the `.env` file next
   to `docker-compose.yml` as `OPENMES_API_TOKEN=…`.

> **OPC UA server on the same machine?** Inside the container `localhost` /
> `127.0.0.1` is the gateway itself. Use `opc.tcp://host.docker.internal:4840`
> (mapped for Linux too in `docker-compose.yml`) or the host's IP address.

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
| `OPENMES_API_TOKEN` | yes | — | Machine gateway token (Settings → API Tokens) |
| `OPCUA_CONNECTION_ID` | yes | `1` | machine_connection id (protocol = opcua) |
| `OPCUA_PASSWORD` | if username auth | — | OPC UA user password |
| `HEARTBEAT_MS` | no | `10000` | runtime heartbeat interval |
| `RECONNECT_MS` | no | `5000` | reconnect backoff |

The connection page in OpenMES shows whether this gateway is currently running
(it heartbeats every `HEARTBEAT_MS`). If it is stopped, the page tells the user
exactly how to start it. If it is running but cannot reach the OPC UA server, it
sends an error heartbeat and the page shows the reason.

## Troubleshooting

| Log line | Cause |
|---|---|
| `config fetch failed: 401` | `OPENMES_API_TOKEN` missing, mistyped or revoked |
| `config fetch failed: 403` | token without gateway access |
| `config fetch failed: 404` | `OPCUA_CONNECTION_ID` does not exist |
| `retry N, next in …` / connection refused | OPC UA endpoint unreachable — see the note on `127.0.0.1` above |
