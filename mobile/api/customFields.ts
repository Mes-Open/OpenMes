import { api } from './client';
import type { CustomFieldDefinition } from '@/types/api';

/**
 * Custom-field *definitions* (read-only on mobile in v1). Reuses the Reverb
 * collection snapshot endpoint, which is tenant-scoped server-side.
 *
 * Caveat: the snapshot serves the `config` column as a raw JSON *string*
 * (DB::table query, no Eloquent casts), so we JSON.parse it here. See the note
 * in OpenMes/app/Sync/ShapeRegistry.php.
 */
interface CollectionSnapshot<T> {
  rows: T[];
  channel: string;
  at: number;
}

type RawDefinition = Omit<CustomFieldDefinition, 'config' | 'required' | 'is_active'> & {
  config: string | CustomFieldDefinition['config'] | null;
  required: boolean | number;
  is_active: boolean | number;
};

function normalize(row: RawDefinition): CustomFieldDefinition {
  let config: CustomFieldDefinition['config'] = null;
  if (typeof row.config === 'string') {
    try {
      config = row.config ? JSON.parse(row.config) : null;
    } catch {
      config = null;
    }
  } else {
    config = row.config ?? null;
  }

  return {
    ...row,
    config,
    required: !!row.required,
    is_active: !!row.is_active,
  };
}

/**
 * Active definitions, optionally filtered to one entity-type, sorted for
 * display. Use the result to label/format an entity's `custom_fields`.
 */
export const listCustomFieldDefinitions = (
  entityType?: string,
): Promise<CustomFieldDefinition[]> =>
  api
    .get<CollectionSnapshot<RawDefinition>>('/api/collections/custom_field_definitions')
    .then((r) =>
      r.data.rows
        .map(normalize)
        .filter((d) => d.is_active && (!entityType || d.entity_type === entityType))
        .sort((a, b) => a.position - b.position || a.id - b.id),
    );
