import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { create } from 'zustand';
import { createJSONStorage, persist, type StateStorage } from 'zustand/middleware';

import type { Role, User } from '@/types/api';

interface AuthState {
  token: string | null;
  user: User | null;
  activeLineId: number | null;
  /** Operator's selected workstation on the active line, used for step routing. */
  activeWorkstationId: number | null;
  hydrated: boolean;
  setSession: (payload: { token: string; user: User }) => void;
  setUser: (user: User) => void;
  setActiveLineId: (id: number | null) => void;
  setActiveWorkstationId: (id: number | null) => void;
  clear: () => void;
  setHydrated: () => void;
}

const TOKEN_KEY = 'openmes.auth.token';

const secureTokenStorage: StateStorage = {
  getItem: async (name) => {
    if (Platform.OS === 'web') return AsyncStorage.getItem(name);
    return SecureStore.getItemAsync(name);
  },
  setItem: async (name, value) => {
    if (Platform.OS === 'web') return AsyncStorage.setItem(name, value);
    return SecureStore.setItemAsync(name, value);
  },
  removeItem: async (name) => {
    if (Platform.OS === 'web') return AsyncStorage.removeItem(name);
    return SecureStore.deleteItemAsync(name);
  },
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      activeLineId: null,
      activeWorkstationId: null,
      hydrated: false,
      setSession: ({ token, user }) =>
        set((s) => ({
          token,
          user,
          activeLineId: pickDefaultLine(user, s.activeLineId),
        })),
      setUser: (user) =>
        set((s) => ({
          user,
          activeLineId: pickDefaultLine(user, s.activeLineId),
        })),
      setActiveLineId: (id) =>
        // Clear the workstation when switching lines so we don't carry a stale ID.
        set({ activeLineId: id, activeWorkstationId: null }),
      setActiveWorkstationId: (id) => set({ activeWorkstationId: id }),
      clear: () =>
        set({ token: null, user: null, activeLineId: null, activeWorkstationId: null }),
      setHydrated: () => set({ hydrated: true }),
    }),
    {
      name: TOKEN_KEY,
      storage: createJSONStorage(() => secureTokenStorage),
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        activeLineId: state.activeLineId,
        activeWorkstationId: state.activeWorkstationId,
      }),
      onRehydrateStorage: () => (state) => {
        state?.setHydrated();
      },
    },
  ),
);

function pickDefaultLine(user: User | null, current: number | null): number | null {
  if (!user) return null;
  const lines = user.lines ?? [];
  if (current && lines.some((l) => l.id === current)) return current;
  if (lines.length === 1) return lines[0].id;
  return current;
}

export function getRole(user: User | null | undefined): Role | null {
  if (!user) return null;
  if (user.role) return user.role as Role;
  const first = user.roles?.[0]?.name;
  return (first as Role) ?? null;
}

export function isSupervisorOrAdmin(user: User | null | undefined): boolean {
  const role = getRole(user);
  return role === 'Admin' || role === 'Supervisor';
}
