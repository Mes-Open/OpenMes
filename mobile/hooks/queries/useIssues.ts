import { useQuery } from '@tanstack/react-query';

import { getIssue, issueStatsByLine, type IssueFilters } from '@/api/issues';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';
import type { Issue, IssueType } from '@/types/api';

/**
 * Live issues via Electric (migrated from REST `listIssues`).
 * Uses `issues_all` so every status value (including RESOLVED/CLOSED) is
 * available. Filters applied client-side:
 *   - status       → string equality / array membership
 *   - work_order_id → equality
 * Fidelity note: the `issues_all` shape does not include a `line_id` column,
 * so the `line_id` filter cannot be applied client-side — when `line_id` is
 * passed, the returned rows are NOT scoped to that line. This is a known
 * limitation; update the ShapeRegistry to add `line_id` to `issues_all` if
 * strict per-line filtering is required.
 */
export function useIssues(filters: IssueFilters = {}) {
  const { status, work_order_id } = filters;

  return useElectricShape<Row, Issue[]>('issues_all', {
    select: (rows) => {
      let out = rows as unknown as Issue[];

      if (status !== undefined) {
        const statuses = Array.isArray(status) ? status : [status];
        out = out.filter((r) => statuses.includes(r.status));
      }

      if (work_order_id !== undefined) {
        out = out.filter((r) => Number(r.work_order_id) === work_order_id);
      }

      // Preserve the REST default order: most-recently-updated first.
      return [...out].sort((a, b) => {
        const ta = a.updated_at ?? a.created_at ?? '';
        const tb = b.updated_at ?? b.created_at ?? '';
        return tb.localeCompare(ta);
      });
    },
  });
}

export function useIssue(id: number | undefined) {
  return useQuery({
    queryKey: ['issue', id],
    queryFn: () => getIssue(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/**
 * Live issue types via Electric (migrated from REST `listIssueTypes`).
 * Uses `issue_types_all` (all rows, incl. inactive) so admin screens that
 * need inactive types are not broken. Active-only callers can add their own
 * `.filter(t => t.is_active !== false)` if needed.
 */
export function useIssueTypes() {
  return useElectricShape<Row, IssueType[]>('issue_types_all', {
    select: (rows) => {
      const out = rows as unknown as IssueType[];
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

/**
 * Issue counts aggregated per line — server-computed aggregate endpoint,
 * left on REST (no shape carries these counts).
 */
export function useIssueStatsByLine() {
  return useQuery({
    queryKey: ['issues', 'stats', 'line'],
    queryFn: issueStatsByLine,
  });
}
