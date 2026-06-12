import { FontAwesome } from '@expo/vector-icons';
import { addDays, isToday, isTomorrow, isWithinInterval, parseISO, startOfDay } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useRoleScopedPath } from '@/hooks/useScopedPath';
import type { WorkOrder, WorkOrderStatus } from '@/types/api';

type RangeId = 'all' | 'today' | 'tomorrow' | 'week';

const RANGES: { id: RangeId; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'today', label: 'Today' },
  { id: 'tomorrow', label: 'Tomorrow' },
  { id: 'week', label: 'Week' },
];

// Extras the WO list endpoint doesn't return today (batches/operator/cycle
// time). Filled with dashes until the API ships those columns — see
// docs/api-gap-mobile-parity.md.
interface RowExtras {
  sku: string;
  qty: string;
  progress: number;
  batches: string;
  operator?: string;
  startedAt?: string;
  cycleAvg?: string;
  scrap?: number;
  good?: number;
  steps: { n: number; name: string; status: 'done' | 'running' | 'queued' }[];
}

interface RowData {
  wo: WorkOrder;
  extras: RowExtras;
}

function statusBand(status: WorkOrderStatus, palette: typeof Colors.light) {
  switch (status) {
    case 'IN_PROGRESS':
      return { color: BRAND.amber, label: 'RUNNING' };
    case 'PAUSED':
      return { color: '#f97316', label: 'PAUSED' };
    case 'BLOCKED':
      return { color: palette.danger, label: 'BLOCKED' };
    case 'ACCEPTED':
      return { color: palette.info, label: 'ACCEPTED' };
    case 'DONE':
      return { color: palette.success, label: 'DONE' };
    case 'PENDING':
      return { color: palette.textFaint, label: 'PENDING' };
    case 'CANCELLED':
      return { color: palette.textFaint, label: 'CANCELLED' };
    default:
      return { color: palette.textFaint, label: status };
  }
}

function due(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    const d = parseISO(value);
    if (Number.isNaN(d.getTime())) return value;
    if (isToday(d)) return value.slice(11, 16) || value.slice(0, 5);
    if (isTomorrow(d)) return 'Tomorrow';
    return value.slice(5, 10);
  } catch {
    return value;
  }
}

/**
 * Tablet "Schedule & dispatch" view of work orders — table on the left,
 * selected order detail panel on the right. Branches off the phone hub
 * (Orders / CSV imports) at the route level so phone navigation is unaffected.
 */
export function TabletWorkOrdersDispatch() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const scoped = useRoleScopedPath();

  const workOrdersQ = useWorkOrders({});
  // Default to "All" so the table isn't empty when nothing is due today —
  // the user can still filter to today/tomorrow/week with the tabs above.
  const [range, setRange] = useState<RangeId>('all');
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const allRows: RowData[] = useMemo(() => {
    const real = workOrdersQ.data ?? [];
    return real.map((wo) => {
      const planned = Number(wo.planned_qty ?? 0);
      const produced = Number(wo.produced_qty ?? 0);
      const progress = planned > 0 ? Math.min(1, produced / planned) : 0;
      return {
        wo,
        extras: {
          sku: wo.product_type?.name ?? '—',
          qty: `${planned} PCS`,
          progress,
          batches: '—',
          steps: [],
        },
      };
    });
  }, [workOrdersQ.data]);

  const rows = useMemo(() => {
    const now = new Date();
    const today = startOfDay(now);
    const tomorrow = startOfDay(addDays(now, 1));
    const inAWeek = startOfDay(addDays(now, 7));
    return allRows.filter(({ wo }) => {
      if (range === 'all') return true;
      if (!wo.due_date) return false;
      try {
        const d = parseISO(wo.due_date);
        if (range === 'today') return isToday(d);
        if (range === 'tomorrow') return isTomorrow(d);
        if (range === 'week') return isWithinInterval(d, { start: today, end: inAWeek });
      } catch {
        return false;
      }
      return false;
    });
  }, [allRows, range]);

  const selected: RowData | null =
    rows.find((r) => r.wo.id === selectedId) ?? rows[0] ?? null;

  return (
    <TabletShell
      eyebrow={`${t('WORK ORDERS').toUpperCase()} · A-SHIFT · ${rows.length} ${t('ACTIVE').toUpperCase()}`}
      title={t('Schedule & dispatch')}
      right={
        <>
          <View style={[styles.rangeTrack, { backgroundColor: palette.surfaceAlt }]}>
            {RANGES.map((r) => {
              const active = r.id === range;
              return (
                <Pressable
                  key={r.id}
                  onPress={() => setRange(r.id)}
                  style={[
                    styles.rangeTab,
                    active && {
                      backgroundColor: palette.surface,
                      boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.06)',
                    },
                  ]}>
                  <Mono
                    size={11}
                    weight="600"
                    color={active ? palette.text : palette.textMuted}>
                    {t(r.label)}
                  </Mono>
                </Pressable>
              );
            })}
          </View>
          <Pressable
            onPress={() => router.push('/work-orders/new' as never)}
            style={({ pressed }) => [
              styles.newWoBtn,
              { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
            ]}>
            <FontAwesome name="plus" size={12} color="#1a1208" />
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
              {t('New WO').toUpperCase()}
            </Mono>
          </Pressable>
        </>
      }>
      <View style={styles.body}>
        {/* LEFT — WO table */}
        <View style={[styles.tableCard, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <TableHeader palette={palette} />
          <ScrollView style={{ flex: 1 }}>
            {rows.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 24 }}>
                {t('No work orders in this range').toUpperCase()}
              </Mono>
            ) : (
              rows.map((row, i) => (
                <TableRow
                  key={row.wo.id}
                  row={row}
                  selected={selected?.wo.id === row.wo.id}
                  last={i === rows.length - 1}
                  onPress={() => setSelectedId(row.wo.id)}
                  onDoublePress={() =>
                    router.push(scoped(`/work-orders/${row.wo.id}`) as never)
                  }
                  palette={palette}
                />
              ))
            )}
          </ScrollView>
        </View>

        {/* RIGHT — Selected detail */}
        <View style={[styles.detailCard, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          {selected ? (
            <SelectedDetail
              row={selected}
              palette={palette}
              onOpenDetail={() => router.push(scoped(`/work-orders/${selected.wo.id}`) as never)}
            />
          ) : (
            <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 24 }}>
              {t('Select a work order to view details').toUpperCase()}
            </Mono>
          )}
        </View>
      </View>
    </TabletShell>
  );
}

