import { useMutation, useQueryClient } from '@tanstack/react-query';

import { completeStep, reportStepProblem, startStep } from '@/api/batchSteps';

export function useStartStep(batchId: number | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (stepId: number) => startStep(stepId),
    onSuccess: () => {
      if (batchId !== undefined) qc.invalidateQueries({ queryKey: ['batch', batchId] });
      qc.invalidateQueries({ queryKey: ['work-orders'] });
    },
  });
}

export function useCompleteStep(batchId: number | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { stepId: number; produced_qty?: number; notes?: string }) =>
      completeStep(vars.stepId, { produced_qty: vars.produced_qty, notes: vars.notes }),
    onSuccess: () => {
      if (batchId !== undefined) qc.invalidateQueries({ queryKey: ['batch', batchId] });
      qc.invalidateQueries({ queryKey: ['work-orders'] });
    },
  });
}

export function useReportStepProblem(batchId: number | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { stepId: number; issue_type_id: number; description?: string }) =>
      reportStepProblem(vars.stepId, {
        issue_type_id: vars.issue_type_id,
        description: vars.description,
      }),
    onSuccess: () => {
      if (batchId !== undefined) qc.invalidateQueries({ queryKey: ['batch', batchId] });
      qc.invalidateQueries({ queryKey: ['issues'] });
      qc.invalidateQueries({ queryKey: ['work-orders'] });
    },
  });
}
