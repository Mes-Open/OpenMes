/**
 * OpenMES OPC UA Gateway
 * ----------------------
 * OpenMES cannot speak OPC UA natively (binary protocol, security policies,
 * X.509 secure channel). This sidecar connects to an OPC UA server, subscribes
 * to the nodes configured in OpenMES, and forwards every value change to the
 * OpenMES machine-signal ingest API — where it flows through the exact same
 * pipeline as Modbus and MQTT.
 *
 * Required env:
 *   OPENMES_API_URL        e.g. http://backend:8000
 *   OPENMES_API_TOKEN      machine gateway token (Settings → API Tokens)
 *   OPCUA_CONNECTION_ID    machine_connection id (protocol = opcua)
 * Optional:
 *   HEARTBEAT_MS           default 10000
 *   RECONNECT_MS           default 5000
 */
import {
  OPCUAClient,
  MessageSecurityMode,
  SecurityPolicy,
  AttributeIds,
  ClientSubscription,
  TimestampsToReturn,
} from "node-opcua-client";

const API_URL = process.env.OPENMES_API_URL || "http://localhost:8000";
const API_TOKEN = process.env.OPENMES_API_TOKEN || "";
const CONNECTION_ID = process.env.OPCUA_CONNECTION_ID || "1";
const HEARTBEAT_MS = parseInt(process.env.HEARTBEAT_MS || "10000", 10);
const RECONNECT_MS = parseInt(process.env.RECONNECT_MS || "5000", 10);
// How often the running gateway re-reads its connection from OpenMES, so tags
// added there, a changed endpoint or switching the connection off take effect
// without restarting the container.
const CONFIG_REFRESH_MS = parseInt(process.env.CONFIG_REFRESH_MS || "60000", 10);
// Upper bound for a call to OpenMES or a clean close of the OPC UA client, so a
// stalled peer cannot hold up the retry loop.
const REQUEST_TIMEOUT_MS = 5000;
const CLOSE_TIMEOUT_MS = 5000;

// Why no machine data is flowing; null only while the session and all
// subscriptions are up. Every heartbeat reports it.
let opcuaProblem = null;
let heartbeatTimer = null;
let closeOpcua = null;
let stopping = false;

const api = (path) => `${API_URL}/api/v1${path}`;
const authHeaders = {
  Authorization: `Bearer ${API_TOKEN}`,
  "Content-Type": "application/json",
  Accept: "application/json",
};

const log = (...a) => console.log(new Date().toISOString(), ...a);

async function fetchConfig() {
  const res = await fetch(api(`/machine-connections/${CONNECTION_ID}/gateway-config`), {
    headers: authHeaders,
    signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
  });
  if (res.status === 401) {
    throw new Error(
      "config fetch failed: 401 - OPENMES_API_TOKEN is invalid or revoked. " +
        "Create a machine gateway token in OpenMES (Settings → API Tokens)."
    );
  }
  if (res.status === 403) {
    throw new Error("config fetch failed: 403 - the token is not allowed to use the machine gateway API.");
  }
  if (res.status === 404) {
    throw new Error(`config fetch failed: 404 - no machine connection with id ${CONNECTION_ID} (OPCUA_CONNECTION_ID).`);
  }
  if (!res.ok) throw new Error(`config fetch failed: ${res.status}`);
  return res.json();
}

// ── Reading outbox ─────────────────────────────────────────────────────────
// Every value change is queued here and sent in order, one request at a time:
//  - in order, because OpenMES rejects a counter reading older than the last
//    one it accepted, so parallel posts racing each other would drop counts;
//  - with retries, because OpenMES restarting (deploy, reboot) must not lose
//    the state changes and alarms that happened meanwhile. Cumulative counters
//    would catch up on the next change, but a FAULT → RUNNING pair would not.
// Each reading carries the time the gateway received it and an event id fixed
// at that moment, so a retried post is recognised as the same reading and a
// pulse counter never counts it twice.
const OUTBOX_MAX = parseInt(process.env.OUTBOX_MAX || "10000", 10);
const BATCH_SIZE = 500; // the ingest API takes at most 1000 readings per post
const RETRY_MS = 2000;
const outbox = [];
let readingSeq = 0;
let flushing = false;

function queueReading(tag, value) {
  const at = new Date();
  // The gateway's own clock, not the PLC's: machine clocks drift, and OpenMES
  // refuses readings stamped more than a few seconds in its future.
  outbox.push({
    tag_id: tag.id,
    value,
    ts: at.toISOString(),
    event_id: `opcua-${CONNECTION_ID}-${tag.id}-${at.getTime()}-${++readingSeq}`,
  });
  if (outbox.length > OUTBOX_MAX) {
    const dropped = outbox.splice(0, outbox.length - OUTBOX_MAX);
    log(`outbox full: dropped ${dropped.length} oldest readings`);
  }
  flushOutbox();
}

