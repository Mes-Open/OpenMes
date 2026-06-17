import axios, { AxiosError, AxiosHeaders } from 'axios';

import { useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

declare module 'axios' {
  interface AxiosRequestConfig {
    /** Skip the Authorization header (login, health, refresh-from-login). */
    unauthenticated?: boolean;
    /** Override the base URL for this single request (login server-URL probe). */
    baseUrlOverride?: string;
  }
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export const api = axios.create({
  headers: { Accept: 'application/json' },
  paramsSerializer: {
    serialize: (params) => {
      const usp = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null) continue;
        if (Array.isArray(value)) {
          for (const v of value) usp.append(`${key}[]`, String(v));
        } else {
          usp.append(key, String(value));
        }
      }
      return usp.toString();
    },
  },
});

api.interceptors.request.use((config) => {
  config.baseURL = config.baseUrlOverride ?? useSettingsStore.getState().serverUrl;

  if (!config.unauthenticated) {
    const token = useAuthStore.getState().token;
    if (token) {
      const headers = AxiosHeaders.from(config.headers);
      headers.set('Authorization', `Bearer ${token}`);
      config.headers = headers;
    }
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const status = error.response?.status ?? 0;
    const payload = error.response?.data ?? null;

    // 401 = stale/invalid Sanctum token; 419 = session/CSRF expired (defense-in-depth
    // for proxy or misconfig — Sanctum bearer auth normally doesn't trip it).
    if ((status === 401 || status === 419) && !error.config?.unauthenticated) {
      useAuthStore.getState().clear();
    }

    const message =
      error.code === 'ERR_CANCELED' || error.name === 'CanceledError'
        ? 'Request cancelled'
        : extractErrorMessage(payload) ?? error.message ?? `Request failed with status ${status}`;

    return Promise.reject(new ApiError(message, status, payload));
  },
);

function extractErrorMessage(payload: unknown): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const obj = payload as Record<string, unknown>;
  if (typeof obj.message === 'string') return obj.message;
  if (typeof obj.error === 'string') return obj.error;
  if (obj.errors && typeof obj.errors === 'object') {
    const first = Object.values(obj.errors as Record<string, unknown>)[0];
    if (Array.isArray(first) && typeof first[0] === 'string') return first[0];
  }
  return null;
}
