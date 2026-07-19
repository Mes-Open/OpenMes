import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createCustomField,
  createIntegration,
  createQcTrigger,
  deleteCustomField,
  deleteIntegration,
  deleteQcTrigger,
  getCustomFieldMeta,
  getQcTriggerMeta,
  listCustomFields,
  listIntegrations,
  listQcTriggers,
  toggleCustomFieldActive,
  toggleIntegrationActive,
  toggleQcTriggerActive,
  updateCustomField,
  updateIntegration,
  updateQcTrigger,
  type CustomFieldInput,
  type IntegrationInput,
  type QcTriggerInput,
} from '@/api/adminConfig';

/** External-system integrations. */
export function useIntegrations() {
  return useQuery({ queryKey: ['integrations'], queryFn: listIntegrations });
}

export function useCreateIntegration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: IntegrationInput) => createIntegration(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['integrations'] }),
  });
}

export function useUpdateIntegration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: IntegrationInput }) => updateIntegration(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['integrations'] }),
  });
}

export function useToggleIntegration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: toggleIntegrationActive,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['integrations'] }),
  });
}

export function useDeleteIntegration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteIntegration,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['integrations'] }),
  });
}

/** Custom field definitions. */
export function useCustomFields() {
  return useQuery({ queryKey: ['custom-fields'], queryFn: listCustomFields });
}

export function useCustomFieldMeta() {
  return useQuery({ queryKey: ['custom-fields', 'meta'], queryFn: getCustomFieldMeta, staleTime: 10 * 60 * 1000 });
}

export function useCreateCustomField() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: CustomFieldInput) => createCustomField(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['custom-fields'] }),
  });
}

export function useUpdateCustomField() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: CustomFieldInput }) => updateCustomField(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['custom-fields'] }),
  });
}

export function useToggleCustomField() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: toggleCustomFieldActive,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['custom-fields'] }),
  });
}

export function useDeleteCustomField() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteCustomField,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['custom-fields'] }),
  });
}

/** Quality-control triggers. */
export function useQcTriggers() {
  return useQuery({ queryKey: ['qc-triggers'], queryFn: listQcTriggers });
}

export function useQcTriggerMeta() {
  return useQuery({ queryKey: ['qc-triggers', 'meta'], queryFn: getQcTriggerMeta, staleTime: 5 * 60 * 1000 });
}

export function useCreateQcTrigger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: QcTriggerInput) => createQcTrigger(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-triggers'] }),
  });
}

export function useUpdateQcTrigger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: QcTriggerInput }) => updateQcTrigger(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-triggers'] }),
  });
}

export function useToggleQcTrigger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: toggleQcTriggerActive,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-triggers'] }),
  });
}

export function useDeleteQcTrigger() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteQcTrigger,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['qc-triggers'] }),
  });
}
