import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import { DEFAULT_API_URL } from '@/constants/api';
import type { AppLocale } from '@/lib/i18n';

export interface ServerEntry {
  url: string;
  label: string;
}

/** App theme preference. 'system' follows the OS color scheme. */
export type ThemePreference = 'system' | 'light' | 'dark';

interface SettingsState {
  serverUrl: string;
  servers: ServerEntry[];
  language: AppLocale | null;
  theme: ThemePreference;
  hydrated: boolean;
  setServerUrl: (url: string, label?: string) => void;
  addServer: (url: string, label?: string) => void;
  removeServer: (url: string) => void;
  renameServer: (url: string, label: string) => void;
  setLanguage: (lng: AppLocale) => void;
  setTheme: (theme: ThemePreference) => void;
  setHydrated: () => void;
}

const normalize = (url: string) => url.trim().replace(/\/+$/, '');
const defaultLabel = (url: string) => url.replace(/^https?:\/\//, '').replace(/\/$/, '');

export const useSettingsStore = create<SettingsState>()(
  persist(
    (set) => ({
      serverUrl: DEFAULT_API_URL,
      servers: [{ url: DEFAULT_API_URL, label: 'Demo' }],
      language: null,
      theme: 'system',
      hydrated: false,
      setServerUrl: (url, label) =>
        set((state) => {
          const clean = normalize(url) || DEFAULT_API_URL;
          const exists = state.servers.find((s) => s.url === clean);
          const servers = exists
            ? state.servers
            : [...state.servers, { url: clean, label: label?.trim() || defaultLabel(clean) }];
          return { serverUrl: clean, servers };
        }),
      addServer: (url, label) =>
        set((state) => {
          const clean = normalize(url);
          if (!clean || state.servers.some((s) => s.url === clean)) return state;
          return {
            servers: [...state.servers, { url: clean, label: label?.trim() || defaultLabel(clean) }],
          };
        }),
      removeServer: (url) =>
        set((state) => {
          const clean = normalize(url);
          const list = state.servers.filter((s) => s.url !== clean);
          const next = list.length === 0 ? [{ url: DEFAULT_API_URL, label: 'Demo' }] : list;
          const active = state.serverUrl === clean ? next[0].url : state.serverUrl;
          return { servers: next, serverUrl: active };
        }),
      renameServer: (url, label) =>
        set((state) => ({
          servers: state.servers.map((s) =>
            s.url === url ? { ...s, label: label.trim() || defaultLabel(url) } : s,
          ),
        })),
      setLanguage: (lng) => set({ language: lng }),
      setTheme: (theme) => set({ theme }),
      setHydrated: () => set({ hydrated: true }),
    }),
    {
      name: 'openmes.settings',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        serverUrl: state.serverUrl,
        servers: state.servers,
        language: state.language,
        theme: state.theme,
      }),
      onRehydrateStorage: () => (state) => {
        if (state) {
          // Migrate old `serverUrls: string[]` shape if present, and ensure non-empty.
          const legacy = (state as unknown as { serverUrls?: string[] }).serverUrls;
          if ((!state.servers || state.servers.length === 0) && legacy?.length) {
            state.servers = legacy.map((u) => ({ url: u, label: defaultLabel(u) }));
          }
          if (!state.servers || state.servers.length === 0) {
            state.servers = [{ url: state.serverUrl || DEFAULT_API_URL, label: 'Demo' }];
          }
        }
        state?.setHydrated();
      },
    },
  ),
);
