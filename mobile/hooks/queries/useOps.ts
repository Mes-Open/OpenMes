import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as ops from '@/api/ops';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// Companies
/**
 * Live company list via Electric `companies` shape.
 * Filtering (include_inactive, type, q) applied client-side.
 */
export function useCompanies(opts: Parameters<typeof ops.listCompanies>[0] = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const type = opts.type;
  const q = opts.q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, ops.Company[]>('companies', {
    select: (rows) => {
      let out = rows as unknown as ops.Company[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (type) out = out.filter((r) => r.type === type);
      if (q) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(q) ||
            (r.code ?? '').toLowerCase().includes(q),
        );
      }
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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
 * Live cost-source list via Electric `cost_sources` shape.
 * The `includeInactive` flag is applied client-side.
 */
export function useCostSources(includeInactive = false) {
  return useElectricShape<Row, ops.CostSource[]>('cost_sources', {
    select: (rows) => {
      let out = rows as unknown as ops.CostSource[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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
 * Live anomaly-reason list via Electric `anomaly_reasons` shape.
 * Filtering (include_inactive, category) applied client-side.
 */
export function useAnomalyReasons(opts: Parameters<typeof ops.listAnomalyReasons>[0] = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const category = opts.category;

  return useElectricShape<Row, ops.AnomalyReason[]>('anomaly_reasons', {
    select: (rows) => {
      let out = rows as unknown as ops.AnomalyReason[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (category) out = out.filter((r) => r.category === category);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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
 * Live subassembly list via Electric `subassemblies` shape.
 * Filtering (include_inactive, product_type_id) applied client-side.
 * Note: the REST `product_type` relation object is not present in the shape
 * (only the raw `product_type_id` FK column is available).
 */
export function useSubassemblies(opts: Parameters<typeof ops.listSubassemblies>[0] = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const productTypeId = opts.product_type_id;

  return useElectricShape<Row, ops.Subassembly[]>('subassemblies', {
    select: (rows) => {
      let out = rows as unknown as ops.Subassembly[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (productTypeId !== undefined) out = out.filter((r) => r.product_type_id === productTypeId);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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
 * Live shift list via Electric `shifts` shape.
 * Filtering (include_inactive, line_id) applied client-side.
 * Note: the REST `line` relation object is not present in the shape
 * (only the raw `line_id` FK column is available). `days_of_week` is
 * also absent from the shape columns — consumers relying on that field
 * should use the detail hook (`useShift`) instead.
 */
export function useShifts(opts: Parameters<typeof ops.listShifts>[0] = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const lineId = opts.line_id;

  return useElectricShape<Row, ops.Shift[]>('shifts', {
    select: (rows) => {
      let out = rows as unknown as ops.Shift[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (lineId !== undefined) out = out.filter((r) => r.line_id === lineId);
      // Shape has sort_order; Shift type doesn't declare it, so cast through Row.
      return [...out].sort(
        (a, b) =>
          ((a as unknown as Record<string, unknown>).sort_order as number ?? 0) -
          ((b as unknown as Record<string, unknown>).sort_order as number ?? 0),
      );
    },
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
