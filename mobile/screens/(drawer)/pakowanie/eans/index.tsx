/**
 * Packaging EANs — 1:1 with the web packaging EAN table
 * (Pages/packaging/eans/Index.jsx): the shared DataTable with the web's column
 * set (Order / Product / Status / EAN / Packed-Plan) and a per-row delete action.
 * Rows show the barcode bound to a work order plus its packing progress (joined
 * client-side from the packaging items). "New EAN" opens the bind form. A filter
 * narrows to active/closed. Keeps the existing EAN-per-row hooks.
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
import { useDeleteEan, useEans, usePackagingItems } from '@/hooks/queries/usePackaging';
import type { WorkOrderEan } from '@/api/packaging';

type FilterId = 'all' | 'active' | 'closed';

type Progress = { packed: number; target: number; done: boolean };

const progressStatus = (p?: Progress): string =>
  p?.done ? 'DONE' : p && p.packed > 0 ? 'IN_PROGRESS' : 'PENDING';

export function EansList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useEans({});
  const items = usePackagingItems();
  const deleteMutation = useDeleteEan();

  // WO id → packed/target/done, for the per-EAN progress + status columns.
  const woProgress = useMemo(() => {
    const map = new Map<number, Progress>();
    (items.data ?? []).forEach((it) => map.set(it.id, { packed: it.packed_qty, target: it.planned_qty, done: it.done }));
    return map;
  }, [items.data]);

  const all = query.data?.data ?? [];
  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((e) => {
      const wo = e.work_order_id ? woProgress.get(e.work_order_id) : null;
      const isClosed = wo?.done === true;
      return filter === 'closed' ? isClosed : !isClosed;
    });
  }, [filter, all, woProgress]);

  const options = useMemo(
    () => [
      { value: 'all', label: t('All') },
      { value: 'active', label: t('Active') },
      { value: 'closed', label: t('Closed') },
    ],
    [t],
  );

  const onDelete = (e: WorkOrderEan) =>
    Alert.alert(t('Delete EAN'), t('Remove "{{ean}}"?', { ean: e.ean }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => deleteMutation.mutate(e.id, { onError: (err: Error) => Alert.alert(t('Could not delete'), err.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Packaging EANs')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 130 }}>
          <Dropdown value={filter} onChange={(v) => setFilter(v as FilterId)} options={options} />
        </View>
        <Button title={t('New EAN')} size="sm" onPress={() => router.push('/pakowanie/eans/new' as never)} />
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
          <DataTable<WorkOrderEan>
            data={filtered}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['ean']}
            emptyText={t('No EANs.')}
            columns={[
              {
                key: 'order',
                label: t('Order'),
                width: 100,
                render: (e) =>
                  e.work_order?.order_no ? (
                    <Mono size={11} color={colors.accent}>{e.work_order.order_no}</Mono>
                  ) : (
                    <Text style={styles.dash}>—</Text>
                  ),
              },
              {
                key: 'product',
                label: t('Product'),
                flex: 1.3,
                render: (e) => <Text numberOfLines={1} style={styles.cellText}>{e.work_order?.product_type?.name ?? '—'}</Text>,
              },
              {
                key: 'status',
                label: t('Status'),
                width: 100,
                render: (e) => {
                  const status = progressStatus(e.work_order_id ? woProgress.get(e.work_order_id) : undefined);
                  return <StatusPill status={status} />;
                },
              },
              {
                key: 'ean',
                label: t('EAN'),
                flex: 1.3,
                render: (e) => <Mono size={11.5} color={colors.ink}>{e.ean}</Mono>,
              },
              {
                key: 'packed',
                label: t('Packed'),
                width: 80,
                align: 'right',
                render: (e) => {
                  const p = e.work_order_id ? woProgress.get(e.work_order_id) : undefined;
                  const target = p?.target ?? 0;
                  return <Mono size={11} color={colors.muted}>{target > 0 ? `${p?.packed ?? 0}/${target}` : '—'}</Mono>;
                },
              },
            ]}
            actions={(e) => [{ label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(e) }]}
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
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  dash: { fontSize: 13, color: colors.faintest },
});
