import { useSettingsStore } from '@/stores/settingsStore';

/**
 * Web variant — there's no reliable RN useColorScheme on web, so we honor
 * the user preference. 'system' falls back to light because we don't observe
 * `prefers-color-scheme` here (could be added if web becomes a real target).
 */
export function useColorScheme(): 'light' | 'dark' {
  const preference = useSettingsStore((s) => s.theme);
  return preference === 'dark' ? 'dark' : 'light';
}
