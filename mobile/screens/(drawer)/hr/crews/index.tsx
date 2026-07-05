/**
 * Crews — 1:1 with the web admin/crews table (Pages/admin/crews/Index.jsx):
 * the shared DataTable (search + Columns menu + pagination) with the web's
 * column set (Code / Name / Division / Leader / Workers / Status) and
 * per-row actions (edit / toggle-active / delete), plus the mobile-only
 * "show inactive" toggle. Data via REST useCrews.
 */
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCrews } from '@/hooks/queries/useHr';
import { useDeleteCrew, useToggleCrewActive } from '@/hooks/mutations/hr';
import type { Crew } from '@/api/hr';

export function CrewsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [includeInactive, setIncludeInactive] = useState(false);
  const q = useCrews(includeInactive);
  const toggle = useToggleCrewActive();
  const del = useDeleteCrew();
  const rows = q.data ?? [];

  const confirmDelete = (crew: Crew) => {
    Alert.alert(t('Delete crew'), t('Delete "{{name}}"?', { name: crew.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(crew.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);
  };

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Crews')}</Text>
        <View style={{ flex: 1 }} />
        <View style={styles.toggle}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Show inactive').toUpperCase()}</Mono>
          <Switch value={includeInactive} onValueChange={setIncludeInactive} />
        </View>
        <Button size="sm" onPress={() => router.push('/hr/crews/new' as never)}>{t('New Crew')}</Button>
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
          <DataTable<Crew>
            data={rows}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['code', 'name']}
            emptyText={t('No crews yet.')}
            onRowPress={(c) => router.push(`/hr/crews/${c.id}` as never)}
            columns={[
              {
                key: 'code',
                label: t('Code'),
                width: 90,
                render: (c) => <Mono size={11} color={colors.muted}>{c.code}</Mono>,
              },
              {
                key: 'name',
                label: t('Name'),
                flex: 1.4,
                render: (c) => <Text numberOfLines={1} style={styles.name}>{c.name}</Text>,
              },
              { key: 'division', label: t('Division'), flex: 1, render: (c) => c.division?.name ?? '—' },
              {
                key: 'leader',
                label: t('Leader'),
                flex: 1,
                render: (c) => (c.leader ? c.leader.name ?? c.leader.username : '—'),
              },
              { key: 'workers_count', label: t('Workers'), width: 80, render: (c) => String(c.workers_count ?? 0) },
              {
                key: 'status',
                label: t('Status'),
                width: 90,
                render: (c) => (
                  <StatusPill status={c.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={t(c.is_active ? 'Active' : 'Inactive')} />
                ),
              },
            ]}
            actions={(c) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/hr/crews/${c.id}` as never) },
              {
                label: c.is_active ? t('Deactivate') : t('Activate'),
                icon: c.is_active ? 'deactivate' : 'activate',
                onPress: () => toggle.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
              },
              { label: t('Delete'), icon: 'delete', onPress: () => confirmDelete(c) },
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
  toggle: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  tableWrap: { paddingHorizontal: 18, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
