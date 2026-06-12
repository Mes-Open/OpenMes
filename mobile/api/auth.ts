import { api } from './client';
import type { ApiEnvelope, LoginResponse, User } from '@/types/api';

export const login = (
  username: string,
  password: string,
  baseUrlOverride?: string,
): Promise<LoginResponse> =>
  api
    .post<ApiEnvelope<LoginResponse>>(
      '/api/auth/login',
      { username, password },
      { unauthenticated: true, baseUrlOverride },
    )
    .then((r) => r.data.data);

export const logout = (): Promise<void> =>
  api.post('/api/auth/logout').then(() => undefined);

export const refresh = (): Promise<{ token: string }> =>
  api.post<ApiEnvelope<{ token: string }>>('/api/auth/refresh').then((r) => r.data.data);

export const me = (): Promise<User> =>
  api.get<ApiEnvelope<User>>('/api/auth/me').then((r) => r.data.data);

export const changePassword = (currentPassword: string, newPassword: string): Promise<void> =>
  api
    .post('/api/auth/change-password', {
      current_password: currentPassword,
      new_password: newPassword,
      new_password_confirmation: newPassword,
    })
    .then(() => undefined);

export const health = (
  baseUrlOverride?: string,
): Promise<{ status: string; timestamp?: string }> =>
  api
    .get<{ status: string; timestamp?: string }>('/api/health', {
      unauthenticated: true,
      baseUrlOverride,
    })
    .then((r) => r.data);
