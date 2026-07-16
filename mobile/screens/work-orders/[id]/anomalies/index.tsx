/**
 * Work-order anomalies — the production anomalies reported against a work order
 * (Code / Reason / Planned / Actual / Dev / Status), mirroring the web table
 * idiom. "New anomaly" adds one; supervisors/admins can process a draft; admins
 * long-press to delete. Data via REST.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteProductionAnomaly,
  useProcessProductionAnomaly,
  useProductionAnomalies,
} from '@/hooks/queries/useWoExtras';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { ProductionAnomaly } from '@/api/woExtras';

const STATUS_MAP: Record<string, string> = {
  draft: 'PENDING',
  processed: 'DONE',
};

export function AnomaliesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const woId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useProductionAnomalies({ work_order_id: woId });
  const processMutation = useProcessProductionAnomaly();
  const deleteMutation = useDeleteProductionAnomaly();

  const user = useAuthStore((s) => s.user);
  const canProcess = isSupervisorOrAdmin(user);
  const canDelete = user?.roles?.some((r) => r.name === 'Admin') ?? false;

  const items = query.data?.data ?? [];

  const onProcess = (item: ProductionAnomaly) =>
    processMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not process'), e.message) });

  const onDelete = (item: ProductionAnomaly) =>
    Alert.alert(t('Delete anomaly?'), item.anomaly_reason?.name ?? `ANOM-${item.id}`, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => deleteMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Anomalies')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('New anomaly')} size="sm" onPress={() => router.push(`/work-orders/${woId}/anomalies/new` as never)} />
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
          <View style={[styles.row, styles.headerRow]}>
            <HCell w={96}>{t('Code')}</HCell>
            <HCell flex={1}>{t('Reason')}</HCell>
            <HCell w={64}>{t('Planned')}</HCell>
            <HCell w={64}>{t('Actual')}</HCell>
            <HCell w={56}>{t('Dev')}</HCell>
            <HCell w={92}>{t('Status')}</HCell>
          </View>
          {items.map((item) => {
            const dev = item.deviation_pct != null ? Number(item.deviation_pct) : null;
            const devColor = dev != null && Math.abs(dev) > 10 ? colors.blocked : dev != null && Math.abs(dev) > 0 ? colors.downtime : colors.muted;
            return (
              <Pressable
                key={item.id}
                onLongPress={canDelete ? () => onDelete(item) : undefined}
                onPress={canProcess && item.status === 'draft' ? () => onProcess(item) : undefined}
                style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
                <View style={{ width: 96 }}>
                  <Mono size={11} color={colors.ink}>{(item.anomaly_reason?.code ?? `ANOM-${item.id}`).toUpperCase()}</Mono>
                </View>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={1} style={styles.title}>{item.anomaly_reason?.name ?? t('Anomaly')}</Text>
                </View>
                <View style={{ width: 64 }}>
                  <Mono size={11} color={colors.muted}>{String(item.planned_qty)}</Mono>
                </View>
                <View style={{ width: 64 }}>
                  <Mono size={11} color={colors.muted}>{String(item.actual_qty)}</Mono>
                </View>
                <View style={{ width: 56 }}>
                  <Mono size={11} color={devColor}>{dev != null ? `${dev}%` : '—'}</Mono>
                </View>
                <View style={{ width: 92 }}>
                  <StatusPill status={STATUS_MAP[item.status] ?? 'PENDING'} label={item.status} />
                </View>
              </Pressable>
            );
          })}
          {items.length === 0 ? <Text style={styles.empty}>{t('No anomalies.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
