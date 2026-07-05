/**
 * Shifts — 1:1 with the web admin shifts table (Pages/admin/shifts/Index.jsx):
 * the shared DataTable (search + Columns menu) with the web's column set
 * (Code / Name / Line / Start / End / Order / Status) and per-row actions
 * (Edit / Delete). A status filter toggles inactive rows. Data via REST.
 */
import { useMemo, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteShift, useShifts } from '@/hooks/queries/useOps';

type Shift = {
  id: number;
  code?: string | null;
  name: string;
  line?: { name: string } | null;
  start_time?: string | null;
  end_time?: string | null;
  sort_order?: number | null;
  is_active?: boolean;
};

export function ShiftsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [scope, setScope] = useState('active');

  const includeInactive = scope === 'all';
  const query = useShifts({ include_inactive: includeInactive });
  const del = useDeleteShift();
  const rows = query.data ?? [];

  const options = useMemo(
    () => [
      { value: 'active', label: t('Active only') },
      { value: 'all', label: t('All statuses') },
    ],
    [t],
  );

  const onDelete = (shift: Shift) =>
    Alert.alert(t('Delete shift'), t('Delete "{{name}}"?', { name: shift.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(shift.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError && !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Shifts')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 150 }}>
          <Dropdown value={scope} onChange={(v) => setScope(v as string)} options={options} />
        </View>
        <Button title={t('+ New Shift')} size="sm" onPress={() => router.push('/production/shifts/new' as never)} />
      </View>

      <DataTable<Shift>
        data={rows as unknown as Shift[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No shifts yet.')}
        onRowPress={(s) => router.push(`/production/shifts/${s.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 90,
            render: (s) => <Mono size={11} color={colors.muted}>{s.code ?? '—'}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
          },
          { key: 'line', label: t('Line'), flex: 1, render: (s) => s.line ? s.line.name : t('Global') },
          {
            key: 'start_time',
            label: t('Start'),
            width: 64,
            render: (s) => <Mono size={11} color={colors.muted}>{s.start_time?.slice(0, 5) ?? '—'}</Mono>,
          },
          {
            key: 'end_time',
            label: t('End'),
            width: 64,
            render: (s) => <Mono size={11} color={colors.muted}>{s.end_time?.slice(0, 5) ?? '—'}</Mono>,
          },
          {
            key: 'sort_order',
            label: t('Order'),
            width: 60,
            render: (s) => <Mono size={11} color={colors.muted}>{s.sort_order != null ? String(s.sort_order) : '—'}</Mono>,
          },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (s) => <StatusPill status={s.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={s.is_active ? t('Active') : t('Inactive')} />,
          },
        ]}
        actions={(s) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/production/shifts/${s.id}` as never) },
          { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(s) },
        ]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
