/**
 * Materials — 1:1 with the web admin/materials table (Pages/admin/materials/Index.jsx):
 * the shared DataTable with the web's column set (Code / Name / Type / UoM /
 * Tracking / Stock / In BOMs / Status) and per-row actions (Edit / Delete). The
 * web toggle-active has no mobile REST counterpart, so it's omitted. Search is
 * sent server-side, so the DataTable's own search box stays off. Data via REST
 * useMaterials.
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMaterials, useDeleteMaterial } from '@/hooks/queries/useBom';
import type { Material } from '@/api/bom';

const TRACKING_LABELS: Record<string, string> = {
  none: 'None',
  batch: 'Batch',
  serial: 'Serial',
};

export function MaterialsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [q, setQ] = useState('');

  const query = useMaterials({ search: q.trim() || undefined });
  const del = useDeleteMaterial();
  const rows = query.data ?? [];

  const onDelete = (m: Material) =>
    Alert.alert(t('Delete material'), t('Delete "{{name}}"?', { name: m.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(m.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Materials')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Material')} size="sm" onPress={() => router.push('/(drawer)/admin/materials/new' as never)} />
      </View>

      <View style={styles.filters}>
        <SearchField value={q} onChange={setQ} placeholder={t('Search by code or name')} />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<Material>
            data={rows as Material[]}
            searchable={false}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No materials yet.')}
            onRowPress={(m) => router.push(`/(drawer)/admin/materials/${m.id}` as never)}
            columns={[
              { key: 'code', label: t('Code'), width: 96, render: (m) => <Mono size={11} color={colors.muted}>{m.code}</Mono> },
              { key: 'name', label: t('Name'), flex: 1.4, render: (m) => <Text numberOfLines={1} style={styles.name}>{m.name}</Text> },
              { key: 'type', label: t('Type'), flex: 1, render: (m) => m.material_type?.name ?? '—' },
              { key: 'unit_of_measure', label: t('UoM'), width: 60, render: (m) => m.unit_of_measure ?? '—' },
              { key: 'tracking_type', label: t('Tracking'), width: 80, render: (m) => (m.tracking_type ? (TRACKING_LABELS[m.tracking_type] ?? m.tracking_type) : '—') },
              { key: 'stock_quantity', label: t('Stock'), width: 60, render: (m) => <Mono size={11} color={colors.muted}>{m.stock_quantity != null ? String(m.stock_quantity) : '—'}</Mono> },
              { key: 'bom_items_count', label: t('In BOMs'), width: 60, render: (m) => <Mono size={11} color={colors.muted}>{String(m.bom_items_count ?? 0)}</Mono> },
              {
                key: 'is_active',
                label: t('Status'),
                width: 80,
                render: (m) => (
                  <StatusPill status={m.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={m.is_active === false ? t('Inactive') : t('Active')} />
                ),
              },
            ]}
            actions={(m) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/materials/${m.id}` as never) },
              { label: t('Delete'), icon: 'delete', onPress: () => onDelete(m) },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { paddingHorizontal: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
