import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaintenanceSchedules } from '@/hooks/queries/useMaintenance';
import type { MaintenanceSchedule } from '@/api/maintenanceSchedules';

/**
 * Maintenance schedules — recurring templates that auto-generate events. Lists
 * each schedule with its cadence + next-due. Overdue schedules get a red rail.
 *
 * Backend status: the V1 API endpoint isn't wired yet; the hook returns an
 * empty array with a console warning until upstream ships it. UI is ready.
 */
export function MaintenanceSchedulesList() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const query = useMaintenanceSchedules();
  const schedules = query.data ?? [];

  const dueThisWeek = useMemo(() => {
    const week = 7 * 24 * 60 * 60 * 1000;
    return schedules.filter((s) => {
      const next = nextDueMs(s);
      return next != null && next < week;
    }).length;
  }, [schedules]);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={t('Maintenance schedules')}
        subtitle={`${t('Recurring').toUpperCase()} · ${schedules.length} ${t('templates').toUpperCase()} · ${dueThisWeek} ${t('DUE THIS WEEK').toUpperCase()}`}
      />
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <View style={styles.container}>
          <View style={[styles.helpBlock, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
              ⓘ {t('Schedules auto-generate maintenance events based on cadence. Backend command runs nightly.')}
            </Mono>
          </View>

          {schedules.length === 0 ? (
            <EmptyState
              title={t('No maintenance schedules')}
              subtitle={t('Schedules are managed on the web admin until the V1 API ships.')}
            />
          ) : (
            <Card style={{ padding: 0 }}>
              {schedules.map((s, i, arr) => (
                <ScheduleRow
                  key={s.id}
                  schedule={s}
                  last={i === arr.length - 1}
                  palette={palette}
                />
              ))}
            </Card>
          )}
        </View>
      )}
    </View>
  );
}

function ScheduleRow({
  schedule,
  last,
  palette,
}: {
  schedule: MaintenanceSchedule;
  last: boolean;
  palette: typeof Colors.light;
}) {
  const dueMs = nextDueMs(schedule);
  const overdue = dueMs != null && dueMs < 0;
  return (
    <View
      style={[
        styles.row,
        {
          backgroundColor: overdue ? '#fef0f0' : 'transparent',
          borderLeftWidth: overdue ? 3 : 0,
          borderLeftColor: overdue ? palette.danger : 'transparent',
        },
        last
          ? null
          : { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: palette.border },
      ]}>
      <View
        style={[
          styles.iconBadge,
          { backgroundColor: overdue ? `${palette.danger}22` : palette.surfaceAlt },
        ]}>
        <FontAwesome
          name={overdue ? 'exclamation-triangle' : 'wrench'}
          size={18}
          color={overdue ? palette.danger : palette.textMuted}
        />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
          {schedule.name}
        </Text>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
          {(schedule.tool?.code ?? schedule.tool?.name ?? '—').toUpperCase()}
          {' · '}
          {cadenceLabel(schedule).toUpperCase()}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono
          size={10}
          color={overdue ? palette.danger : palette.textFaint}
          weight="600"
          letterSpacing={0.4}>
          {t_NEXT()}
        </Mono>
        <Mono
          size={12}
          color={overdue ? palette.danger : palette.text}
          weight="700"
          style={{ marginTop: 2 }}>
          {dueLabel(schedule)}
        </Mono>
      </View>
    </View>
  );
}

function cadenceLabel(s: MaintenanceSchedule): string {
  const freq = s.frequency;
  const n = s.interval_value;
  if (freq === 'daily') return `Every ${n} day(s)`;
  if (freq === 'weekly') return `Weekly · every ${n}`;
  if (freq === 'monthly') return 'Monthly';
  if (freq === 'quarterly') return 'Quarterly';
  if (freq === 'annually') return 'Annually';
  if (freq === 'by_hours') return `Every ${n}h runtime`;
  return String(freq);
}

function nextDueMs(s: MaintenanceSchedule): number | null {
  if (!s.next_due_at) return null;
  try {
    return new Date(s.next_due_at).getTime() - Date.now();
  } catch {
    return null;
  }
}

function dueLabel(s: MaintenanceSchedule): string {
  const ms = nextDueMs(s);
  if (ms == null) return '—';
  const days = Math.round(ms / (24 * 60 * 60 * 1000));
  if (days < 0) return `${Math.abs(days)}d ago`;
  if (days === 0) return 'Today';
  if (days === 1) return 'Tomorrow';
  if (days < 14) return `In ${days}d`;
  if (s.next_due_at) {
    try {
      return format(parseISO(s.next_due_at), 'yyyy-MM-dd');
    } catch {
      return '—';
    }
  }
  return '—';
}

// Local helper because hooks can't call useTranslation inside loops. Returns
// the literal "NEXT" — kept as a function so it stays close to dueLabel.
function t_NEXT(): string {
  return 'NEXT';
}

const styles = StyleSheet.create({
  container: { padding: 16, gap: 12 },
  helpBlock: { padding: 12, borderRadius: 10 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  iconBadge: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowTitle: { fontSize: 13, fontWeight: '700' },
});