const COL_TEMPLATE = '110px 1fr 90px 70px 90px 110px 80px' as unknown as never;

function TableHeader({ palette }: { palette: typeof Colors.light }) {
  const { t } = useTranslation();
  return (
    <View style={[styles.tableHead, { borderBottomColor: palette.border }]}>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colOrder}>{t('Order').toUpperCase()}</Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colSku}>{t('SKU').toUpperCase()} / {t('Qty').toUpperCase()}</Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colLine}>{t('Line').toUpperCase()}</Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colBatch}>{t('Batch').toUpperCase()}</Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colStatus}>{t('Status').toUpperCase()}</Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={styles.colProgress}>{t('Progress').toUpperCase()}</Mono>
      <Mono
        size={10}
        color={palette.textFaint}
        letterSpacing={0.7}
        style={[styles.colDue, { textAlign: 'right' }]}>
        {t('DUE').toUpperCase()}
      </Mono>
    </View>
  );
}

function TableRow({
  row,
  selected,
  last,
  onPress,
  onDoublePress,
  palette,
}: {
  row: RowData;
  selected: boolean;
  last: boolean;
  onPress: () => void;
  onDoublePress?: () => void;
  palette: typeof Colors.light;
}) {
  const { t } = useTranslation();
  const band = statusBand(row.wo.status, palette);
  // Manual double-tap detection — RN Pressable doesn't expose `onDoublePress`
  // directly. We treat a second tap within 300ms as a double-tap and fire the
  // detail-open handler. A single tap still selects the row.
  const lastTapRef = useRef(0);
  const handleTap = () => {
    const now = Date.now();
    if (now - lastTapRef.current < 300 && onDoublePress) {
      lastTapRef.current = 0;
      onDoublePress();
      return;
    }
    lastTapRef.current = now;
    onPress();
  };
  return (
    <Pressable
      onPress={handleTap}
      style={({ pressed }) => [
        styles.tableRow,
        {
          backgroundColor: selected ? '#fdf3df' : 'transparent',
          borderLeftColor: selected ? BRAND.amber : 'transparent',
          // Thicker, darker border so rows read clearly from operator distance
          // (mirrors web commit 8d8a038).
          borderBottomColor: last ? 'transparent' : palette.border,
          opacity: pressed ? 0.92 : 1,
        },
      ]}>
      <Mono size={11.5} color={palette.text} weight="700" style={styles.colOrder}>
        {row.wo.order_no}
      </Mono>
      <View style={styles.colSku}>
        <Text style={{ fontSize: 13, fontWeight: '600', color: palette.text }} numberOfLines={1}>
          {row.extras.sku}
        </Text>
        <Mono size={10} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 2 }}>
          {row.extras.qty}
        </Mono>
      </View>
      <View style={styles.colLine}>
        <View style={[styles.lineChip, { backgroundColor: palette.surfaceAlt }]}>
          <Mono size={11} color={palette.text} weight="600">
            {row.wo.line?.code ?? row.wo.line?.name ?? '—'}
          </Mono>
        </View>
      </View>
      <Mono size={11} color={palette.textMuted} style={styles.colBatch}>
        {row.extras.batches}
      </Mono>
      <View style={styles.colStatus}>
        <View style={[styles.statusPill, { backgroundColor: `${band.color}22` }]}>
          <Mono size={9.5} color={band.color} weight="700" letterSpacing={0.5}>
            {t(band.label).toUpperCase()}
          </Mono>
        </View>
      </View>
      <View style={[styles.colProgress, { height: 4, justifyContent: 'center' }]}>
        <View style={[styles.progressTrack, { backgroundColor: palette.surfaceAlt }]}>
          <View
            style={{
              width: `${row.extras.progress * 100}%`,
              height: '100%',
              backgroundColor: band.color,
            }}
          />
        </View>
      </View>
      <Mono
        size={11.5}
        color={row.wo.due_date ? palette.text : palette.textFaint}
        weight="600"
        style={[styles.colDue, { textAlign: 'right' }]}>
        {due(row.wo.due_date)}
      </Mono>
    </Pressable>
  );
}

