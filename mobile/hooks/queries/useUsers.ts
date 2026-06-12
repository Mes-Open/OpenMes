import { useQuery } from '@tanstack/react-query';

import { getUser, listRoles, type UserFilters } from '@/api/users';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';
import type { Line, User } from '@/types/api';

// ── Users (Electric) ─────────────────────────────────────────────────────────
// Shape `users` columns (SAFE — no password/pin/remember_token):
//   id, name, username, email, account_type, force_password_change,
//   last_login_at, worker_id, workstation_id.
// NOTE: `role`/`roles`/`lines` relation data is absent from the shape
// (join tables not synced). The `role` filter param is silently dropped —
// callers that rely on server-side role filtering should use useUser() or
// a dedicated REST endpoint. `line_id` filter is also dropped for the same
// reason (no lines pivot in the shape). `account_type` and `q` are applied.
// REST-returned pagination meta is not available; `meta` is always undefined.

export function useUsers(filters: UserFilters = {}) {
  const accountType = filters.account_type;
  const search = filters.q?.trim().toLowerCase() ?? '';

  const result = useElectricShape<Row, User[]>('users', {
    select: (rows) => {
      let out = rows as unknown as User[];
      if (accountType !== undefined) out = out.filter((r) => r.account_type === accountType);
      if (search) {
        out = out.filter(
          (r) =>
            (r.name ?? '').toLowerCase().includes(search) ||
            r.username.toLowerCase().includes(search) ||
            (r.email ?? '').toLowerCase().includes(search),
        );
      }
      return [...out].sort((a, b) => (a.name ?? a.username).localeCompare(b.name ?? b.username));
    },
  });

  // Preserve the {data, meta} return shape that listUsers returns.
  return {
    ...result,
    data: { data: result.data, meta: undefined } as {
      data: User[];
      meta: undefined;
    },
  };
}

// REST — detail-by-id (includes hydrated role/roles/lines relations).
export function useUser(id: number | undefined) {
  return useQuery({
    queryKey: ['user', id],
    queryFn: () => getUser(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// REST — no `roles` shape registered.
export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: listRoles,
    staleTime: 5 * 60 * 1000,
  });
}

// ── Lines (Electric) ─────────────────────────────────────────────────────────
// Shape `lines_all` columns: id, code, name, description, is_active,
// area_id, division_id, view_template_id, default_operator_view.
// NOTE: REST-computed `workstations_count`, `work_orders_count`, `users_count`
// are absent from the shape.

export function useLines() {
  return useElectricShape<Row, Line[]>('lines_all', {
    select: (rows) =>
      ([...rows] as unknown as Line[]).sort((a, b) => a.name.localeCompare(b.name)),
  });
}
