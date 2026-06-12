// Light-only v1: Colors[scheme] switching dropped — visuals delegate to @openmes/ui SearchField (Geist White tokens).
import { useTranslation } from 'react-i18next';

import { SearchField } from '@openmes/ui/native';

interface Props {
  placeholder?: string;
  value?: string;
  onChangeText?: (v: string) => void;
}

/**
 * Search bar matching the catalog list pattern from the design.
 * Placeholder auto-translates so call sites pass English keys.
 */
export function SearchBar({ placeholder = 'Search', value, onChangeText }: Props) {
  const { t } = useTranslation();

  return (
    <SearchField
      value={value ?? ''}
      onChange={(v) => onChangeText?.(v)}
      placeholder={t(placeholder)}
    />
  );
}
