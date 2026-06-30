import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useDeleteMaintenanceSchedule,
  useGenerateScheduleNow,
  useMaintenanceSchedules,
} from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { MaintenanceSchedule } from '@/api/maintenanceSchedules';

/**
 * Recurring maintenance schedules — admin/supervisor manage the templates
 * that auto-generate MaintenanceEvents. Long-press a row to delete; tap the
 * lightning icon to force-generate an event now (skips the lead-time gate).
 */
export function MaintenanceSchedulesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const canManage = isSupervisorOrAdmin(useAuthStore((s) => s.user));

  const q = useMaintenanceSchedules({ per_page: 50 });
  const del = useDeleteMaintenanceSchedule();
  const gen = useGenerateScheduleNow();

  const onDelete = (s: MaintenanceSchedule) => {
    Alert.alert(t('Delete schedule'), s.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(s.id, {
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  const onGenerate = (s: MaintenanceSchedule) => {
    Alert.alert(
      t('Generate now'),
      t('Create one maintenance event from this schedule immediately?'),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Generate'),
          onPress: () =>
            gen.mutate(s.id, {
              onSuccess: (r) =>
                Alert.alert(t('Done'), `${r.generated} ${t('event(s) generated')}`),
              onError: (e: Error) => Alert.alert(t('Could not generate'), e.message),
            }),
        },
      ],
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Maintenance schedules')}
        subtitle={`MAINTENANCE · ${q.data?.length ?? 0} ${t('schedules').toUpperCase()}`}
      />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <LegendList
          data={q.data ?? []}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          ListEmptyComponent={
            <EmptyState
              title={t('No schedules yet')}
              subtitle={t('Add one to auto-generate recurring preventive maintenance events.')}
            />
          }
          ListFooterComponent={
            canManage ? (
              <Pressable
                onPress={() => router.push('/maintenance/schedules/new' as never)}
                style={({ pressed }) => [
                  styles.addBtn,
                  { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
                ]}>
                <FontAwesome name="plus" size={12} color={palette.text} />
                <Mono size={11} weight="700" letterSpacing={0.5} color={palette.text}>
                  {t('NEW SCHEDULE')}
                </Mono>
              </Pressable>
            ) : null
          }
          renderItem={({ item: s }) => (
            <Pressable
              onPress={
                canManage
                  ? () => router.push(`/maintenance/schedules/${s.id}/edit` as never)
                  : undefined
              }
              onLongPress={canManage ? () => onDelete(s) : undefined}
              style={({ pressed }) => [
                styles.row,
                {
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                  opacity: pressed ? 0.85 : 1,
                  borderLeftColor: s.is_active ? palette.success : palette.textFaint,
                },
              ]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                  {s.name}
                </Text>
                <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
                  {`${s.frequency.toUpperCase()} · ${t('EVERY')} ${s.interval_value}`}
                  {s.event_type ? ` · ${s.event_type.toUpperCase()}` : ''}
                </Mono>
                <Mono size={10.5} color={palette.textMuted} style={{ marginTop: 2 }}>
                  {targetLabel(s, t)}
                  {s.next_due_at
                    ? `  ·  ${t('NEXT').toUpperCase()} ${formatDue(s.next_due_at)}`
                    : ''}
                </Mono>
              </View>
              {canManage ? (
                <Pressable
                  onPress={() => onGenerate(s)}
                  hitSlop={10}
                  style={({ pressed }) => [styles.genBtn, { opacity: pressed ? 0.6 : 1 }]}>
                  <FontAwesome name="bolt" size={14} color={BRAND.amber} />
                </Pressable>
              ) : null}
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

function targetLabel(
  s: MaintenanceSchedule,
  t: (k: string) => string,
): string {
  if (s.tool) return `${t('TOOL').toUpperCase()} · ${s.tool.name}`;
  if (s.line) return `${t('LINE').toUpperCase()} · ${s.line.name}`;
  if (s.workstation) return `${t('WORKSTATION').toUpperCase()} · ${s.workstation.name}`;
  return t('NO TARGET').toUpperCase();
}

function formatDue(iso: string): string {
  try {
    return format(parseISO(iso), 'dd MMM, HH:mm');
  } catch {
    return iso.slice(0, 16).replace('T', ' ');
  }
}

const styles = StyleSheet.create({
  list: { padding: 18, paddingBottom: 32 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  name: { fontSize: 14, fontWeight: '600' },
  genBtn: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addBtn: {
    marginTop: 14,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
