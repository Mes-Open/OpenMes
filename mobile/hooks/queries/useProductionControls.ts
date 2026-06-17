import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createProcessConfirmation,
  createQcTemplate,
  createQualityCheck,
  deleteQcTemplate,
  getConfirmationStatus,
  getPackagingChecklist,
  getQcStatus,
  listProcessConfirmations,
  listQcTemplatesForProcessTemplate,
  listQualityChecks,
  submitPackagingChecklist,
  updateQcTemplate,
  type ConfirmInput,
  type PackagingChecklistInput,
  type QcCheckInput,
  type QcTemplateInput,
} from '@/api/productionControls';

// ── Confirmations ─────────────────────────────────────────────────────────

export function useProcessConfirmations(batchId: number | undefined) {
  return useQuery({
    queryKey: ['confirmations', batchId],
    queryFn: () => listProcessConfirmations(batchId as number),
    enabled: typeof batchId === 'number' && Number.isFinite(batchId),
  });
}

export function useConfirmationStatus(batchId: number | undefined) {
  return useQuery({
    queryKey: ['confirmations-status', batchId],
    queryFn: () => getConfirmationStatus(batchId as number),
    enabled: typeof batchId === 'number' && Number.isFinite(batchId),
  });
}

export function useCreateConfirmation(batchId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: ConfirmInput) => createProcessConfirmation(batchId, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['confirmations', batchId] });
      qc.invalidateQueries({ queryKey: ['confirmations-status', batchId] });
    },
  });
}

// ── Quality Checks ────────────────────────────────────────────────────────

export function useQualityChecks(batchId: number | undefined) {
  return useQuery({
    queryKey: ['quality-checks', batchId],
    queryFn: () => listQualityChecks(batchId as number),
    enabled: typeof batchId === 'number' && Number.isFinite(batchId),
  });
}

export function useQcStatus(batchId: number | undefined) {
  return useQuery({
    queryKey: ['qc-status', batchId],
    queryFn: () => getQcStatus(batchId as number),
    enabled: typeof batchId === 'number' && Number.isFinite(batchId),
  });
}

export function useQcTemplatesForProcessTemplate(processTemplateId: number | undefined) {
  return useQuery({
    queryKey: ['qc-templates', processTemplateId],
    queryFn: () => listQcTemplatesForProcessTemplate(processTemplateId as number),
    enabled: typeof processTemplateId === 'number' && Number.isFinite(processTemplateId),
  });
}

export function useCreateQualityCheck(batchId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: QcCheckInput) => createQualityCheck(batchId, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['quality-checks', batchId] });
      qc.invalidateQueries({ queryKey: ['qc-status', batchId] });
    },
  });
}

// ── Packaging Checklist ───────────────────────────────────────────────────

export function usePackagingChecklist(batchId: number | undefined) {
  return useQuery({
    queryKey: ['packaging-checklist', batchId],
    queryFn: () => getPackagingChecklist(batchId as number),
    enabled: typeof batchId === 'number' && Number.isFinite(batchId),
  });
}

export function useSubmitPackagingChecklist(batchId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: PackagingChecklistInput) => submitPackagingChecklist(batchId, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['packaging-checklist', batchId] });
      qc.invalidateQueries({ queryKey: ['batch', batchId] });
    },
  });
}

// ── QC Template admin CRUD ────────────────────────────────────────────────

export function useCreateQcTemplate(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: QcTemplateInput) => createQcTemplate(processTemplateId, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-templates', processTemplateId] }),
  });
}

export function useUpdateQcTemplate(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<QcTemplateInput> }) =>
      updateQcTemplate(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-templates', processTemplateId] }),
  });
}

export function useDeleteQcTemplate(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteQcTemplate(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-templates', processTemplateId] }),
  });
}
