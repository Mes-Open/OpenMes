import { useQuery } from '@tanstack/react-query';

import {
  getProductType,
  listProductTypes,
  type ProductTypeFilters,
} from '@/api/productTypes';
import { getTemplate, listTemplatesForProductType } from '@/api/processTemplates';

/**
 * Product types via REST (`/api/v1/product-types`). The server applies the
 * `include_inactive` / `q` filters and returns the per-row `process_templates_count`
 * and `work_orders_count` the card grid shows.
 */
export function useProductTypes(filters: ProductTypeFilters = {}) {
  return useQuery({
    queryKey: ['product-types', filters],
    queryFn: () => listProductTypes(filters),
  });
}

export function useProductType(id: number | undefined) {
  return useQuery({
    queryKey: ['product-type', id],
    queryFn: () => getProductType(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useProcessTemplatesForProductType(productTypeId: number | undefined, includeInactive = false) {
  return useQuery({
    queryKey: ['process-templates', productTypeId, includeInactive],
    queryFn: () => listTemplatesForProductType(productTypeId as number, includeInactive),
    enabled: typeof productTypeId === 'number' && Number.isFinite(productTypeId),
  });
}

export function useProcessTemplate(id: number | undefined) {
  return useQuery({
    queryKey: ['process-template', id],
    queryFn: () => getTemplate(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
