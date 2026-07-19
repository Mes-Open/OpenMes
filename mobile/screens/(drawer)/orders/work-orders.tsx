/**
 * Work-orders list — the operator "Queue" (design: OpenMES Mobile.dc.html — Queue
 * tab; content mirrors the web operator Queue page Pages/operator/Queue.jsx).
 * Search + Active/Queued/Done segments + optional line chips; each row shows
 * product, status, order no, produced/planned and due, and swipes left to reveal
 * a Report action (operators don't transition status — reporting is their row
 * action on the web, so the swipe routes to the order where reporting lives).
 * Geist White, light-only v1.
 */
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { format, isValid, parseISO } from 'date-fns';
import { useTranslation } from 'react-i18next';

import { SegmentedControl, StatusPill, colors, fonts, radius, type StatusKey } from '@openmes/ui';
import { SearchField, SwipeRow } from '@openmes/ui/native';

import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useLines } from '@/hooks/queries/useUsers';
import { isWorkOrderOverdue, statusLabel } from '@/lib/statusLabels';
import type { WorkOrder, WorkOrderStatus } from '@/types/api';

// Labels are i18n keys (English phrase = key, per Laravel __() convention).
const STATUS_GROUPS: { key: string; label: string; statuses: WorkOrderStatus[] }[] = [
  { key: 'active', label: 'Active', statuses: ['IN_PROGRESS', 'BLOCKED', 'PAUSED'] },
  { key: 'queued', label: 'Queued', statuses: ['PENDING', 'ACCEPTED'] },
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
  const [query, setQuery] = useState('');

  const linesQuery = useLines();
  const lines = linesQuery.data ?? [];

  const filters = useMemo(() => {
    const group = STATUS_GROUPS.find((g) => g.key === statusKey);
    const f: { status?: WorkOrderStatus[]; line_id?: number; per_page?: number } = { per_page: 100 };
    if (group?.statuses) f.status = group.statuses;
    if (lineId != null) f.line_id = lineId;
    return f;
  }, [statusKey, lineId]);

  const woQuery = useWorkOrders(filters);
  const orders = woQuery.data ?? [];

  const q = query.trim().toLowerCase();
  const visible = q
    ? orders.filter(
        (o) =>
          o.order_no.toLowerCase().includes(q) ||
          (o.product_type?.name ?? '').toLowerCase().includes(q),
      )
    : orders;

  return (
    <View style={styles.screen}>
      {isTablet ? <TabletStatusStripLive /> : null}
      <View style={styles.filters}>
        <SearchField value={query} onChange={setQuery} placeholder={t('Search work orders')} />
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
                {t('All lines')}
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

      {woQuery.isLoading ? (
        <LoadingState />
      ) : woQuery.isError ? (
        <ErrorState error={woQuery.error} onRetry={woQuery.refetch} />
      ) : (
        <LegendList
          style={styles.screen}
          data={visible}
          keyExtractor={(wo) => String(wo.id)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <Text style={styles.swipeHint}>‹ {t('Swipe a row for actions')}</Text>
          }
          ListEmptyComponent={
            <EmptyState title={t('No work orders')} subtitle={t('Try a different filter or pull to refresh.')} />
          }
          renderItem={({ item }) => (
            <QueueRow
              workOrder={item}
              onPress={() => router.push(`/work-orders/${item.id}`)}
              onReport={() => router.push(`/work-orders/${item.id}`)}
              reportLabel={t('Report')}
            />
          )}
          ItemSeparatorComponent={() => <View style={{ height: 9 }} />}
          refreshControl={<RefreshControl refreshing={woQuery.isFetching} onRefresh={woQuery.refetch} />}
        />
      )}
    </View>
  );
}

function QueueRow({
  workOrder,
  onPress,
  onReport,
  reportLabel,
}: {
  workOrder: WorkOrder;
  onPress: () => void;
  onReport: () => void;
  reportLabel: string;
}) {
  const due = workOrder.due_date ? parseISO(workOrder.due_date) : null;
  // Day-first short date, matching the web queue's fmtDate (e.g. '02 Jul').
  const dueLabel = due && isValid(due) ? format(due, 'dd MMM') : null;
  const overdue = isWorkOrderOverdue(workOrder);
  const planned = Number(workOrder.planned_qty ?? 0);
  const produced = Number(workOrder.produced_qty ?? 0);
  const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;
  const complete = workOrder.status === 'DONE' || pct >= 100;

  return (
    <SwipeRow actions={[{ key: 'report', label: reportLabel, color: colors.blocked, onPress: onReport }]}>
      <Pressable accessibilityRole="button" onPress={onPress} style={styles.row}>
        <View style={styles.rowBody}>
          <View style={styles.rowTitleLine}>
            <Text style={styles.rowTitle} numberOfLines={1}>
              {workOrder.product_type?.name ?? workOrder.order_no}
            </Text>
            <StatusPill
              status={PILL_STATUS[workOrder.status] ?? 'pending'}
              label={statusLabel(workOrder.status).toUpperCase()}
            />
          </View>
          <Text style={styles.rowMeta} numberOfLines={1}>
            {`${workOrder.order_no} · ${produced}/${planned} PCS${planned > 0 ? ` · ${pct}%` : ''}`}
          </Text>
          {planned > 0 ? (
            <View style={styles.barTrack}>
              <View
                style={[
                  styles.barFill,
                  { width: `${pct}%`, backgroundColor: complete ? colors.running : colors.accent },
                ]}
              />
            </View>
          ) : null}
        </View>
        {dueLabel ? (
          <Text style={[styles.rowDue, overdue && styles.rowDueOverdue]}>{dueLabel}</Text>
        ) : null}
        <Text style={styles.chevron}>›</Text>
      </Pressable>
    </SwipeRow>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  filters: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    gap: 10,
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
  chipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
  chipText: { fontSize: 12, fontFamily: fonts.sans.native.semibold, color: colors.muted },
  chipTextActive: { color: '#FFFFFF' },
  list: { padding: 18, maxWidth: 680, width: '100%', alignSelf: 'center' },
  swipeHint: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 9,
    letterSpacing: 0.7,
    textTransform: 'uppercase',
    color: colors.faintest,
    marginBottom: 9,
    marginLeft: 2,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: colors.card,
    paddingVertical: 13,
    paddingHorizontal: 14,
    borderRadius: radius.md,
  },
  rowBody: { flex: 1, minWidth: 0 },
  rowTitleLine: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  rowTitle: { flexShrink: 1, fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  rowMeta: { fontFamily: fonts.mono.native.regular, fontSize: 10, color: colors.faint, marginTop: 3 },
  barTrack: { height: 4, borderRadius: 2, overflow: 'hidden', backgroundColor: colors.chip, marginTop: 6 },
  barFill: { height: '100%', borderRadius: 2 },
  rowDue: { fontFamily: fonts.mono.native.semibold, fontSize: 12, color: colors.muted },
  rowDueOverdue: { color: colors.blocked },
  chevron: { fontFamily: fonts.mono.native.regular, fontSize: 16, color: colors.faintest },
});
