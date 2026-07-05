// Light-only v1: no theming here — formatting logic only; all visuals delegate to StatPanel.
import { useTranslation } from 'react-i18next';

import { StatPanel } from '@/components/ui/Detail';
import i18n from '@/lib/i18n';
import type { CustomFieldDefinition, CustomFieldValues } from '@/types/api';

interface Props {
  definitions: CustomFieldDefinition[];
  values?: CustomFieldValues | null;
  title?: string;
}

/**
 * Read-only display of an entity's custom-field values on a detail screen.
 * Driven by the same definitions as the web form, so labels and select option
 * labels match. Renders nothing when no values are set.
 */
export function CustomFieldsPanel({ definitions, values, title = 'Custom fields' }: Props) {
  const { t } = useTranslation();
  if (!values) return null;

  const items = definitions
    .filter((d) => {
      const v = values[d.key];
      return v != null && v !== '' && !(Array.isArray(v) && v.length === 0);
    })
    .map((d) => ({ label: d.label, value: formatValue(d, values[d.key]) }));

  if (items.length === 0) return null;

  return <StatPanel title={t(title)} items={items} />;
}

function formatValue(def: CustomFieldDefinition, value: unknown): string {
  const options = def.config?.options ?? [];

  if (def.type === 'boolean') return value ? i18n.t('Yes') : i18n.t('No');

  if (def.type === 'multiselect') {
    const set = Array.isArray(value) ? (value as unknown[]) : [];
    const labels = options.filter((o) => set.includes(o.value)).map((o) => o.label);
    return labels.length ? labels.join(', ') : '—';
  }

  if (def.type === 'select') {
    return options.find((o) => o.value === value)?.label ?? String(value);
  }

  return String(value);
}
