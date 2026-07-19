import { useQuery } from '@tanstack/react-query';

import { listLines } from '@/api/lines';
import { getUser, listRoles, listUsers, type UserFilters } from '@/api/users';

// ── Users (REST) ─────────────────────────────────────────────────────────────
// The API applies filters (role, line_id,
// account_type, q) server-side and returns the {data, meta} pagination shape.

export function useUsers(filters: UserFilters = {}) {
  return useQuery({
    queryKey: ['users', filters],
    queryFn: () => listUsers(filters),
  });
}

// REST — detail-by-id (includes hydrated role/roles/lines relations).
export function useUser(id: number | undefined) {
  return useQuery({
    queryKey: ['user', id],
    queryFn: () => getUser(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: listRoles,
    staleTime: 5 * 60 * 1000,
  });
}

// ── Lines (REST) ─────────────────────────────────────────────────────────────

export function useLines() {
  return useQuery({
    queryKey: ['lines'],
    queryFn: () => listLines(),
  });
}
