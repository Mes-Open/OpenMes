import { useColorScheme as useRNColorScheme } from 'react-native';

import { useSettingsStore } from '@/stores/settingsStore';

/**
 * Resolves the active color scheme. Order of precedence:
 *   1. User's explicit pick in settings ('light' | 'dark')
 *   2. The OS-reported scheme (when preference is 'system')
 *   3. 'light' fallback
 *
 * Components consume this everywhere instead of `react-native`'s
 * `useColorScheme` so they all flip together when the user toggles theme.
 */
export function useColorScheme(): 'light' | 'dark' {
  const preference = useSettingsStore((s) => s.theme);
  const osScheme = useRNColorScheme();
  if (preference === 'light' || preference === 'dark') return preference;
  return osScheme === 'dark' ? 'dark' : 'light';
}
