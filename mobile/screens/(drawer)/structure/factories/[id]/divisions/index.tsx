/**
 * Divisions (factory-scoped) — 1:1 with the web admin divisions page (Pages/
 * admin/divisions/Index.jsx): the shared DataTable (search + Columns menu +
 * pagination) with the web's column set, minus "Factory" (this screen is
 * already scoped to one factory, so every row shares the same factory — the
 * column would be redundant) — Code / Name / Crews / Status — and per-row
 * actions (Edit / toggle-active / Delete). The mobile Division type has no
 */
import { useMemo, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteDivision, useFactoryDivisions, useToggleDivisionActive } from '@/hooks/queries/useOrgStructure';
import type { Division } from '@/api/orgStructure';

export function DivisionsList() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const factoryId = Number(id);
  const router = useRouter();
  const [scope, setScope] = useState('');
  const includeInactive = scope === 'all';

  const query = useFactoryDivisions(factoryId, includeInactive);
  const toggle = useToggleDivisionActive();
  const del = useDeleteDivision();
  const rows = query.data ?? [];

  const options = useMemo(
    () => [
      { value: '', label: t('Active only') },
      { value: 'all', label: t('All') },
    ],
    [t],
  );

  const onDelete = (division: Division) =>
    Alert.alert(t('Delete division'), t('Delete "{{name}}"?', { name: division.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(division.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Divisions')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 150 }}>
          <Dropdown value={scope} onChange={(v) => setScope(v as string)} placeholder={t('Active only')} options={options} />
        </View>
        <Button
          title={t('New division')}
          size="sm"
          onPress={() => router.push(`/structure/factories/${factoryId}/divisions/new` as never)}
        />
      </View>

      <DataTable<Division>
        data={rows as unknown as Division[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No divisions yet.')}
        onRowPress={(d) => router.push(`/structure/divisions/${d.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 110,
            render: (d) => <Mono size={11} color={colors.muted}>{d.code ?? '—'}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (d) => <Text numberOfLines={1} style={styles.name}>{d.name}</Text>,
          },
          { key: 'crews', label: t('Crews'), width: 80, render: (d) => String(d.crews_count ?? 0) },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (d) => (
              <StatusPill status={d.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={d.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(d) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/divisions/${d.id}` as never) },
          {
            label: d.is_active === false ? t('Activate') : t('Deactivate'),
            icon: d.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(d.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(d) },
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
