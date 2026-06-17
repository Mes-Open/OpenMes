import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createBomItem,
  createMaterial,
  deleteBomItem,
  deleteMaterial,
  getBomRequirements,
  getMaterial,
  getMaterialType,
  importMaterials,
  listBomItems,
  listMaterialTypes,
  updateBomItem,
  updateMaterial,
  type BomItemInput,
  type Material,
  type MaterialFilters,
  type MaterialImportRow,
  type MaterialInput,
} from '@/api/bom';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

// ── Material Types ────────────────────────────────────────────────────────

export function useMaterialTypes() {
  return useQuery({
    queryKey: ['material-types'],
    queryFn: listMaterialTypes,
    staleTime: 5 * 60 * 1000,
  });
}

export function useMaterialType(id: number | undefined) {
  return useQuery({
    queryKey: ['material-type', id],
    queryFn: () => getMaterialType(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Materials ─────────────────────────────────────────────────────────────

/**
 * Live material list via Electric `materials` shape.
 * Filtering (material_type_id, is_active, search, external_system) applied client-side.
 * Fidelity note: the shape carries raw table columns only — the REST-computed
 * `material_type` relation object and v0.9 enrichment fields (min_stock_level,
 * supplier_name, supplier_code, unit_price, price_currency, ean, last_stock_sync_at)
 * are absent. Consumers needing those fields should use `useMaterial(id)`.
 */
export function useMaterials(filters: MaterialFilters = {}) {
  const materialTypeId = filters.material_type_id;
  const isActive = filters.is_active;
  const search = filters.search?.trim().toLowerCase() ?? '';
  const externalSystem = filters.external_system;

  return useElectricShape<Row, Material[]>('materials', {
    select: (rows) => {
      let out = rows as unknown as Material[];
      if (materialTypeId !== undefined) out = out.filter((r) => r.material_type_id === materialTypeId);
      if (isActive !== undefined) out = out.filter((r) => (r.is_active ?? true) === isActive);
      if (externalSystem) out = out.filter((r) => r.external_system === externalSystem);
      if (search) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(search) ||
            (r.code ?? '').toLowerCase().includes(search),
        );
      }
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

export function useMaterial(id: number | undefined) {
  return useQuery({
    queryKey: ['material', id],
    queryFn: () => getMaterial(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateMaterial() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: MaterialInput) => createMaterial(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['materials'] }),
  });
}

export function useUpdateMaterial() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<MaterialInput> }) =>
      updateMaterial(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['materials'] }),
  });
}

export function useDeleteMaterial() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteMaterial(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['materials'] }),
  });
}

export function useImportMaterials() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { source_system: string; materials: MaterialImportRow[] }) =>
      importMaterials(vars.source_system, vars.materials),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['materials'] }),
  });
}

// ── BOM Items ─────────────────────────────────────────────────────────────

export function useBomItems(processTemplateId: number | undefined) {
  return useQuery({
    queryKey: ['bom-items', processTemplateId],
    queryFn: () => listBomItems(processTemplateId as number),
    enabled: typeof processTemplateId === 'number' && Number.isFinite(processTemplateId),
  });
}

export function useCreateBomItem(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: BomItemInput) => createBomItem(processTemplateId, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bom-items', processTemplateId] }),
  });
}

export function useUpdateBomItem(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<BomItemInput> }) =>
      updateBomItem(processTemplateId, vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bom-items', processTemplateId] }),
  });
}

export function useDeleteBomItem(processTemplateId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteBomItem(processTemplateId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bom-items', processTemplateId] }),
  });
}

// ── Requirements ──────────────────────────────────────────────────────────

export function useBomRequirements(
  processTemplateId: number | undefined,
  quantity: number | undefined,
) {
  return useQuery({
    queryKey: ['bom-requirements', processTemplateId, quantity],
    queryFn: () => getBomRequirements(processTemplateId as number, quantity as number),
    enabled:
      typeof processTemplateId === 'number' &&
      typeof quantity === 'number' &&
      Number.isFinite(quantity) &&
      quantity > 0,
  });
}