function SelectedDetail({
  row,
  palette,
  onOpenDetail,
}: {
  row: RowData;
  palette: typeof Colors.light;
  onOpenDetail: () => void;
}) {
  const { t } = useTranslation();
  const band = statusBand(row.wo.status, palette);
  const wo = row.wo;
  const planned = Number(wo.planned_qty ?? 0);
  const produced = Number(wo.produced_qty ?? row.extras.good ?? 0);
  const progress = planned > 0 ? Math.min(1, produced / planned) : row.extras.progress;

  // Process steps: prefer real steps from the active batch when we know the
  // batch id (only the mock rows carry pretend steps inline). For real WOs,
  // call into the batch query when possible.
  const batchSteps = useRealOrMockSteps(wo, row.extras.steps);

  return (
    <ScrollView contentContainerStyle={{ padding: 18, gap: 14 }}>
      <View>
        <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
          {t('Selected').toUpperCase()} · {wo.order_no}
        </Mono>
        <Text style={[styles.detailTitle, { color: palette.text }]} numberOfLines={2}>
          {wo.product_type?.name ?? row.extras.sku}
        </Text>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
          {[
            `${t('SKU').toUpperCase()} ${wo.product_type?.id != null ? `PRD-${wo.product_type.id}` : '—'}`,
            `${planned} ${t('PCS').toUpperCase()}`,
            wo.due_date ? `${t('DUE').toUpperCase()} ${due(wo.due_date)}` : null,
          ]
            .filter(Boolean)
            .join(' · ')}
        </Mono>
      </View>

      {/* Progress card — light surface that fits the warm-cream detail panel. */}
      <View style={[styles.progressCard, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
          <View>
            <Mono size={9.5} color={palette.textFaint} letterSpacing={0.7}>{t('Progress').toUpperCase()}</Mono>
            <Mono size={36} weight="700" color={band.color} letterSpacing={-1} style={{ marginTop: 4 }}>
              {Math.round(produced)}{' '}
              <Text style={{ fontSize: 16, color: palette.textFaint, fontWeight: '500' }}>
                / {Math.round(planned)}
              </Text>
            </Mono>
          </View>
          <Mono size={11} color={band.color} weight="700">
            {t(band.label).toUpperCase()} · {t('BATCH').toUpperCase()} {row.extras.batches}
          </Mono>
        </View>
        <View style={[styles.progressTrack, { backgroundColor: palette.surface, marginTop: 12 }]}>
          <View
            style={{
              width: `${progress * 100}%`,
              height: '100%',
              backgroundColor: band.color,
            }}
          />
        </View>
      </View>

      <View style={styles.kvGrid}>
        <KV label={t('Line').toUpperCase()} value={wo.line ? `${wo.line.code ?? ''} ${wo.line.name}`.trim() : '—'} palette={palette} />
        <KV label={t('Operator').toUpperCase()} value={row.extras.operator ?? '—'} palette={palette} />
        <KV label={t('Started').toUpperCase()} value={row.extras.startedAt ?? '—'} palette={palette} />
        <KV label={t('Cycle avg').toUpperCase()} value={row.extras.cycleAvg ?? '—'} palette={palette} />
        <KV label={t('GOOD').toUpperCase()} value={`${Math.round(produced)} ${t('PCS').toUpperCase()}`} palette={palette} />
        <KV label={t('SCRAP').toUpperCase()} value={`${row.extras.scrap ?? 0} ${t('PCS').toUpperCase()}`} palette={palette} />
      </View>

      <View>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('PROCESS').toUpperCase()} · {batchSteps.length} {t('STEPS').toUpperCase()}
        </Mono>
        <View style={{ marginTop: 8, gap: 4 }}>
          {batchSteps.map((s) => (
            <View
              key={s.n}
              style={[
                styles.stepRow,
                {
                  backgroundColor: s.status === 'running' ? '#fdf3df' : 'transparent',
                },
              ]}>
              <View
                style={[
                  styles.stepDot,
                  {
                    backgroundColor:
                      s.status === 'done'
                        ? palette.success
                        : s.status === 'running'
                        ? BRAND.amber
                        : palette.surfaceAlt,
                  },
                ]}>
                {s.status === 'done' ? (
                  <FontAwesome name="check" size={10} color="#fff" />
                ) : (
                  <Mono
                    size={10}
                    color={s.status === 'queued' ? palette.textFaint : '#fff'}
                    weight="700">
                    {s.n}
                  </Mono>
                )}
              </View>
              <Text
                style={{
                  flex: 1,
                  fontSize: 12,
                  color: palette.text,
                  fontWeight: s.status === 'running' ? '600' : '400',
                }}
                numberOfLines={1}>
                {s.name}
              </Text>
            </View>
          ))}
          {batchSteps.length === 0 ? (
            <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 8 }}>
              {t('NO STEPS DEFINED').toUpperCase()}
            </Mono>
          ) : null}
        </View>
      </View>

      <View style={{ flexDirection: 'row', gap: 8 }}>
        <Pressable
          style={({ pressed }) => [
            styles.actionBtn,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              borderWidth: 1,
              opacity: pressed ? 0.85 : 1,
            },
          ]}>
          <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
            {t('Reassign').toUpperCase()}
          </Mono>
        </Pressable>
        <Pressable
          onPress={onOpenDetail}
          style={({ pressed }) => [
            styles.actionBtn,
            { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
          ]}>
          <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.5}>
            {t('Open detail').toUpperCase()}
          </Mono>
        </Pressable>
      </View>
    </ScrollView>
  );
}

