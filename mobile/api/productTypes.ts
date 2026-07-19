import { api } from './client';
import type { ApiEnvelope, ProductType } from '@/types/api';

export interface ProductTypeWithCount extends ProductType {
  description?: string | null;
  unit_of_measure?: string | null;
  is_active?: boolean;
  process_templates_count?: number;
  work_orders_count?: number;
}

export interface ProductTypeFilters {
  include_inactive?: boolean;
  q?: string;
}

export const listProductTypes = (
  filters: ProductTypeFilters = {},
): Promise<ProductTypeWithCount[]> =>
  api
    .get<ApiEnvelope<ProductTypeWithCount[]>>('/api/v1/product-types', { params: filters })
    .then((r) => r.data.data);

export const getProductType = (id: number): Promise<ProductTypeWithCount> =>
  api
    .get<ApiEnvelope<ProductTypeWithCount>>(`/api/v1/product-types/${id}`)
    .then((r) => r.data.data);

export interface CreateProductTypePayload {
  code: string;
  name: string;
  description?: string;
  unit_of_measure?: string;
  is_active?: boolean;
}

export const createProductType = (
  payload: CreateProductTypePayload,
): Promise<ProductTypeWithCount> =>
  api
    .post<ApiEnvelope<ProductTypeWithCount>>('/api/v1/product-types', payload)
    .then((r) => r.data.data);

export const updateProductType = (
  id: number,
  payload: Partial<CreateProductTypePayload>,
): Promise<ProductTypeWithCount> =>
  api
    .patch<ApiEnvelope<ProductTypeWithCount>>(`/api/v1/product-types/${id}`, payload)
    .then((r) => r.data.data);

export const deleteProductType = (id: number): Promise<void> =>
  api.delete(`/api/v1/product-types/${id}`).then(() => undefined);

export const toggleProductTypeActive = (id: number): Promise<ProductTypeWithCount> =>
  api
    .post<ApiEnvelope<ProductTypeWithCount>>(`/api/v1/product-types/${id}/toggle-active`)
    .then((r) => r.data.data);
