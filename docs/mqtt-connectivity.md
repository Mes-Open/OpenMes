# MQTT Machine Connectivity

The MQTT machine connectivity module is available since **v0.4.0** under **Admin → Connectivity → MQTT**.

---

## Running the MQTT listener in production

Each MQTT connection requires a dedicated listener process. The listener is a separate Docker service (`mqtt-listener`) defined in `docker-compose.yml`.

### Start a listener for connection ID 1

```bash
cd /opt/openmmes   # or wherever your docker-compose.yml is
MQTT_CONNECTION_ID=1 docker compose up -d mqtt-listener
```

### Check listener logs

```bash
docker logs openmmes-mqtt-listener -f
```

### Stop the listener

```bash
docker compose stop mqtt-listener
```

> **Multiple connections**: run separate listener containers per connection. The current setup supports one at a time via `MQTT_CONNECTION_ID`. For multiple simultaneous listeners, duplicate the service block in `docker-compose.yml` with different container names and connection IDs.

---

## Testing Guide

Below is a reproducible test procedure for verifying MQTT connections end-to-end.

---

## Test environment

| Component | Details |
|---|---|
| MQTT broker | `eclipse-mosquitto:2` (Docker container) |
| Backend | `openmmes-backend` container (Laravel 12) |
| Package | `php-mqtt/client ^2.0` |
| Test topic pattern | `factory/line1/machine01/#` (wildcard) |
| Payload format | JSON |

---

## Step 1 — Start a local Mosquitto broker

```bash
docker run --name mosquitto-test -d -p 1883:1883 eclipse-mosquitto:2 \
  sh -c "printf 'listener 1883\nallow_anonymous true\n' > /mosquitto/config/mosquitto.conf \
         && mosquitto -c /mosquitto/config/mosquitto.conf"
```

If running inside Docker Compose, connect Mosquitto to the same network as the backend:

```bash
docker network connect <your_project>_openmmes-network mosquitto-test
```

---

## Step 2 — Create a test machine connection

```bash
docker exec openmmes-backend php artisan tinker --execute="
use App\Models\MachineConnection;
use App\Models\MqttConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;

\$mc = MachineConnection::create([
    'name'      => 'TEST-MACHINE-01',
    'protocol'  => 'mqtt',
    'is_active' => true,
    'status'    => 'disconnected',
]);

\$mqtt = MqttConnection::create([
    'machine_connection_id'   => \$mc->id,
    'broker_host'             => '172.21.0.5',  // Mosquitto container IP
    'broker_port'             => 1883,
    'clean_session'           => true,
    'qos_default'             => 0,
    'keep_alive_seconds'      => 60,
    'connect_timeout'         => 10,
    'reconnect_delay_seconds' => 5,
    'use_tls'                 => false,
]);

\$topic = MachineTopic::create([
    'machine_connection_id' => \$mc->id,
    'topic_pattern'         => 'factory/line1/machine01/#',
    'payload_format'        => 'json',
    'is_active'             => true,
]);

TopicMapping::create([
    'machine_topic_id' => \$topic->id,
    'action_type'      => 'log_event',
    'is_active'        => true,
]);

echo 'Created MachineConnection ID=' . \$mc->id;
"
```

---

## Step 3 — Verify connection (dry-run)

The `--dry-run` flag connects and logs received messages without persisting them to the database — safe for initial testing.

```bash
docker exec openmmes-backend php artisan mqtt:listen --connection=1 --dry-run
```

Expected output:
```
Starting MQTT listener for [1] TEST-MACHINE-01
  Broker: 172.21.0.5:1883
[18:56:27] Connected.
  Subscribed: factory/line1/machine01/#
```

---

## Step 4 — Simulate a machine publishing data

From the Mosquitto container or any MQTT client:

```bash
# Machine status message
docker exec mosquitto-test mosquitto_pub \
  -h 172.21.0.5 -p 1883 \
  -t "factory/line1/machine01/status" \
  -m '{"status":"running","speed":120,"temp":42.5,"cycle_count":1042}'

# Production counter
docker exec mosquitto-test mosquitto_pub \
  -h 172.21.0.5 -p 1883 \
  -t "factory/line1/machine01/production" \
  -m '{"order_no":"WO-2026-001","produced":47,"planned":200}'

# Alarm/fault
docker exec mosquitto-test mosquitto_pub \
  -h 172.21.0.5 -p 1883 \
  -t "factory/line1/machine01/alarm" \
  -m '{"code":"TEMP_HIGH","value":85.2,"threshold":80,"severity":"warning"}'
```

The listener outputs received messages to the console in real time:

```
[2026-04-03T18:56:31+00:00] factory/line1/machine01/status: {"status":"running","speed":120,...}
[2026-04-03T18:56:31+00:00] factory/line1/machine01/production: {"order_no":"WO-2026-001",...}
```

---

## Step 5 — Full mode (with DB persistence)

Run without `--dry-run` to process messages and store them in `machine_messages`:

```bash
docker exec openmmes-backend php artisan mqtt:listen --connection=1
```

Verify messages were stored:

```bash
docker exec openmmes-backend php artisan tinker --execute="
use App\Models\MachineMessage;
use App\Models\MachineConnection;

echo 'Messages in DB: ' . MachineMessage::count() . PHP_EOL;
MachineMessage::orderBy('received_at','desc')->take(3)->get(['topic','processing_status','received_at'])
    ->each(fn(\$m) => print \"[\$m->received_at] [\$m->processing_status] \$m->topic\n\");

\$c = MachineConnection::find(1);
echo 'Total messages received: ' . \$c->messages_received . PHP_EOL;
"
```

Expected output:
```
Messages in DB: 2
[2026-04-03 18:56:46] [ok] factory/line1/machine01/production
[2026-04-03 18:56:46] [ok] factory/line1/machine01/status
Total messages received: 2
```

---

## Test results summary

| Test | Result |
|---|---|
| Broker connection | ✅ Connected |
| Wildcard topic subscription (`#`) | ✅ Subscribed |
| JSON payload parsing | ✅ Correct |
| Message receipt (dry-run) | ✅ 3/3 received |
| DB persistence (full mode) | ✅ Stored, status `ok` |
| Action execution (`log_event`) | ✅ `{"logged":true}` |
| `messages_received` counter | ✅ Incremented |
| Reconnect loop on disconnect | ✅ Retries after `reconnect_delay_seconds` |

---

## Known issue fixed during testing

The original WIP commit declared `"php-mqtt/client": "^1.10"` in `composer.json` — this version does not exist (the package skips directly from `v0.x` to `v2.x`). Fixed to `^2.0`. The API is fully compatible (same class names and method signatures).