async function flushOutbox() {
  if (flushing) return;
  flushing = true;
  try {
    while (outbox.length > 0 && !stopping) {
      const batch = outbox.slice(0, BATCH_SIZE);
      let res;
      try {
        res = await fetch(api(`/machine-connections/${CONNECTION_ID}/signals`), {
          method: "POST",
          headers: authHeaders,
          body: JSON.stringify({ readings: batch }),
          signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS * 2),
        });
      } catch (e) {
        log(`post readings error: ${e.message} - ${outbox.length} queued, retrying`);
        await delay(RETRY_MS);
        continue;
      }
      if (res.ok) {
        outbox.splice(0, batch.length);
        continue;
      }
      if (res.status === 422) {
        // The data itself is rejected; resending it would block the queue forever.
        const body = await res.text().catch(() => "");
        log(`readings rejected (422), dropping ${batch.length}: ${body.slice(0, 300)}`);
        outbox.splice(0, batch.length);
        continue;
      }
      // 401/403/404/5xx: the readings are fine, OpenMES is not accepting them yet.
      log(`post readings failed: HTTP ${res.status} - ${outbox.length} queued, retrying`);
      await delay(RETRY_MS);
    }
  } finally {
    flushing = false;
  }
}

async function heartbeat() {
  try {
    await fetch(api(`/machine-connections/${CONNECTION_ID}/heartbeat`), {
      method: "POST",
      headers: authHeaders,
      signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
      // An error heartbeat tells OpenMES the gateway is up but cannot reach the
      // OPC UA server, so the UI shows that instead of "not running".
      body: JSON.stringify(opcuaProblem ? { status: "error", message: opcuaProblem.slice(0, 500) } : {}),
    });
  } catch (e) {
    log("heartbeat error:", e.message);
  }
}

const securityPolicyMap = {
  None: SecurityPolicy.None,
  Basic256Sha256: SecurityPolicy.Basic256Sha256,
};
const securityModeMap = {
  None: MessageSecurityMode.None,
  Sign: MessageSecurityMode.Sign,
  SignAndEncrypt: MessageSecurityMode.SignAndEncrypt,
};

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Rejects as soon as the session or the subscription stops delivering data. */
function watchConnection(client, session, subscription) {
  return new Promise((_, reject) => {
    const lost = (reason) => () => reject(new Error(reason));
    client.once("connection_lost", lost("connection lost"));
    session.once("keepalive_failure", lost("server stopped responding"));
    session.once("session_closed", lost("session closed"));
    subscription.once("terminated", lost("subscription terminated"));
    subscription.once("internal_error", (e) => reject(e));
  });
}

async function subscribeTags(subscription, cfg) {
  for (const tag of cfg.tags) {
    const item = await subscription.monitor(
      { nodeId: tag.node_id, attributeId: AttributeIds.Value },
      { samplingInterval: cfg.opcua.publishing_interval_ms || 1000, discardOldest: true, queueSize: 10 },
      TimestampsToReturn.Both
    );
    item.on("changed", (dataValue) => {
      queueReading(tag, dataValue.value?.value);
    });
    log(`monitoring ${tag.name} (${tag.node_id}) → ${tag.signal_type}`);
  }
}

/** Thrown to start over with the new settings; not an outage. */
class ConfigChanged extends Error {}

/** What a running gateway depends on: a change to any of it needs a restart. */
const configKey = (cfg) =>
  JSON.stringify({ active: cfg.connection?.is_active, opcua: cfg.opcua, tags: cfg.tags });

/**
 * Re-reads the config every CONFIG_REFRESH_MS; `changed` rejects with
 * ConfigChanged once it differs from `cfg`. A failed read (OpenMES restarting)
 * is ignored — the next one decides.
 */
function watchConfig(cfg) {
  let timer;
  const changed = new Promise((_, reject) => {
    timer = setInterval(async () => {
      try {
        if (configKey(await fetchConfig()) !== configKey(cfg)) {
          reject(new ConfigChanged("configuration changed in OpenMES"));
        }
      } catch {}
    }, CONFIG_REFRESH_MS);
  });
  changed.catch(() => {}); // settled by whoever awaits it, or by nobody
  return { changed, stop: () => clearInterval(timer) };
}

