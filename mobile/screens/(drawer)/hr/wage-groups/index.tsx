/**
 * Wage groups — 1:1 with the web admin wage-groups page (Pages/admin/
 * wage-groups/Index.jsx): the shared DataTable (search + Columns menu +
 * pagination) with the web's column set (Code / Name / Base Rate / Workers /
 * Status) and per-row actions (edit / toggle-active / delete).
 */
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWageGroups } from '@/hooks/queries/useHr';
import { useDeleteWageGroup, useToggleWageGroupActive } from '@/hooks/mutations/hr';
import type { WageGroup } from '@/api/hr';

export function WageGroupsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [includeInactive, setIncludeInactive] = useState(false);
  const q = useWageGroups(includeInactive);
  const toggle = useToggleWageGroupActive();
  const del = useDeleteWageGroup();
  const rows = q.data ?? [];

  const onDelete = (group: WageGroup) =>
    Alert.alert(t('Delete wage group'), t('Delete "{{name}}"?', { name: group.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(group.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Wage Groups')}</Text>
        <View style={{ flex: 1 }} />
        <View style={styles.toggle}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Show inactive').toUpperCase()}</Mono>
          <Switch value={includeInactive} onValueChange={setIncludeInactive} />
        </View>
        <Button title={t('+ New Wage Group')} size="sm" onPress={() => router.push('/hr/wage-groups/new' as never)} />
      </View>

      <DataTable<WageGroup>
        data={rows as unknown as WageGroup[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No wage groups yet.')}
        onRowPress={(g) => router.push(`/hr/wage-groups/${g.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 90,
            render: (g) => <Mono size={11} color={colors.muted}>{g.code}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (g) => <Text numberOfLines={1} style={styles.name}>{g.name}</Text>,
          },
          {
            key: 'rate',
            label: t('Base Rate'),
            flex: 1,
            render: (g) => {
              const rate = g.base_hourly_rate != null ? `${g.base_hourly_rate} ${g.currency ?? ''}`.trim() : '—';
              return <Mono size={11} color={colors.muted}>{rate}</Mono>;
            },
          },
          { key: 'workers', label: t('Workers'), width: 80, render: (g) => String(g.workers_count ?? 0) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (g) => (
              <StatusPill status={g.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={t(g.is_active ? 'Active' : 'Inactive')} />
            ),
          },
        ]}
        actions={(g) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/hr/wage-groups/${g.id}` as never) },
          {
            label: g.is_active ? t('Deactivate') : t('Activate'),
            icon: g.is_active ? 'deactivate' : 'activate',
            onPress: () => toggle.mutate(g.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(g) },
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
  toggle: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
