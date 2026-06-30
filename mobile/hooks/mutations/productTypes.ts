import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  createProductType,
  deleteProductType,
  toggleProductTypeActive,
  updateProductType,
  type CreateProductTypePayload,
} from '@/api/productTypes';
import {
  addTemplateStep,
  createTemplate,
  deleteTemplate,
  deleteTemplateStep,
  reorderTemplateSteps,
  toggleTemplateActive,
  updateTemplate,
  updateTemplateStep,
  type ProcessTemplateInput,
  type TemplateStepInput,
} from '@/api/processTemplates';

const invalidatePT = (qc: ReturnType<typeof useQueryClient>) => {
  qc.invalidateQueries({ queryKey: ['product-types'] });
};

export function useCreateProductType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateProductTypePayload) => createProductType(payload),
    onSuccess: () => invalidatePT(qc),
  });
}

export function useUpdateProductType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateProductTypePayload> }) =>
      updateProductType(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      invalidatePT(qc);
      qc.invalidateQueries({ queryKey: ['product-type', vars.id] });
    },
  });
}

export function useDeleteProductType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteProductType(id),
    onSuccess: () => invalidatePT(qc),
  });
}

export function useToggleProductTypeActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleProductTypeActive(id),
    onSuccess: (_d, id) => {
      invalidatePT(qc);
      qc.invalidateQueries({ queryKey: ['product-type', id] });
    },
  });
}

export function useDeleteTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteTemplate(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['process-templates'] }),
  });
}

export function useToggleTemplateActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleTemplateActive(id),
    onSuccess: (_d, id) => {
      qc.invalidateQueries({ queryKey: ['process-templates'] });
      qc.invalidateQueries({ queryKey: ['process-template', id] });
    },
  });
}

export function useCreateTemplate(productTypeId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: ProcessTemplateInput) => createTemplate(productTypeId, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['process-templates'] }),
  });
}

export function useUpdateTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<ProcessTemplateInput> }) =>
      updateTemplate(vars.id, vars.input),
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['process-templates'] });
      qc.invalidateQueries({ queryKey: ['process-template', vars.id] });
    },
  });
}

// ── Template step mutations ───────────────────────────────────────────────

const invStep = (qc: ReturnType<typeof useQueryClient>, processTemplateId: number) => {
  qc.invalidateQueries({ queryKey: ['process-template', processTemplateId] });
};

export function useAddTemplateStep(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: TemplateStepInput) => addTemplateStep(processTemplateId, input),
    onSuccess: () => invStep(qc, processTemplateId),
  });
}

export function useUpdateTemplateStep(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { stepId: number; input: Partial<TemplateStepInput> }) =>
      updateTemplateStep(vars.stepId, vars.input),
    onSuccess: () => invStep(qc, processTemplateId),
  });
}

export function useDeleteTemplateStep(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (stepId: number) => deleteTemplateStep(stepId),
    onSuccess: () => invStep(qc, processTemplateId),
  });
}

export function useReorderTemplateSteps(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (step_ids: number[]) => reorderTemplateSteps(processTemplateId, step_ids),
    onSuccess: () => invStep(qc, processTemplateId),
  });
}