async function closeClient(client, session, subscription) {
  const closeSession = async () => {
    try { await subscription?.terminate(); await session?.close(); } catch {}
  };
  await Promise.race([closeSession(), delay(CLOSE_TIMEOUT_MS)]);
  // Always disconnect: a client left behind would keep reconnecting on its own.
  await Promise.race([client.disconnect().catch(() => {}), delay(CLOSE_TIMEOUT_MS)]);
}

/**
 * One connection attempt: runs until the OPC UA side fails, then rejects. The
 * caller starts over with a fresh client, so every recovery re-reads the config
 * and re-creates the session and all subscriptions.
 */
async function run() {
  const cfg = await fetchConfig();
  if (!cfg.opcua) throw new Error("connection has no OPC UA config");
  const endpoint = cfg.opcua.endpoint_url;

  // OpenMES is reachable from here on: keep it told about the OPC UA side for
  // as long as the process lives, including during slow connection attempts.
  heartbeatTimer ??= setInterval(heartbeat, HEARTBEAT_MS);
  const config = watchConfig(cfg);

  if (!cfg.connection.is_active) {
    // Switched off in OpenMES (machine down for maintenance): do not read it,
    // say why, and wait to be switched back on.
    opcuaProblem = "Connection is deactivated in OpenMES - not reading the machine";
    log(`${cfg.connection.name} is deactivated in OpenMES - waiting`);
    await heartbeat();
    try { await config.changed; } catch {} finally { config.stop(); }
    return;
  }

  log(`config: ${cfg.connection.name}, ${cfg.tags.length} nodes, endpoint ${endpoint}`);
  opcuaProblem ??= `Connecting to ${endpoint}`;

  const client = OPCUAClient.create({
    applicationName: "OpenMES-Gateway",
    securityMode: securityModeMap[cfg.opcua.security_mode] ?? MessageSecurityMode.None,
    securityPolicy: securityPolicyMap[cfg.opcua.security_policy] ?? SecurityPolicy.None,
    endpointMustExist: false,
    keepSessionAlive: true,
    connectionStrategy: { maxRetry: 3, initialDelay: 1000, maxDelay: 10000 },
  });

  client.on("backoff", (retry, delayMs) => log(`retry ${retry}, next in ${delayMs}ms`));

  const userIdentity =
    cfg.opcua.auth_mode === "username" && cfg.opcua.username
      ? { userName: cfg.opcua.username, password: cfg.opcua.password ?? process.env.OPCUA_PASSWORD ?? "" }
      : undefined;

  let session;
  let subscription;
  let established = false;
  let closing;
  closeOpcua = () => (closing ??= closeClient(client, session, subscription));

  try {
    await Promise.race([config.changed, client.connect(endpoint)]);
    log("connected to OPC UA server");
    session = await Promise.race([config.changed, client.createSession(userIdentity)]);
    log("session created");

    subscription = ClientSubscription.create(session, {
      requestedPublishingInterval: cfg.opcua.publishing_interval_ms || 1000,
      requestedMaxKeepAliveCount: 10,
      requestedLifetimeCount: 100,
      maxNotificationsPerPublish: 100,
      publishingEnabled: true,
      priority: 10,
    });

    const connectionLost = watchConnection(client, session, subscription);
    await Promise.race([connectionLost, config.changed, subscribeTags(subscription, cfg)]);

    established = true;
    opcuaProblem = null;
    await heartbeat();
    await Promise.race([connectionLost, config.changed]);
  } catch (e) {
    // Closing the client on shutdown ends up here too; that is not an outage.
    if (stopping) return;
    if (e instanceof ConfigChanged) {
      log(`${e.message} - reconnecting with the new settings`);
      return;
    }
    // Report it, so the connection page shows the reason and the workstations
    // stop reading as fresh.
    opcuaProblem = `${established ? "Lost connection to" : "Cannot connect to"} ${endpoint}: ${e.message}`;
    await heartbeat();
    throw e;
  } finally {
    config.stop();
    await closeOpcua();
    closeOpcua = null;
  }
}

async function main() {
  const shutdown = async () => {
    stopping = true;
    clearInterval(heartbeatTimer);
    await closeOpcua?.();
    process.exit(0);
  };
  process.once("SIGINT", shutdown);
  process.once("SIGTERM", shutdown);

  if (!API_TOKEN) {
    log(
      "fatal: OPENMES_API_TOKEN is not set. Create a machine gateway token in OpenMES " +
        "(Settings → API Tokens) and set it in the .env file next to docker-compose.yml."
    );
  }
  while (!stopping) {
    try {
      await run();
    } catch (e) {
      log("fatal:", e.message, `- retrying in ${RECONNECT_MS}ms`);
      await delay(RECONNECT_MS);
    }
  }
}

main();