function KV({
  label,
  value,
  palette,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
}) {
  return (
    <View style={[styles.kv, { backgroundColor: palette.surfaceAlt }]}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>{label}</Mono>
      <Mono size={13} color={palette.text} weight="700" style={{ marginTop: 4 }}>
        {value}
      </Mono>
    </View>
  );
}

type StepRow = { n: number; name: string; status: 'done' | 'running' | 'queued' };

/** Resolve process steps for the selected WO. Returns the fallback list
 * (empty by default) until the WO list endpoint returns batches. */
function useRealOrMockSteps(wo: WorkOrder, fallback: StepRow[]): StepRow[] {
  void wo;
  return fallback;
}

const styles = StyleSheet.create({
  rangeTrack: { flexDirection: 'row', gap: 4, padding: 4, borderRadius: 10 },
  rangeTab: { paddingVertical: 6, paddingHorizontal: 14, borderRadius: 6 },
  newWoBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
  },

  body: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  tableCard: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 16,
    overflow: 'hidden',
  },
  detailCard: {
    width: 420,
    borderWidth: 1,
    borderRadius: 16,
    overflow: 'hidden',
  },

  tableHead: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 16,
    gap: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    paddingHorizontal: 16,
    gap: 12,
    borderLeftWidth: 3,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },

  colOrder: { width: 110 },
  colSku: { flex: 1, minWidth: 0 },
  colLine: { width: 90 },
  colBatch: { width: 70 },
  colStatus: { width: 90 },
  colProgress: { width: 110 },
  colDue: { width: 80 },

  lineChip: {
    alignSelf: 'flex-start',
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 4,
  },
  statusPill: {
    alignSelf: 'flex-start',
    paddingVertical: 3,
    paddingHorizontal: 6,
    borderRadius: 4,
  },
  progressTrack: { width: '100%', height: 4, borderRadius: 2, overflow: 'hidden' },

  detailTitle: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 4 },
  progressCard: {
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
  },
  kvGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kv: { flexBasis: '47%', flexGrow: 1, padding: 10, borderRadius: 8 },

  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 6,
    paddingHorizontal: 8,
    borderRadius: 6,
  },
  stepDot: {
    width: 20,
    height: 20,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionBtn: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
