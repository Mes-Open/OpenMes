import { useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

/**
 * Electric SQL live-sync wiring for the mobile client.
 *
 * Reads migrate from REST (React Query) to Electric shapes streamed through the
 * same gatekeeper the web uses. The gatekeeper (`GET /api/shapes/{name}`) is
 * already `auth:web,sanctum`, so it authenticates our Sanctum BEARER token and
 * mints an HMAC-signed `{ url, params }` capability. The actual shape stream is
 * served by Caddy at `{serverUrl}/electric/v1/shape` and re-validates the
 * signature on every poll — so the stream itself needs no auth header.
 *
 * NO offline mode: the server is the source of truth, writes still go through
 * REST (hooks/mutations), and Electric only delivers live read state.
 *
 * NOTE: Electric holds one long-lived connection per active shape; this relies
 * on the server speaking HTTP/2 (https://) so many shapes multiplex over one
 * connection instead of exhausting the per-origin socket limit. Point
 * `serverUrl` at the https origin.
 */

export interface ShapeConfig {
  /** Absolute URL to the Electric shape endpoint (gatekeeper-proxied). */
  url: string;
  /** Signed protocol params: { table, columns, where, exp, sig }. */
  params: Record<string, string>;
}

/** Raw gatekeeper response (url is server-relative, e.g. `/electric/v1/shape`). */
interface GatekeeperConfig {
  url: string;
  params: Record<string, string>;
}

export class ShapeConfigError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message);
    this.name = 'ShapeConfigError';
  }
}

/**
 * Fetch and absolutize the signed config for a named shape from the gatekeeper,
 * authenticating with the current Sanctum bearer token. Throws
 * ShapeConfigError on failure (e.g. 401 unauthenticated, 404 unknown shape).
 */
export async function fetchShapeConfig(name: string, signal?: AbortSignal): Promise<ShapeConfig> {
  const serverUrl = useSettingsStore.getState().serverUrl.replace(/\/+$/, '');
  const token = useAuthStore.getState().token;

  const res = await fetch(`${serverUrl}/api/shapes/${encodeURIComponent(name)}`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    signal,
  });

  if (res.status === 401) {
    // Stale/invalid token — mirror the axios client's behavior so the app
    // bounces to login instead of silently failing to sync.
    useAuthStore.getState().clear();
    throw new ShapeConfigError('Unauthenticated', 401);
  }
  if (!res.ok) {
    throw new ShapeConfigError(`Shape config "${name}" failed (${res.status})`, res.status);
  }

  const cfg = (await res.json()) as GatekeeperConfig;
  // The gatekeeper returns a server-relative url; make it absolute against the
  // active server origin (Electric's client requires a full URL).
  const url = cfg.url.startsWith('http') ? cfg.url : `${serverUrl}${cfg.url}`;
  return { url, params: cfg.params };
}
