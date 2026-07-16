import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createEan,
  createLabelTemplate,
  createPallet,
  deleteEan,
  deleteLabelTemplate,
  deletePallet,
  getLabelTemplateMeta,
  getPackagingStats,
  getPalletMeta,
  listEans,
  listLabelTemplates,
  listPackagingItems,
  listPallets,
  listScanLogs,
  scanEan,
  setLabelTemplateDefault,
  updateLabelTemplate,
  updatePallet,
  type EanFilters,
  type LabelTemplateInput,
  type PalletInput,
  type PalletStatus,
  type ScanLogFilters,
} from '@/api/packaging';

export function usePallets(status?: PalletStatus) {
  return useQuery({
    queryKey: ['packaging', 'pallets', status ?? 'all'],
    queryFn: () => listPallets(status),
  });
}

export function usePalletMeta() {
  return useQuery({ queryKey: ['packaging', 'pallet-meta'], queryFn: getPalletMeta, staleTime: 5 * 60 * 1000 });
}

export function useCreatePallet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: PalletInput) => createPallet(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'pallets'] }),
  });
}

export function useUpdatePallet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: PalletInput }) => updatePallet(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'pallets'] }),
  });
}

export function useDeletePallet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deletePallet,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'pallets'] }),
  });
}

export function useLabelTemplates() {
  return useQuery({
    queryKey: ['packaging', 'label-templates'],
    queryFn: listLabelTemplates,
  });
}

export function useLabelTemplateMeta() {
  return useQuery({ queryKey: ['packaging', 'label-template-meta'], queryFn: getLabelTemplateMeta, staleTime: 30 * 60 * 1000 });
}

export function useCreateLabelTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: LabelTemplateInput) => createLabelTemplate(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'label-templates'] }),
  });
}

export function useUpdateLabelTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: LabelTemplateInput }) => updateLabelTemplate(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'label-templates'] }),
  });
}

export function useSetLabelTemplateDefault() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: setLabelTemplateDefault,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'label-templates'] }),
  });
}

export function useDeleteLabelTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteLabelTemplate,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'label-templates'] }),
  });
}

export function usePackagingItems() {
  return useQuery({
    queryKey: ['packaging', 'items'],
    queryFn: listPackagingItems,
    refetchInterval: 10_000,
  });
}

export function usePackagingStats() {
  return useQuery({
    queryKey: ['packaging', 'stats'],
    queryFn: getPackagingStats,
    refetchInterval: 30_000,
  });
}

export function useEans(filters: EanFilters = {}) {
  return useQuery({
    queryKey: ['packaging', 'eans', filters],
    queryFn: () => listEans(filters),
  });
}

export function useScanLogs(filters: ScanLogFilters = {}) {
  return useQuery({
    queryKey: ['packaging', 'scan-logs', filters],
    queryFn: () => listScanLogs(filters),
  });
}

export function useScanEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ean: string) => scanEan(ean),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['packaging'] });
    },
  });
}

export function useCreateEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { work_order_id: number; ean: string }) => createEan(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'eans'] }),
  });
}

export function useDeleteEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteEan(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'eans'] }),
  });
}
