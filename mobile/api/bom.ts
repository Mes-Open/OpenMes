import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

// ── Material Types ────────────────────────────────────────────────────────

export interface MaterialType {
  id: number;
  code: string;
  name: string;
  description?: string | null;
}

export const listMaterialTypes = (): Promise<MaterialType[]> =>
  api.get<ApiEnvelope<MaterialType[]>>('/api/v1/material-types').then((r) => r.data.data);

export const getMaterialType = (id: number): Promise<MaterialType> =>
  api.get<ApiEnvelope<MaterialType>>(`/api/v1/material-types/${id}`).then((r) => r.data.data);

// ── Materials ─────────────────────────────────────────────────────────────

export type MaterialTrackingType = 'none' | 'batch' | 'serial';

export interface Material {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  material_type_id: number;
  material_type?: MaterialType | null;
  unit_of_measure?: string | null;
  tracking_type?: MaterialTrackingType | null;
  default_scrap_percentage?: number | string | null;
  external_code?: string | null;
  external_system?: string | null;
  is_active?: boolean;
  // v0.9.0 enrichment
  stock_quantity?: number | string | null;
  reserved_quantity?: number | string | null;
  available_quantity?: number | string | null;
  min_stock_level?: number | string | null;
  supplier_name?: string | null;
  supplier_code?: string | null;
  unit_price?: number | string | null;
  price_currency?: string | null;
  ean?: string | null;
  last_stock_sync_at?: string | null;
  // Count of BOM lines referencing this material (materials index).
  bom_items_count?: number;
  // Detail-only enrichment (materials show).
  lots?: MaterialLotSummary[];
  recent_movements?: MaterialStockMovement[];
  bom_usage?: MaterialBomUsage[];
}

export interface MaterialLotSummary {
  id: number;
  lot_number: string;
  supplier_lot_no?: string | null;
  quantity_received: number | string;
  quantity_available: number | string;
  expiry_date?: string | null;
  status: string;
  is_expired: boolean;
}

export interface MaterialStockMovement {
  id: number;
  performed_at?: string | null;
  movement_type: string;
  quantity: number | string;
  balance_after: number | string;
  source_type?: string | null;
  source_id?: number | null;
  reason?: string | null;
  performed_by?: { name: string } | null;
}

export interface MaterialBomUsage {
  id: number;
  quantity_per_unit: number | string;
  scrap_percentage: number | string;
  process_template?: {
    name: string;
    product_type?: { name: string } | null;
  } | null;
}

export interface MaterialFilters {
  material_type_id?: number;
  is_active?: boolean;
  search?: string;
  external_system?: string;
}

export const listMaterials = (filters: MaterialFilters = {}): Promise<Material[]> =>
  api
    .get<ApiEnvelope<Material[]>>('/api/v1/materials', { params: filters })
    .then((r) => r.data.data);

export const getMaterial = (id: number): Promise<Material> =>
  api.get<ApiEnvelope<Material>>(`/api/v1/materials/${id}`).then((r) => r.data.data);

export interface MaterialInput {
  code: string;
  name: string;
  material_type_id: number;
  description?: string | null;
  unit_of_measure?: string | null;
  tracking_type?: MaterialTrackingType | null;
  default_scrap_percentage?: number | null;
  external_code?: string | null;
  external_system?: string | null;
  is_active?: boolean;
}

export const createMaterial = (input: MaterialInput): Promise<Material> =>
  api.post<ApiEnvelope<Material>>('/api/v1/materials', input).then((r) => r.data.data);

export const updateMaterial = (
  id: number,
  input: Partial<MaterialInput>,
): Promise<Material> =>
  api.patch<ApiEnvelope<Material>>(`/api/v1/materials/${id}`, input).then((r) => r.data.data);

export const deleteMaterial = (id: number): Promise<void> =>
  api.delete(`/api/v1/materials/${id}`).then(() => undefined);

export interface MaterialImportRow {
  external_code: string;
  name: string;
  type?: string | null;
  unit?: string | null;
  description?: string | null;
}

export interface MaterialImportResult {
  created: number;
  updated: number;
  skipped?: number;
  errors?: Array<{ row: number; message: string }>;
}

export const importMaterials = (
  source_system: string,
  materials: MaterialImportRow[],
): Promise<MaterialImportResult> =>
  api
    .post<ApiEnvelope<MaterialImportResult>>('/api/v1/materials/import', {
      source_system,
      materials,
    })
    .then((r) => r.data.data);

// ── BOM Items (per process template) ──────────────────────────────────────

export type BomConsumedAt = 'start' | 'during' | 'end';

export interface BomItem {
  id: number;
  process_template_id: number;
  material_id: number;
  material?: Material | null;
  template_step_id?: number | null;
  template_step?: { id: number; step_number?: number; name: string } | null;
  quantity_per_unit: number | string;
  scrap_percentage: number | string;
  consumed_at?: BomConsumedAt | null;
  sort_order?: number;
  notes?: string | null;
}

export const listBomItems = (processTemplateId: number): Promise<BomItem[]> =>
  api
    .get<ApiEnvelope<BomItem[]>>(`/api/v1/process-templates/${processTemplateId}/bom-items`)
    .then((r) => r.data.data);

export interface BomItemInput {
  material_id: number;
  template_step_id?: number | null;
  quantity_per_unit: number;
  scrap_percentage?: number | null;
  consumed_at?: BomConsumedAt | null;
  sort_order?: number | null;
  notes?: string | null;
}

export const createBomItem = (
  processTemplateId: number,
  input: BomItemInput,
): Promise<BomItem> =>
  api
    .post<ApiEnvelope<BomItem>>(
      `/api/v1/process-templates/${processTemplateId}/bom-items`,
      input,
    )
    .then((r) => r.data.data);

export const updateBomItem = (
  processTemplateId: number,
  bomItemId: number,
  input: Partial<BomItemInput>,
): Promise<BomItem> =>
  api
    .patch<ApiEnvelope<BomItem>>(
      `/api/v1/process-templates/${processTemplateId}/bom-items/${bomItemId}`,
      input,
    )
    .then((r) => r.data.data);

export const deleteBomItem = (processTemplateId: number, bomItemId: number): Promise<void> =>
  api
    .delete(`/api/v1/process-templates/${processTemplateId}/bom-items/${bomItemId}`)
    .then(() => undefined);

// ── Requirements ──────────────────────────────────────────────────────────

export interface BomRequirement {
  material_id: number;
  material_code: string;
  material_name: string;
  material_type: string;
  unit_of_measure?: string | null;
  quantity_per_unit: number;
  base_qty: number;
  scrap_qty: number;
  required_qty: number;
  step_number?: number | null;
  consumed_at?: BomConsumedAt | null;
}

export const getBomRequirements = (
  processTemplateId: number,
  quantity: number,
): Promise<BomRequirement[]> =>
  api
    .get<ApiEnvelope<BomRequirement[]>>(
      `/api/v1/process-templates/${processTemplateId}/bom-items/requirements`,
      { params: { quantity } },
    )
    .then((r) => r.data.data);
