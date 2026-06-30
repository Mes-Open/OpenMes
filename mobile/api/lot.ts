import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface LotSequence {
  id: number;
  name: string;
  prefix?: string | null;
  suffix?: string | null;
  pad_size?: number;
  year_prefix?: boolean;
  next_value?: number;
  product_type_id?: number | null;
  product_type?: { id: number; name: string } | null;
}

export const listLotSequences = (): Promise<LotSequence[]> =>
  api.get<ApiEnvelope<LotSequence[]>>('/api/v1/lot-sequences').then((r) => r.data.data);

export const getLotSequence = (id: number): Promise<LotSequence> =>
  api.get<ApiEnvelope<LotSequence>>(`/api/v1/lot-sequences/${id}`).then((r) => r.data.data);

export interface LotSequenceInput {
  name: string;
  prefix: string;
  product_type_id?: number | null;
  suffix?: string | null;
  pad_size?: number | null;
  year_prefix?: boolean | null;
}

export const createLotSequence = (input: LotSequenceInput): Promise<LotSequence> =>
  api.post<ApiEnvelope<LotSequence>>('/api/v1/lot-sequences', input).then((r) => r.data.data);

export const updateLotSequence = (
  id: number,
  input: Partial<LotSequenceInput>,
): Promise<LotSequence> =>
  api
    .patch<ApiEnvelope<LotSequence>>(`/api/v1/lot-sequences/${id}`, input)
    .then((r) => r.data.data);

export const deleteLotSequence = (id: number): Promise<void> =>
  api.delete(`/api/v1/lot-sequences/${id}`).then(() => undefined);

export const previewNextLot = (productTypeId?: number): Promise<string | null> => {
  const path = productTypeId ? `/api/v1/lot/preview/${productTypeId}` : '/api/v1/lot/preview';
  return api
    .get<ApiEnvelope<{ next_lot: string | null }>>(path)
    .then((r) => r.data.data.next_lot);
};
