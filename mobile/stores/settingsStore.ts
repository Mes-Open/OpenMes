import AsyncStorage from '@react-native-async-storage/async-storage';
import { Appearance, Platform } from 'react-native';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import { applyTheme, type ThemeName } from '@openmes/ui';

import { DEFAULT_API_URL, LOCAL_API_URL } from '@/constants/api';
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
  /** Tablet sidebar collapsed to the icon rail (vs. expanded with labels). */
  sidebarCollapsed: boolean;
  hydrated: boolean;
  setServerUrl: (url: string, label?: string) => void;
  addServer: (url: string, label?: string) => void;
  removeServer: (url: string) => void;
  renameServer: (url: string, label: string) => void;
  setLanguage: (lng: AppLocale) => void;
  setTheme: (theme: ThemePreference) => void;
  toggleSidebar: () => void;
  setHydrated: () => void;
}

const normalize = (url: string) => url.trim().replace(/\/+$/, '');
const defaultLabel = (url: string) => url.replace(/^https?:\/\//, '').replace(/\/$/, '');

export const useSettingsStore = create<SettingsState>()(
  persist(
    (set) => ({
      serverUrl: DEFAULT_API_URL,
      servers: [
        { url: DEFAULT_API_URL, label: 'Demo' },
        { url: LOCAL_API_URL, label: 'Local' },
      ],
      language: null,
      theme: 'system',
      sidebarCollapsed: false,
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
      setTheme: (theme) => {
        set({ theme });
        const resolved = resolveTheme(theme);
        // Mirror the web AppLayout toggle: persist the resolved value where the
        // shared tokens read it synchronously at boot, then reload so every
        // baked StyleSheet re-creates with the new palette (the web gets this
        // for free from CSS variables; RN styles are frozen at module load).
        if (Platform.OS === 'web' && typeof localStorage !== 'undefined') {
          try {
            localStorage.setItem('om-theme', resolved);
          } catch {
            /* private mode */
          }
          if (typeof location !== 'undefined') location.reload();
        } else {
          applyTheme(resolved);
        }
      },
      toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
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
        sidebarCollapsed: state.sidebarCollapsed,
      }),
      onRehydrateStorage: () => (state) => {
        if (state) {
          if (Platform.OS !== 'web') applyTheme(resolveTheme(state.theme));
          // Migrate old `serverUrls: string[]` shape if present, and ensure non-empty.
          const legacy = (state as unknown as { serverUrls?: string[] }).serverUrls;
          if ((!state.servers || state.servers.length === 0) && legacy?.length) {
            state.servers = legacy.map((u) => ({ url: u, label: defaultLabel(u) }));
          }
          if (!state.servers || state.servers.length === 0) {
            state.servers = [{ url: state.serverUrl || DEFAULT_API_URL, label: 'Demo' }];
          }
          // Ship the platform-appropriate local dev server in the picker by
          // default (Android emulators reach the host via 10.0.2.2).
          if (!state.servers.some((srv) => srv.url === LOCAL_API_URL)) {
            state.servers = [...state.servers, { url: LOCAL_API_URL, label: 'Local' }];
          }
        }
        state?.setHydrated();
      },
    },
  ),
);

/** Resolve a ThemePreference to the concrete token theme. */
function resolveTheme(pref: ThemePreference): ThemeName {
  if (pref === 'system') return Appearance.getColorScheme() === 'dark' ? 'dark' : 'light';
  return pref;
}
