/**
 * Maintenance Events — 1:1 with the web admin maintenance-events table
 * (Pages/admin/maintenance-events/Index.jsx): the shared DataTable with the
 * web's column set (Title / Type / Target / Scheduled / Status) and per-row
 * actions (edit → detail, delete). Keeps the status filter; rows open the event
 * detail (start/complete/cancel live there). Supervisors/admins get a "New
 * event" button. Data via REST.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteMaintenanceEvent, useMaintenanceEvents } from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { MaintenanceEvent, MaintenanceEventStatus } from '@/api/maintenance';

const STATUSES: MaintenanceEventStatus[] = ['pending', 'in_progress', 'completed', 'cancelled'];
const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function MaintenanceEventsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [status, setStatus] = useState('');
  const canCreate = isSupervisorOrAdmin(useAuthStore((s) => s.user));

  const filters = useMemo(() => (status ? { status: status as MaintenanceEventStatus } : {}), [status]);
  const q = useMaintenanceEvents(filters);
  const del = useDeleteMaintenanceEvent();
  const rows = q.data?.data ?? [];

  const onDelete = (e: MaintenanceEvent) =>
    Alert.alert(t('Delete maintenance event'), t('Delete "{{name}}"?', { name: e.title }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(e.id, { onError: (err: Error) => Alert.alert(t('Could not delete'), err.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Maintenance Events')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 170 }}>
          <Dropdown
            value={status}
            onChange={(v) => setStatus(v as string)}
            placeholder={t('All statuses')}
            options={[{ value: '', label: t('All statuses') }, ...STATUSES.map((s) => ({ value: s, label: humanize(s) }))]}
          />
        </View>
        {canCreate ? (
          <Button title={t('New event')} size="sm" onPress={() => router.push('/maintenance/events/new' as never)} />
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
          <DataTable<MaintenanceEvent>
            data={rows}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['title', 'event_type']}
            emptyText={t('No maintenance events.')}
            onRowPress={(e) => router.push(`/maintenance/events/${e.id}` as never)}
            columns={[
              {
                key: 'title',
                label: t('Title'),
                flex: 1.5,
                render: (e) => <Text numberOfLines={1} style={styles.title}>{e.title}</Text>,
              },
              { key: 'event_type', label: t('Type'), width: 100, render: (e) => humanize(e.event_type) },
              {
                key: 'target',
                label: t('Target'),
                flex: 1,
                render: (e) => e.tool?.name ?? e.line?.name ?? e.workstation?.name ?? '—',
              },
              {
                key: 'scheduled_at',
                label: t('Scheduled'),
                width: 120,
                render: (e) => (
                  <Mono size={10} color={colors.muted}>
                    {e.scheduled_at ? String(e.scheduled_at).slice(0, 16).replace('T', ' ') : '—'}
                  </Mono>
                ),
              },
              { key: 'status', label: t('Status'), width: 120, render: (e) => <StatusPill status={e.status} /> },
            ]}
            actions={(e) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/maintenance/events/${e.id}` as never) },
              { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(e) },
            ]}
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
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
