import { useQuery } from '@tanstack/react-query';

import { getProductType, type ProductTypeFilters, type ProductTypeWithCount } from '@/api/productTypes';
import { getTemplate, listTemplatesForProductType } from '@/api/processTemplates';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

/**
 * Live product types via Electric (migrated from REST `listProductTypes`).
 * Returns the same React-Query-compatible `{ data, isLoading, ... }` shape so
 * consuming screens are unchanged. Filtering (`include_inactive`, `q`) is applied
 * client-side over the synced row set.
 *
 * Fidelity note: the `product_types` shape carries raw table columns only, so
 * the REST-computed `process_templates_count` is not present here (it's an
 * optional field; pickers that only need name/code are unaffected).
 */
export function useProductTypes(filters: ProductTypeFilters = {}) {
  const includeInactive = filters.include_inactive ?? false;
  const q = filters.q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, ProductTypeWithCount[]>('product_types', {
    select: (rows) => {
      let out = rows as unknown as ProductTypeWithCount[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
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
