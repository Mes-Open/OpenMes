import { describe, it, expect, beforeEach } from 'vitest';
import { useAuthStore } from './authStore';
import type { User } from '../types';

describe('authStore', () => {
  beforeEach(() => {
    // Clear localStorage before each test
    localStorage.clear();
    // Reset the store
    useAuthStore.getState().clearAuth();
  });

  it('initializes with no user and not authenticated', () => {
    const state = useAuthStore.getState();
    expect(state.user).toBeNull();
    expect(state.token).toBeNull();
    expect(state.isAuthenticated).toBe(false);
  });

  it('setAuth stores user, token, and sets authenticated to true', () => {
    const mockUser: User = {
      id: 1,
      username: 'testuser',
      email: 'test@example.com',
      roles: [],
      lines: [],
      force_password_change: false,
      last_login_at: null,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-01T00:00:00Z',
    };
    const mockToken = 'test-token-123';

    useAuthStore.getState().setAuth(mockUser, mockToken);

    const state = useAuthStore.getState();
    expect(state.user).toEqual(mockUser);
    expect(state.token).toBe(mockToken);
    expect(state.isAuthenticated).toBe(true);
    expect(localStorage.getItem('auth_token')).toBe(mockToken);
    expect(localStorage.getItem('user')).toBe(JSON.stringify(mockUser));
  });

  it('clearAuth removes user, token, and sets authenticated to false', () => {
    const mockUser: User = {
      id: 1,
      username: 'testuser',
      email: 'test@example.com',
      roles: [],
      lines: [],
      force_password_change: false,
      last_login_at: null,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-01T00:00:00Z',
    };

    useAuthStore.getState().setAuth(mockUser, 'test-token');
    useAuthStore.getState().clearAuth();

    const state = useAuthStore.getState();
    expect(state.user).toBeNull();
    expect(state.token).toBeNull();
    expect(state.isAuthenticated).toBe(false);
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
  });

  it('setSelectedLine stores selected line ID', () => {
    useAuthStore.getState().setSelectedLine(5);

    const state = useAuthStore.getState();
    expect(state.selectedLineId).toBe(5);
    expect(localStorage.getItem('selected_line_id')).toBe('5');
  });

  it('initializeAuth restores auth from localStorage', () => {
    const mockUser: User = {
      id: 1,
      username: 'testuser',
      email: 'test@example.com',
      roles: [],
      lines: [],
      force_password_change: false,
      last_login_at: null,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-01T00:00:00Z',
    };
    const mockToken = 'stored-token-123';

    localStorage.setItem('auth_token', mockToken);
    localStorage.setItem('user', JSON.stringify(mockUser));
    localStorage.setItem('selected_line_id', '3');

    useAuthStore.getState().initializeAuth();

    const state = useAuthStore.getState();
    expect(state.user).toEqual(mockUser);
    expect(state.token).toBe(mockToken);
    expect(state.isAuthenticated).toBe(true);
    expect(state.selectedLineId).toBe(3);
  });
});
