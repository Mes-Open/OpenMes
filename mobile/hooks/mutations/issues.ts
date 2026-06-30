import { useMutation, useQueryClient } from '@tanstack/react-query';

import { acknowledgeIssue, closeIssue, createIssue, resolveIssue } from '@/api/issues';

export function useCreateIssue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createIssue,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['issues'] }),
  });
}

export function useAcknowledgeIssue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => acknowledgeIssue(id),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ['issues'] });
      qc.invalidateQueries({ queryKey: ['issue', id] });
    },
  });
}

export function useResolveIssue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; resolutionNotes?: string }) =>
      resolveIssue(vars.id, vars.resolutionNotes),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: ['issues'] });
      qc.invalidateQueries({ queryKey: ['issue', vars.id] });
    },
  });
}

export function useCloseIssue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => closeIssue(id),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ['issues'] });
      qc.invalidateQueries({ queryKey: ['issue', id] });
    },
  });
}
