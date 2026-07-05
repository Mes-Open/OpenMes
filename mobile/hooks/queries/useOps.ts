import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as ops from '@/api/ops';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// Companies
/**
 * Company list via REST `listCompanies`.
 * The server applies include_inactive / type / q filters.
 */
export function useCompanies(opts: Parameters<typeof ops.listCompanies>[0] = {}) {
  return useQuery({
    queryKey: ['companies', opts],
    queryFn: () => ops.listCompanies(opts),
  });
}
export function useCompany(id: number | undefined) {
  return useQuery({
    queryKey: ['company', id],
    queryFn: () => ops.getCompany(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateCompany() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.createCompany, onSuccess: () => inv(qc, 'companies') });
}
export function useUpdateCompany() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<ops.CreateCompanyPayload> }) => ops.updateCompany(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'companies'),
  });
}
export function useDeleteCompany() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.deleteCompany, onSuccess: () => inv(qc, 'companies') });
}
export function useToggleCompanyActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.toggleCompanyActive, onSuccess: () => inv(qc, 'companies') });
}

// Cost sources
/**
 * Cost-source list via REST `listCostSources`.
 * The server applies the include_inactive flag.
 */
export function useCostSources(includeInactive = false) {
  return useQuery({
    queryKey: ['cost-sources', includeInactive],
    queryFn: () => ops.listCostSources(includeInactive),
  });
}
export function useCostSource(id: number | undefined) {
  return useQuery({
    queryKey: ['cost-source', id],
    queryFn: () => ops.getCostSource(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateCostSource() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.createCostSource, onSuccess: () => inv(qc, 'cost-sources') });
}
export function useUpdateCostSource() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<ops.CreateCostSourcePayload> }) => ops.updateCostSource(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'cost-sources'),
  });
}
export function useDeleteCostSource() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.deleteCostSource, onSuccess: () => inv(qc, 'cost-sources') });
}
export function useToggleCostSourceActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.toggleCostSourceActive, onSuccess: () => inv(qc, 'cost-sources') });
}

// Anomaly reasons
/**
 * Anomaly-reason list via REST `listAnomalyReasons`.
 * The server applies include_inactive / category filters.
 */
export function useAnomalyReasons(opts: Parameters<typeof ops.listAnomalyReasons>[0] = {}) {
  return useQuery({
    queryKey: ['anomaly-reasons', opts],
    queryFn: () => ops.listAnomalyReasons(opts),
  });
}
export function useAnomalyReason(id: number | undefined) {
  return useQuery({
    queryKey: ['anomaly-reason', id],
    queryFn: () => ops.getAnomalyReason(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateAnomalyReason() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.createAnomalyReason, onSuccess: () => inv(qc, 'anomaly-reasons') });
}
export function useUpdateAnomalyReason() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<ops.CreateAnomalyReasonPayload> }) => ops.updateAnomalyReason(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'anomaly-reasons'),
  });
}
export function useDeleteAnomalyReason() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.deleteAnomalyReason, onSuccess: () => inv(qc, 'anomaly-reasons') });
}

// Subassemblies
/**
 * Subassembly list via REST `listSubassemblies`.
 * The server applies include_inactive / product_type_id filters and returns
 * the product_type relation.
 */
export function useSubassemblies(opts: Parameters<typeof ops.listSubassemblies>[0] = {}) {
  return useQuery({
    queryKey: ['subassemblies', opts],
    queryFn: () => ops.listSubassemblies(opts),
  });
}
export function useSubassembly(id: number | undefined) {
  return useQuery({
    queryKey: ['subassembly', id],
    queryFn: () => ops.getSubassembly(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateSubassembly() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.createSubassembly, onSuccess: () => inv(qc, 'subassemblies') });
}
export function useUpdateSubassembly() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<ops.CreateSubassemblyPayload> }) => ops.updateSubassembly(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'subassemblies'),
  });
}
export function useDeleteSubassembly() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.deleteSubassembly, onSuccess: () => inv(qc, 'subassemblies') });
}

// Shifts
/**
 * Shift list via REST `listShifts`.
 * The server applies include_inactive / line_id filters and returns the line
 * relation plus days_of_week.
 */
export function useShifts(opts: Parameters<typeof ops.listShifts>[0] = {}) {
  return useQuery({
    queryKey: ['shifts', opts],
    queryFn: () => ops.listShifts(opts),
  });
}
export function useShift(id: number | undefined) {
  return useQuery({
    queryKey: ['shift', id],
    queryFn: () => ops.getShift(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateShift() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.createShift, onSuccess: () => inv(qc, 'shifts') });
}
export function useUpdateShift() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<ops.CreateShiftPayload> }) => ops.updateShift(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'shifts'),
  });
}
export function useDeleteShift() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: ops.deleteShift, onSuccess: () => inv(qc, 'shifts') });
}
