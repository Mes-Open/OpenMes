/**
 * Work-orders list — restyled onto the Geist White design system (@openmes/ui).
 * Light-only v1: the previous Colors[scheme] light/dark switch is dropped here;
 * dark shop-floor theming returns with token theming later.
 * Behavior (queries, filters, navigation, i18n) is unchanged.
 */
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { format, isValid, parseISO } from 'date-fns';
import { useTranslation } from 'react-i18next';

import { SegmentedControl, StatusPill, colors, fonts, type StatusKey } from '@openmes/ui';

import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useLines } from '@/hooks/queries/useUsers';
import { isWorkOrderOverdue, statusLabel } from '@/lib/statusLabels';
import type { WorkOrder, WorkOrderStatus } from '@/types/api';

// Labels are i18n keys (English phrase = key, per Laravel __() convention).
const STATUS_GROUPS: { key: string; label: string; statuses?: WorkOrderStatus[] }[] = [
  { key: 'all', label: 'All' },
  { key: 'active', label: 'Active', statuses: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'BLOCKED', 'PAUSED'] },
  { key: 'pending', label: 'Not Started', statuses: ['PENDING'] },
  { key: 'in_progress', label: 'Running', statuses: ['IN_PROGRESS'] },
  { key: 'blocked', label: 'Blocked', statuses: ['BLOCKED'] },
  { key: 'done', label: 'Done', statuses: ['DONE'] },
];

/** Map API work-order statuses onto the design system's pill states. */
const PILL_STATUS: Record<WorkOrderStatus, StatusKey> = {
  PENDING: 'pending',
  ACCEPTED: 'pending',
  IN_PROGRESS: 'running',
  BLOCKED: 'blocked',
  PAUSED: 'downtime',
  DONE: 'done',
  REJECTED: 'blocked',
  CANCELLED: 'done',
};

export function WorkOrdersListScreen() {
  const router = useRouter();
  const { t } = useTranslation();
  const { useTabletLayout: isTablet } = useDeviceClass();

  const [statusKey, setStatusKey] = useState<string>('active');
  const [lineId, setLineId] = useState<number | null>(null);

  const linesQuery = useLines();
  const lines = linesQuery.data ?? [];

  const filters = useMemo(() => {
    const group = STATUS_GROUPS.find((g) => g.key === statusKey);
    const f: { status?: WorkOrderStatus[]; line_id?: number; per_page?: number } = { per_page: 100 };
    if (group?.statuses) f.status = group.statuses;
    if (lineId != null) f.line_id = lineId;
    return f;
  }, [statusKey, lineId]);

  const query = useWorkOrders(filters);
  const orders = query.data ?? [];

  return (
    <View style={styles.screen}>
      {isTablet ? <TabletStatusStripLive /> : null}
      <View style={styles.filters}>
        <SegmentedControl
          options={STATUS_GROUPS.map((g) => ({ value: g.key, label: t(g.label) }))}
          value={statusKey}
          onChange={setStatusKey}
        />
        {lines.length > 1 ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipsRow}>
            <Pressable
              onPress={() => setLineId(null)}
              accessibilityRole="button"
              accessibilityState={{ selected: lineId == null }}
              style={[styles.chip, lineId == null && styles.chipActive]}>
              <Text style={[styles.chipText, lineId == null && styles.chipTextActive]}>
                All lines
              </Text>
            </Pressable>
            {lines.map((l) => {
              const active = l.id === lineId;
              return (
                <Pressable
                  key={l.id}
                  onPress={() => setLineId(l.id)}
                  accessibilityRole="button"
                  accessibilityState={{ selected: active }}
                  style={[styles.chip, active && styles.chipActive]}>
                  <Text style={[styles.chipText, active && styles.chipTextActive]}>{l.name}</Text>
                </Pressable>
              );
            })}
          </ScrollView>
        ) : null}
      </View>

      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <LegendList
          style={styles.screen}
          data={orders}
          keyExtractor={(wo) => String(wo.id)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <View style={styles.listHeader}>
              <Text style={styles.sectionLabel}>{t('Work orders').toUpperCase()}</Text>
              <Text style={styles.sectionCount}>
                {orders.length} {orders.length === 1 ? 'ORDER' : 'ORDERS'}
              </Text>
            </View>
          }
          ListEmptyComponent={
            <EmptyState title="No work orders" subtitle="Try a different filter or pull to refresh." />
          }
          renderItem={({ item }) => (
            <WorkOrderRow workOrder={item} onPress={() => router.push(`/work-orders/${item.id}`)} />
          )}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
        />
      )}
    </View>
  );
}

function WorkOrderRow({ workOrder, onPress }: { workOrder: WorkOrder; onPress: () => void }) {
  const { t } = useTranslation();

  const due = workOrder.due_date ? parseISO(workOrder.due_date) : null;
  const dueLabel = due && isValid(due) ? format(due, 'MMM d') : null;
  const overdue = isWorkOrderOverdue(workOrder);
  const planned = workOrder.planned_qty ?? 0;
  const produced = workOrder.produced_qty ?? 0;

  const meta = [
    `${workOrder.order_no} · ${produced}/${planned} ${t('pcs').toUpperCase()}`,
    dueLabel ? `${t('DUE').toUpperCase()} ${dueLabel.toUpperCase()}` : null,
    overdue ? t('Overdue').toUpperCase() : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={styles.row}>
      <View style={styles.rowBody}>
        <Text style={styles.rowTitle} numberOfLines={1}>
          {workOrder.product_type?.name ?? '—'}
        </Text>
        <Text style={[styles.rowMeta, overdue && styles.rowMetaOverdue]} numberOfLines={1}>
          {meta}
        </Text>
      </View>
      <StatusPill
        status={PILL_STATUS[workOrder.status] ?? 'pending'}
        label={statusLabel(workOrder.status).toUpperCase()}
      />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  filters: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    gap: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line,
  },
  chipsRow: { flexDirection: 'row', gap: 8 },
  chip: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
  },
  chipActive: {
    backgroundColor: colors.ink,
    borderColor: colors.ink,
  },
  chipText: {
    fontSize: 12,
    fontFamily: fonts.sans.native.semibold,
    color: colors.muted,
  },
  chipTextActive: { color: '#FFFFFF' },
  list: { padding: 18 },
  listHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  sectionLabel: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.faint,
  },
  sectionCount: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 10,
    letterSpacing: 1.2,
    color: colors.faint,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line2,
    borderRadius: 12,
    paddingVertical: 13,
    paddingHorizontal: 14,
  },
  rowBody: { flex: 1, minWidth: 0 },
  rowTitle: {
    fontSize: 14,
    fontFamily: fonts.sans.native.semibold,
    color: colors.ink,
  },
  rowMeta: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 10,
    color: colors.faint,
    marginTop: 3,
  },
  rowMetaOverdue: { color: colors.blocked },
});
