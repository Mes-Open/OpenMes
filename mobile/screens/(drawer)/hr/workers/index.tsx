/**
 * Workers — 1:1 with the web admin/workers table (Pages/admin/workers/Index.jsx):
 * the shared DataTable (search + Columns menu + pagination) with the web's
 * column set (Code / Name / Email / Crew / Class / Status) and per-row actions
 * (edit / toggle-active / delete), plus the mobile-only crew filter. Rows open
 * the worker edit screen.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCrews, useWorkers } from '@/hooks/queries/useHr';
import { useDeleteWorker, useUpdateWorker } from '@/hooks/mutations/hr';
import type { Worker } from '@/api/hr';

export function WorkersList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [q, setQ] = useState('');
  const [crewId, setCrewId] = useState('');

  const filters = useMemo(
    () => ({ q: q.trim() || undefined, crew_id: crewId ? Number(crewId) : undefined }),
    [q, crewId],
  );
  const query = useWorkers(filters);
  const crews = useCrews(false).data ?? [];
  const items = query.data?.data ?? [];
  const update = useUpdateWorker();
  const del = useDeleteWorker();

  const confirmDelete = (worker: Worker) =>
    Alert.alert(t('Delete worker'), t('Delete "{{name}}"?', { name: worker.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(worker.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Workers')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 160 }}>
          <Button title={t('+ New Worker')} size="sm" onPress={() => router.push('/hr/workers/new' as never)} />
        </View>
      </View>

      <View style={styles.filters}>
        <View style={{ flex: 1 }}>
          <SearchField value={q} onChange={setQ} placeholder={t('name, code, email')} />
        </View>
        <View style={{ width: 180 }}>
          <Dropdown
            value={crewId}
            onChange={(v) => setCrewId(v as string)}
            placeholder={t('All crews')}
            options={[{ value: '', label: t('All crews') }, ...crews.map((c) => ({ value: String(c.id), label: c.name }))]}
          />
        </View>
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<Worker>
            data={items}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['code', 'name', 'email']}
            emptyText={t('No workers yet.')}
            onRowPress={(w) => router.push(`/hr/workers/${w.id}` as never)}
            columns={[
              {
                key: 'code',
                label: t('Code'),
                width: 92,
                render: (w) => <Mono size={11} color={colors.muted}>{w.code}</Mono>,
              },
              {
                key: 'name',
                label: t('Name'),
                flex: 1.2,
                render: (w) => <Text numberOfLines={1} style={styles.name}>{w.name}</Text>,
              },
              { key: 'email', label: t('Email'), flex: 1.5, render: (w) => w.email ?? '—' },
              { key: 'crew', label: t('Crew'), flex: 1, render: (w) => w.crew?.name ?? '—' },
              { key: 'class', label: t('Class'), flex: 1, render: (w) => w.personnel_class?.name ?? '—' },
              {
                key: 'status',
                label: t('Status'),
                width: 90,
                render: (w) => (
                  <StatusPill status={w.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={w.is_active ? t('Active') : t('Inactive')} />
                ),
              },
            ]}
            actions={(w) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/hr/workers/${w.id}` as never) },
              {
                label: w.is_active ? t('Deactivate') : t('Activate'),
                icon: w.is_active ? 'deactivate' : 'activate',
                onPress: () =>
                  update.mutate(
                    { id: w.id, payload: { is_active: !w.is_active } },
                    { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
                  ),
              },
              { label: t('Delete'), icon: 'delete', onPress: () => confirmDelete(w) },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 12 },
  tableWrap: { paddingHorizontal: 18, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
