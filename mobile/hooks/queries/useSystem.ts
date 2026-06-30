import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as s from '@/api/system';

export function useSettings() {
  return useQuery({ queryKey: ['system-settings'], queryFn: s.listSettings });
}
export function useSetting(key: string | undefined) {
  return useQuery({
    queryKey: ['system-setting', key],
    queryFn: () => s.getSetting(key as string),
    enabled: typeof key === 'string' && key.length > 0,
  });
}
export function useUpdateSetting() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { key: string; value: unknown }) => s.updateSetting(vars.key, vars.value),
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['system-settings'] });
      qc.invalidateQueries({ queryKey: ['system-setting', vars.key] });
    },
  });
}

export function useModules() {
  return useQuery({ queryKey: ['system-modules'], queryFn: s.listModules });
}
export function useToggleModule() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { name: string; enabled: boolean }) =>
      vars.enabled ? s.enableModule(vars.name) : s.disableModule(vars.name),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['system-modules'] }),
  });
}

// Polls /api/v1/system/schedule every 30s while the screen is mounted — mirrors
// the live schedule planner on web (admin.schedule.check-updates).
export function useScheduleEvents(opts: Parameters<typeof s.listScheduleEvents>[0]) {
  return useQuery({
    queryKey: ['system-schedule', opts],
    queryFn: () => s.listScheduleEvents(opts),
    enabled: !!opts.from && !!opts.to,
    refetchInterval: 30_000,
  });
}

// TODO(api/shift-attendance): mocked client-side until the backend ships
// /api/v1/system/operators-on-shift. The mobile schedule screen passes
// workers + lines (already loaded for other UI) into the mock generator.
// When the backend lands, drop the workers/lines params and fetch directly.
export function useOperatorsOnShift(
  date: string | undefined,
  workers: Parameters<typeof s.listOperatorsOnShift>[1],
  lines: Parameters<typeof s.listOperatorsOnShift>[2],
) {
  return useQuery({
    queryKey: [
      'system-operators-on-shift',
      date ?? null,
      (workers ?? []).map((w) => w.id),
      (lines ?? []).map((l) => l.id),
    ],
    queryFn: () => s.listOperatorsOnShift(date, workers, lines),
    refetchInterval: 60_000,
  });
}

export function useAlerts(type: Parameters<typeof s.listAlerts>[0] = 'all') {
  return useQuery({
    queryKey: ['system-alerts', type],
    queryFn: () => s.listAlerts(type),
    refetchInterval: 30_000,
  });
}
export function useAlertCounts() {
  return useQuery({
    queryKey: ['system-alerts', 'counts'],
    queryFn: s.getAlertCounts,
    refetchInterval: 30_000,
  });
}

export function useUpdateCheck() {
  return useQuery({ queryKey: ['system-update-check'], queryFn: s.checkForUpdates });
}

/**
 * Reads the dashboard widget config (enabled + sort_order) from the backend.
 * Mobile dashboard maps widget_id → built-in renderer; unknown widget_ids are
 * skipped silently so a module adding new widgets on web doesn't break mobile.
 */
export function useDashboardWidgets() {
  return useQuery({
    queryKey: ['system-dashboard-widgets'],
    queryFn: s.listDashboardWidgets,
    staleTime: 5 * 60_000, // widgets rarely change; 5min is plenty
  });
}
