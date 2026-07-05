/**
 * Maintenance schedules (manage) — 1:1 with the web admin maintenance-schedules
 * table (Pages/admin/maintenance-schedules/Index.jsx): the shared DataTable with
 * the web's column set (Name / Target / Frequency / Every / Next Due / Status)
 * and per-row actions (edit, delete) plus the mobile generate-now flow.
 * Admins/supervisors get "+ New Schedule". Data + mutations via useMaintenance.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteMaintenanceSchedule,
  useGenerateScheduleNow,
  useMaintenanceSchedules,
} from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { MaintenanceSchedule } from '@/api/maintenanceSchedules';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function MaintenanceSchedulesList() {
  const { t } = useTranslation();
  const router = useRouter();
  const canManage = isSupervisorOrAdmin(useAuthStore((s) => s.user));

  const q = useMaintenanceSchedules({ per_page: 50 });
  const del = useDeleteMaintenanceSchedule();
  const gen = useGenerateScheduleNow();
  const rows = q.data ?? [];

  const onDelete = (s: MaintenanceSchedule) =>
    Alert.alert(t('Delete schedule'), s.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(s.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  const onGenerate = (s: MaintenanceSchedule) =>
    Alert.alert(t('Generate now'), t('Create one maintenance event from this schedule immediately?'), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Generate'),
        onPress: () =>
          gen.mutate(s.id, {
            onSuccess: (r) => Alert.alert(t('Done'), `${r.generated} ${t('event(s) generated')}`),
            onError: (e: Error) => Alert.alert(t('Could not generate'), e.message),
          }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Maintenance Schedules')}</Text>
        <View style={{ flex: 1 }} />
        {canManage ? (
          <Button title={t('+ New Schedule')} size="sm" onPress={() => router.push('/maintenance/schedules/new' as never)} />
        ) : null}
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <DataTable<MaintenanceSchedule>
            data={rows}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['name', 'frequency']}
            emptyText={t('No maintenance schedules yet.')}
            onRowPress={canManage ? (s) => router.push(`/maintenance/schedules/${s.id}/edit` as never) : undefined}
            columns={[
              {
                key: 'name',
                label: t('Name'),
                flex: 1.4,
                render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
              },
              {
                key: 'target',
                label: t('Target'),
                flex: 1,
                render: (s) => s.tool?.name ?? s.line?.name ?? s.workstation?.name ?? '—',
              },
              { key: 'frequency', label: t('Frequency'), width: 92, render: (s) => humanize(s.frequency) },
              {
                key: 'interval_value',
                label: t('Every'),
                width: 56,
                render: (s) => <Mono size={11} color={colors.muted}>{String(s.interval_value)}</Mono>,
              },
              {
                key: 'next_due_at',
                label: t('Next Due'),
                width: 120,
                render: (s) => (
                  <Mono size={10} color={colors.muted}>
                    {s.next_due_at ? String(s.next_due_at).slice(0, 16).replace('T', ' ') : '—'}
                  </Mono>
                ),
              },
              {
                key: 'is_active',
                label: t('Status'),
                width: 90,
                render: (s) => (
                  <StatusPill status={s.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={s.is_active ? t('Active') : t('Inactive')} />
                ),
              },
            ]}
            actions={
              canManage
                ? (s) => [
                    { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/maintenance/schedules/${s.id}/edit` as never) },
                    { label: t('Generate now'), onPress: () => onGenerate(s) },
                    { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(s) },
                  ]
                : undefined
            }
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
