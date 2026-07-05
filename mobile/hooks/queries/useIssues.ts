import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createIssue,
  createIssueType,
  deleteIssueType,
  getIssue,
  getIssueType,
  issueStatsByLine,
  listIssueTypes,
  listIssues,
  updateIssueType,
  type IssueFilters,
  type IssueTypeInput,
} from '@/api/issues';

/**
 * Issues via REST (React Query). The API applies
 * filters (status, line_id, work_order_id) server-side.
 */
export function useIssues(filters: IssueFilters = {}) {
  return useQuery({
    queryKey: ['issues', filters],
    queryFn: () => listIssues(filters),
  });
}

export function useIssue(id: number | undefined) {
  return useQuery({
    queryKey: ['issue', id],
    queryFn: () => getIssue(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/** Operator issue report — POST /issues (issue_type_id + optional work order/description). */
export function useCreateIssue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: {
      issue_type_id: number;
      description?: string;
      work_order_id?: number;
      line_id?: number;
    }) => createIssue(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['issues'] }),
  });
}

/** Issue types via REST. Active-only by default; admin screens pass true for all rows. */
export function useIssueTypes(includeInactive = false) {
  return useQuery({
    queryKey: ['issue-types', includeInactive],
    queryFn: () => listIssueTypes(includeInactive),
    staleTime: 5 * 60 * 1000,
  });
}

export function useIssueType(id: number | undefined) {
  return useQuery({
    queryKey: ['issue-type', id],
    queryFn: () => getIssueType(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateIssueType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: IssueTypeInput) => createIssueType(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['issue-types'] }),
  });
}

export function useUpdateIssueType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<IssueTypeInput> }) => updateIssueType(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['issue-types'] }),
  });
}

export function useDeleteIssueType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteIssueType,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['issue-types'] }),
  });
}

/**
 * Issue counts aggregated per line — server-computed aggregate endpoint.
 */
export function useIssueStatsByLine() {
  return useQuery({
    queryKey: ['issues', 'stats', 'line'],
    queryFn: issueStatsByLine,
  });
}
