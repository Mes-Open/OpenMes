# Machine Connectivity Architecture

This document explains how OpenMES connects to shop-floor machines (MQTT, Modbus
TCP, OPC UA), how readings flow through the system, and what is intentionally
left for later. It is aimed at **contributors** who want to extend connectivity
or add a new protocol.

For day-to-day MQTT setup, see [mqtt-connectivity.md](mqtt-connectivity.md).

---

## The big idea: one signal pipeline, many protocols

Every protocol adapter is "dumb": it only produces raw readings. All meaning is
applied in a single, protocol-agnostic pipeline. This means adding a new
protocol does **not** touch state, downtime, OEE, or UI code — you only write an
adapter that feeds the ingestor.

```
[Machine] ──OPC UA──▶ [OPC UA gateway sidecar] ──HTTP──┐
[Machine] ──Modbus──▶ [modbus:poll daemon (PHP)] ──────┤
[Machine] ──MQTT────▶ [mqtt:listen daemon (PHP)] ──────┤
                                                        ▼
                                   ┌─────────────────────────────────┐
                                   │  MachineTag  (address → signal,  │
                                   │  transform: scale/offset/value-map)│
                                   └─────────────────────────────────┘
                                                        ▼
                                   ┌─────────────────────────────────┐
                                   │  MachineSignalIngestor           │
                                   │  routes by signal_type           │
                                   └─────────────────────────────────┘
                  ┌──────────────────────┼──────────────────────┐
                  ▼                       ▼                      ▼
          MachineEvent            WorkstationState         counter deltas
          (event store)           (state machine)          (good / reject)
                                         ▼                      ▼
                                  auto ProductionDowntime   per-workstation OEE
                                         ▼
                                  Live Machine Monitor
```

### Key building blocks (`app/Services/Machine/`, `app/Models/`)

| Component | Responsibility |
|---|---|
| `MachineTag` | Maps a protocol address (Modbus register, OPC UA node id, MQTT JSONPath) to a `signal_type` and an optional `transform`. |
| `MachineSignalIngestor` | The single entry point. Applies the transform, routes by signal type (`state`, `good_count`, `reject_count`, `cycle_complete`, `telemetry`, `alarm`). |
| `WorkstationStateMachine` | Opens/closes time-sliced `WorkstationState` rows and auto-creates/closes `ProductionDowntime` when entering/leaving `STOPPED`/`FAULT`. |
| `MachineEvent` | Append-only event store (correlation id, microsecond timestamp, `synced_to_cloud` for edge sync). |
| `MachineMonitorService` | Read model for the live monitor: current state, today's availability, good/reject counts. |
| `RuntimeMonitor` | Tracks whether each connection's background runtime is alive (see below). |

### Signal types

A tag declares what its value *means*:

- `state` → drives the workstation state machine (`RUNNING`/`IDLE`/`STOPPED`/`FAULT`/`SETUP`). Use a `value_map` transform, e.g. `{"1":"RUNNING","2":"IDLE","3":"FAULT"}`.
- `good_count` / `reject_count` → cumulative machine counters; the ingestor stores the last value and emits the **delta**.
- `cycle_complete` → treated as a good-count pulse.
- `telemetry` → numeric value stored on the current state's metadata (temperature, pressure…). Use `scale`/`offset`.
- `alarm` → truthy value records an alarm event.

---

## Supported protocols

### MQTT — `php artisan mqtt:listen --connection=<id>`
Fully implemented (since v0.4.0). Library: `php-mqtt/client`. See
[mqtt-connectivity.md](mqtt-connectivity.md).

### Modbus TCP — `php artisan modbus:poll --connection=<id>`
Fully implemented. Library: `aldas/modbus-tcp-client`.

- Config in `modbus_connections` (host, port, unit id, poll interval, byte/word
  order). UI: **Admin → Connectivity → Modbus**.
- `ModbusReader` decodes `int16/uint16/int32/uint32/float32/bool`, handles
  endianness and Modicon-style addresses (`40001` → register 0).
- **Simulator for testing without hardware:**
  `php artisan modbus:simulate --port=5020` runs an in-PHP Modbus TCP server that
  cycles a machine through RUNNING/IDLE/FAULT and increments counters. Point a
  `modbus_connections` row at `127.0.0.1:5020` and run the poller to exercise the
  whole pipeline end to end.

### OPC UA — external gateway sidecar
OpenMES **does not speak OPC UA natively** — and intentionally so. OPC UA is a
binary protocol with security policies (Basic256Sha256), X.509 certificates and
a secure-channel/subscription model that is impractical to implement in PHP. The
production-correct approach is a small **gateway sidecar**.

- `opcua-gateway/` (Node.js, `node-opcua`) connects to the OPC UA server,
  subscribes to the configured nodes, and POSTs normalized readings back to
  OpenMES.
- It uses the protocol-agnostic bridge API (`MachineGatewayController`):
  - `GET  /api/v1/machine-connections/{id}/gateway-config` — what to subscribe to
  - `POST /api/v1/machine-connections/{id}/signals` — push readings (ingested)
  - `POST /api/v1/machine-connections/{id}/heartbeat` — keep-alive
