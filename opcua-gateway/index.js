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

async function heartbeat(error = null) {
  try {
    await fetch(api(`/machine-connections/${CONNECTION_ID}/heartbeat`), {
      method: "POST",
      headers: authHeaders,
      // An error heartbeat tells OpenMES the gateway is up but cannot reach the
      // OPC UA server, so the UI shows that instead of "not running".
      body: JSON.stringify(error ? { status: "error", message: String(error).slice(0, 500) } : {}),
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

async function run() {
  const cfg = await fetchConfig();
  if (!cfg.opcua) throw new Error("connection has no OPC UA config");
  log(`config: ${cfg.connection.name}, ${cfg.tags.length} nodes, endpoint ${cfg.opcua.endpoint_url}`);

  const client = OPCUAClient.create({
    applicationName: "OpenMES-Gateway",
    securityMode: securityModeMap[cfg.opcua.security_mode] ?? MessageSecurityMode.None,
    securityPolicy: securityPolicyMap[cfg.opcua.security_policy] ?? SecurityPolicy.None,
    endpointMustExist: false,
    connectionStrategy: { maxRetry: 3, initialDelay: 1000, maxDelay: 10000 },
  });

  client.on("backoff", (retry, delay) => log(`retry ${retry}, next in ${delay}ms`));

  const userIdentity =
    cfg.opcua.auth_mode === "username" && cfg.opcua.username
      ? { userName: cfg.opcua.username, password: process.env.OPCUA_PASSWORD || "" }
      : undefined;

  let session;
  try {
    await client.connect(cfg.opcua.endpoint_url);
    log("connected to OPC UA server");
    session = await client.createSession(userIdentity);
    log("session created");
  } catch (e) {
    // OpenMES is reachable (config was fetched) but the OPC UA server is not:
    // report it, so the connection page shows the reason.
    await heartbeat(`Cannot connect to ${cfg.opcua.endpoint_url}: ${e.message}`);
    try { await client.disconnect(); } catch {}
    throw e;
  }

  const subscription = ClientSubscription.create(session, {
    requestedPublishingInterval: cfg.opcua.publishing_interval_ms || 1000,
    requestedMaxKeepAliveCount: 10,
    requestedLifetimeCount: 100,
    maxNotificationsPerPublish: 100,
    publishingEnabled: true,
    priority: 10,
  });

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

  const hb = setInterval(heartbeat, HEARTBEAT_MS);
  heartbeat();

  const shutdown = async () => {
    clearInterval(hb);
    try { await subscription.terminate(); await session.close(); await client.disconnect(); } catch {}
    process.exit(0);
  };
  process.on("SIGINT", shutdown);
  process.on("SIGTERM", shutdown);
}

async function main() {
  if (!API_TOKEN) {
    log(
      "fatal: OPENMES_API_TOKEN is not set. Create a machine gateway token in OpenMES " +
        "(Settings → API Tokens) and set it in the .env file next to docker-compose.yml."
    );
  }
  for (;;) {
    try {
      await run();
      return; // run() keeps the process alive via subscription + heartbeat
    } catch (e) {
      log("fatal:", e.message, `- retrying in ${RECONNECT_MS}ms`);
      await new Promise((r) => setTimeout(r, RECONNECT_MS));
    }
  }
}

main();
