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

async function postReadings(readings) {
  if (readings.length === 0) return;
  try {
    await fetch(api(`/machine-connections/${CONNECTION_ID}/signals`), {
      method: "POST",
      headers: authHeaders,
      body: JSON.stringify({ readings }),
    });
  } catch (e) {
    log("post readings error:", e.message);
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
      const value = dataValue.value?.value;
      postReadings([{ tag_id: tag.id, value }]);
    });
    log(`monitoring ${tag.name} (${tag.node_id}) → ${tag.signal_type}`);
  }
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
  log(`config: ${cfg.connection.name}, ${cfg.tags.length} nodes, endpoint ${endpoint}`);

  // OpenMES is reachable from here on: keep it told about the OPC UA side for
  // as long as the process lives, including during slow connection attempts.
  opcuaProblem ??= `Connecting to ${endpoint}`;
  heartbeatTimer ??= setInterval(heartbeat, HEARTBEAT_MS);

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
      ? { userName: cfg.opcua.username, password: process.env.OPCUA_PASSWORD || "" }
      : undefined;

  let session;
  let subscription;
  let established = false;
  let closing;
  closeOpcua = () => (closing ??= closeClient(client, session, subscription));

  try {
    await client.connect(endpoint);
    log("connected to OPC UA server");
    session = await client.createSession(userIdentity);
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
    await Promise.race([connectionLost, subscribeTags(subscription, cfg)]);

    established = true;
    opcuaProblem = null;
    await heartbeat();
    await connectionLost;
  } catch (e) {
    // Closing the client on shutdown ends up here too; that is not an outage.
    if (stopping) return;
    // Report it, so the connection page shows the reason and the workstations
    // stop reading as fresh.
    opcuaProblem = `${established ? "Lost connection to" : "Cannot connect to"} ${endpoint}: ${e.message}`;
    await heartbeat();
    throw e;
  } finally {
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
