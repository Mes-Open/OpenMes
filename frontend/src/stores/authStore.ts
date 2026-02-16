import { create } from 'zustand';
import type { User } from '../types';

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  selectedLineId: number | null;
  setAuth: (user: User, token: string) => void;
  setUser: (user: User) => void;
  setSelectedLine: (lineId: number) => void;
  clearAuth: () => void;
  initializeAuth: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  selectedLineId: null,

  setAuth: (user: User, token: string) => {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user', JSON.stringify(user));
    set({ user, token, isAuthenticated: true });
  },

  setUser: (user: User) => {
    localStorage.setItem('user', JSON.stringify(user));
    set({ user });
  },

  setSelectedLine: (lineId: number) => {
    localStorage.setItem('selected_line_id', lineId.toString());
    set({ selectedLineId: lineId });
  },

  clearAuth: () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    localStorage.removeItem('selected_line_id');
    set({ user: null, token: null, isAuthenticated: false, selectedLineId: null });
  },

  initializeAuth: () => {
    const token = localStorage.getItem('auth_token');
    const userStr = localStorage.getItem('user');
    const selectedLineIdStr = localStorage.getItem('selected_line_id');

    if (token && userStr) {
      try {
        const user = JSON.parse(userStr) as User;
        const selectedLineId = selectedLineIdStr ? parseInt(selectedLineIdStr) : null;
        set({ user, token, isAuthenticated: true, selectedLineId });
      } catch (error) {
        // Invalid data in localStorage, clear it
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        localStorage.removeItem('selected_line_id');
      }
    }
  },
}));
