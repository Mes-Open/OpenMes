/**
 * Production Anomalies — 1:1 with the web admin production-anomalies page
 * (Pages/admin/production-anomalies/Index.jsx): the shared DataTable with the
 * web's column set (Work Order / Product / Planned Qty / Actual Qty / Deviation
 * / Reason / Status) and per-row actions (process when unprocessed / delete).
 * A status filter sits beside the table; rows open the work order. Data via
 * REST useProductionAnomalies.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteProductionAnomaly, useProcessProductionAnomaly, useProductionAnomalies } from '@/hooks/queries/useWoExtras';
import type { ProductionAnomaly, ProductionAnomalyStatus } from '@/api/woExtras';

const STATUSES: ProductionAnomalyStatus[] = ['draft', 'processed'];
const humanize = (s: string) => s.replace(/\b\w/g, (c) => c.toUpperCase());

function deviation(a: ProductionAnomaly): number {
  if (a.deviation_pct != null) return Number(a.deviation_pct);
  const p = Number(a.planned_qty);
  if (!p) return 0;
  return ((Number(a.actual_qty) - p) / p) * 100;
}

export function ProductionAnomaliesScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const [status, setStatus] = useState('');

  const filters = useMemo(() => (status ? { status: status as ProductionAnomalyStatus } : {}), [status]);
  const q = useProductionAnomalies(filters);
  const process = useProcessProductionAnomaly();
  const del = useDeleteProductionAnomaly();
  const rows = q.data?.data ?? [];

  const onDelete = (a: ProductionAnomaly) =>
    Alert.alert(t('Delete this anomaly record?'), '', [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(a.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Production Anomalies')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 180 }}>
          <Dropdown
            value={status}
            onChange={(v) => setStatus(v as string)}
            placeholder={t('All statuses')}
            options={[{ value: '', label: t('All statuses') }, ...STATUSES.map((s) => ({ value: s, label: humanize(s) }))]}
          />
        </View>
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
          <DataTable<ProductionAnomaly>
            data={rows as ProductionAnomaly[]}
            searchPlaceholder={t('Search anomalies…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['product_name']}
            emptyText={t('No anomalies recorded')}
            onRowPress={(a) => router.push(`/work-orders/${a.work_order_id}` as never)}
            columns={[
              {
                key: 'work_order',
                label: t('Work Order'),
                width: 100,
                render: (a) => <Mono size={11} color={colors.accent}>{`WO #${a.work_order_id}`}</Mono>,
              },
              {
                key: 'product_name',
                label: t('Product'),
                flex: 1,
                render: (a) => <Text numberOfLines={1} style={styles.product}>{a.product_name ?? '—'}</Text>,
              },
              { key: 'planned_qty', label: t('Planned Qty'), width: 96, align: 'right', render: (a) => String(Number(a.planned_qty ?? 0)) },
              { key: 'actual_qty', label: t('Actual Qty'), width: 96, align: 'right', render: (a) => String(Number(a.actual_qty ?? 0)) },
              {
                key: 'deviation',
                label: t('Deviation'),
                width: 92,
                align: 'right',
                render: (a) => {
                  const dev = deviation(a);
                  const positive = dev >= 0;
                  return (
                    <Text style={[styles.dev, { color: positive ? colors.running : colors.blocked }]}>
                      {`${positive ? '+' : ''}${dev.toFixed(1)}%`}
                    </Text>
                  );
                },
              },
              { key: 'reason', label: t('Reason'), flex: 1, render: (a) => a.anomaly_reason?.name ?? '—' },
              { key: 'status', label: t('Status'), width: 108, render: (a) => <StatusPill status={a.status} /> },
            ]}
            actions={(a) => [
              ...(a.status === 'draft'
                ? [{ label: t('Process'), icon: 'activate' as const, onPress: () => process.mutate(a.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }) }]
                : []),
              { label: t('Delete'), icon: 'delete' as const, onPress: () => onDelete(a) },
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
  product: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  dev: { fontSize: 12.5, fontFamily: fonts.sans.native.medium },
});
