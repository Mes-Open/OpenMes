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
> (mapped for Linux too in `docker-compose.yml`) or the host's IP address. On
> Linux that address is the Docker bridge, so a server listening only on
> `127.0.0.1` stays unreachable: make it listen on the bridge address (usually
> `172.17.0.1`), or on `0.0.0.0` behind a firewall.

> **Changing the token?** `docker compose restart` keeps the old value. After
> editing `.env`, recreate the container from the folder with
> `docker-compose.yml`, with no `OPENMES_API_TOKEN` exported in your shell (it
> would override `.env`):
> `OPCUA_CONNECTION_ID=<id> docker compose --profile connectivity up -d opcua-gateway`.

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
| `OPCUA_PASSWORD` | no | — | OPC UA user password, only if none is saved on the connection in OpenMES (the saved one wins) |
| `HEARTBEAT_MS` | no | `10000` | runtime heartbeat interval |
| `RECONNECT_MS` | no | `5000` | reconnect backoff |
| `CONFIG_REFRESH_MS` | no | `60000` | how often the gateway re-reads its connection; added tags, a changed endpoint or deactivating the connection take effect within this time |
| `OUTBOX_MAX` | no | `10000` | readings kept for retry while OpenMES is unreachable; the oldest are dropped beyond this |

The connection page in OpenMES shows whether this gateway is currently running
(it heartbeats every `HEARTBEAT_MS`). If it is stopped, the page tells the user
exactly how to start it. If it is running but cannot reach the OPC UA server, or
loses an established connection, it sends error heartbeats with the reason until
the session and subscriptions are back, and the page shows that reason.

## Delivery

Every value change is queued and posted to OpenMES in order, one request at a
time, stamped with the time the gateway received it and a unique event id. If
OpenMES is unreachable or answers with an error (restart, deploy, revoked
token), the queue is retried every 2 seconds, so state changes and alarms from
that period are not lost. A retried reading carries the same event id, and
OpenMES skips an event id it has already taken, so a resent batch never counts
parts, records a state change or raises an alarm twice. A batch OpenMES rejects
as malformed (HTTP 422) is logged and dropped. A single reading it cannot use,
such as a state value that matches no workstation state, is logged as
`reading rejected for tag …` while the rest of the batch goes through.

## Troubleshooting

| Log line | Cause |
|---|---|
| `config fetch failed: 401` | `OPENMES_API_TOKEN` missing, mistyped or revoked |
| `config fetch failed: 403` | token without gateway access |
| `config fetch failed: 404` | `OPCUA_CONNECTION_ID` does not exist |
| `retry N, next in …` / connection refused | OPC UA endpoint unreachable — see the note on `127.0.0.1` above |
| `reading rejected for tag N: Unknown workstation state: …` | the state tag's value map does not translate this machine value; add it in the tag's Value map |