- Any external system (a custom REST pusher, an edge box) can use the same API —
  it is not OPC UA-specific.
- Config in `opcua_connections`, UI: **Admin → Connectivity → OPC UA**.

---

## Runtime visibility (a hard project rule)

> **Any feature that needs a background daemon or container MUST surface, in the
> UI, whether that runtime is actually running — because not everyone deploys on
> Docker.**

A configured connection that has no poller/gateway running collects *nothing*,
silently. To prevent that confusion:

- Daemons emit a heartbeat every cycle via `RuntimeMonitor::heartbeat()`; the
  OPC UA gateway heartbeats on every POST.
- The connection page shows a green **"running (last seen Ns ago)"** banner, or
  an amber **"not running"** banner with copy-paste start commands for **both**
  bare metal (`php artisan …`) and Docker (`docker compose --profile … up`).
- Implemented in `RuntimeMonitor` + the
  `resources/views/admin/connectivity/_runtime-status.blade.php` partial.

**When you add a new protocol/daemon:** add a heartbeat call in the daemon loop
and include the `_runtime-status` partial on its connection page.

---

## Running the background services (Docker, all opt-in)

These are defined in `docker-compose.yml` under opt-in profiles, so they never
start by default:

```bash
# Modbus poller for connection 1
MODBUS_CONNECTION_ID=1 docker compose --profile connectivity up -d modbus-poller

# OPC UA gateway for connection 1 (needs an API token: Admin → API Tokens)
OPCUA_CONNECTION_ID=1 OPENMES_API_TOKEN=xxx \
  docker compose --profile connectivity up -d opcua-gateway

# MQTT listener for connection 1
MQTT_CONNECTION_ID=1 docker compose --profile connectivity up -d mqtt-listener

# Queue worker (needed in production for MQTT/queued jobs)
docker compose --profile workers up -d queue-worker
```

Not using Docker? Run the equivalent `php artisan …` command under a process
supervisor (systemd, Supervisor, etc.). The connection page shows the exact
command.

---

## Adding a new protocol — checklist

1. Add a `<protocol>_connections` table + model for transport config.
2. Reuse `machine_tags` for address→signal mapping (no new mapping table).
3. Write an adapter that reads values and calls
   `MachineSignalIngestor::ingest($tag, $rawValue)`.
4. Run it from a daemon command **or** push via the gateway API
   (`MachineGatewayController`).
5. Emit a heartbeat each cycle and add the `_runtime-status` banner to the UI.
6. Add a CRUD controller/views under `Admin → Connectivity` and a sidebar link.
7. Add tests (transform, ingest, state/downtime, address parsing) — see
   `tests/Feature/MachineConnectivityTest.php` and `MachineGatewayTest.php`.

---

## Intentionally deferred (and why)

These are **deliberate non-goals for now**, not missing work. Documented so
contributors know the trade-offs before picking them up.

### 1. Real-time push (Laravel Reverb / WebSockets)
- **Status:** the Live Machine Monitor uses **HTTP polling** (~3s). The
  `realtime_mode` setting (`polling`/`websocket`) already exists as the hook.
- **Why deferred:** polling needs zero extra infrastructure and is fine for
  typical deployments. Reverb only pays off with many simultaneous large Andon
  screens or a hard sub-second reaction requirement.
- **Pick this up when:** you need true Andon boards or many concurrent viewers.
  Add `laravel/reverb`, a `reverb` compose service, broadcast
  `WorkstationStateChanged`, and wire the monitor to Echo with polling fallback.

### 2. Write-back to machines (Modbus/OPC UA write)
- **Status:** everything is **read-only** by design.
- **Why deferred:** writing to a live production machine (set parameters, remote
  stop) is a safety-critical capability — a bug can damage equipment or halt
  production. Read-only is the safe default.
- **Pick this up when:** a concrete use case exists (e.g. push recipe parameters
  from a work order). Gate it behind a dedicated permission, add an audit-log
  entry per write, and default to off.

### 3. Full OPC UA test against a real server
- **Status:** the **ingest path** (gateway → OpenMES → pipeline) is covered by
  tests; the sidecar's actual `node-opcua` connection logic has not been
  exercised against a live OPC UA server in CI.
- **Why deferred:** it needs a real/containerized OPC UA server (security
  policy, certificates, subscriptions) in the test environment.
- **Pick this up when:** preparing the first real OPC UA deployment. Spin up a
  test server (e.g. an `open62541`/Prosys demo container) and validate the
  sidecar end to end. This is verification, not missing code.

---

## Related

- [MQTT Connectivity](mqtt-connectivity.md)
- [OEE Module](oee-module/)
- [ISA-95 Material Lots](isa95.md)
- [Roadmap](roadmap.md)
- OPC UA gateway: [`opcua-gateway/README.md`](../opcua-gateway/README.md)
